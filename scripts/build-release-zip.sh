#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${PLUGIN_DIR:-$ROOT_DIR/plugin-dir}"
PLUGIN_SLUG="${PLUGIN_SLUG:-ai-text-to-speech-using-aws-polly}"
ZIP_PATH="${ZIP_PATH:-$ROOT_DIR/dist/${PLUGIN_SLUG}-wp-plugin.zip}"
export TZ=UTC

[[ "$PLUGIN_SLUG" =~ ^[a-z0-9][a-z0-9-]*$ ]] || exit 1
case "$ZIP_PATH" in
	/*) ;;
	*) ZIP_PATH="$ROOT_DIR/$ZIP_PATH" ;;
esac
[ -f "$PLUGIN_DIR/composer.lock" ] || { printf 'Missing plugin composer.lock\n' >&2; exit 1; }

STAGE_DIR="$(mktemp -d)"
trap 'rm -rf -- "$STAGE_DIR"' EXIT
PLUGIN_STAGE="$STAGE_DIR/$PLUGIN_SLUG"
mkdir -p "$PLUGIN_STAGE" "$(dirname "$ZIP_PATH")"

# Install into staging so local development dependencies are never removed.
rsync -a "$PLUGIN_DIR/" "$PLUGIN_STAGE/" \
	--exclude '/vendor/' --exclude '/.git/' --exclude '/.github/' \
	--exclude '/.env' --exclude '/.env.*' --exclude '/assets/' --exclude '/README.md'
# Older Composer versions otherwise generate a random autoloader class suffix.
lock_hash="$(sha256sum "$PLUGIN_STAGE/composer.lock")"
composer --working-dir="$PLUGIN_STAGE" config autoloader-suffix "ItronPollyTts${lock_hash:0:32}"
composer --working-dir="$PLUGIN_STAGE" install --no-dev --no-interaction --prefer-dist
rsync -a "$PLUGIN_DIR/composer.json" "$PLUGIN_STAGE/composer.json"

PACKAGE_DIR="$STAGE_DIR/package/$PLUGIN_SLUG"
mkdir -p "$PACKAGE_DIR"
rsync -a "$PLUGIN_STAGE/" "$PACKAGE_DIR/" --exclude-from "$PLUGIN_DIR/.distignore"
rm -rf "$PACKAGE_DIR/vendor/aws/Aws"
find "$PACKAGE_DIR/vendor" -type d \( -name .github -o -name tests -o -name test -o -name docs -o -name bin \) -prune -exec rm -rf {} +
find "$PACKAGE_DIR/vendor" -type f \( \
	-name composer.json -o -name composer.lock -o -name phpunit.xml -o \
	-name phpunit.xml.dist -o -name README.md -o -name CHANGELOG.md -o \
	-name .gitignore -o -name .gitattributes \
\) -delete

SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-$(git -C "$PLUGIN_DIR" log -1 --format=%ct)}"
find "$PACKAGE_DIR" -type d -exec chmod 755 {} +
find "$PACKAGE_DIR" -type f -exec chmod 644 {} +
find "$PACKAGE_DIR" -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +
rm -f -- "$ZIP_PATH"
(
	cd "$STAGE_DIR/package"
	find "$PLUGIN_SLUG" -print | LC_ALL=C sort | zip -q -9 -X "$ZIP_PATH" -@
)
python3 "$ROOT_DIR/scripts/validate-release-zip.py" "$ZIP_PATH"
