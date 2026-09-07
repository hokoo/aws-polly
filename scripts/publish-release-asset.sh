#!/usr/bin/env bash
set -Eeuo pipefail

mode="${1:-check}"
case "$mode" in
	check|upload) ;;
	*) printf 'Expected check or upload mode.\n' >&2; exit 1 ;;
esac
: "${RELEASE_TAG:?RELEASE_TAG is required}"
: "${ZIP_PATH:?ZIP_PATH is required}"
[ -f "$ZIP_PATH" ] || { printf 'Release ZIP is missing.\n' >&2; exit 1; }
name="$(basename "$ZIP_PATH")"
[[ "$name" =~ ^[a-zA-Z0-9._-]+\.zip$ ]] || exit 1

release="$(gh release view "$RELEASE_TAG" --json assets)"
exists="$(jq -r --arg name "$name" 'any(.assets[]; .name == $name)' <<< "$release")"
if [ "$exists" = true ]; then
	TEMP_DIR="$(mktemp -d)"
	trap 'rm -rf -- "$TEMP_DIR"' EXIT
	gh release download "$RELEASE_TAG" --pattern "$name" --dir "$TEMP_DIR"
	if ! cmp -s "$ZIP_PATH" "$TEMP_DIR/$name"; then
		printf 'Existing GitHub release ZIP differs; refusing to overwrite %s.\n' "$name" >&2
		exit 1
	fi
	printf 'GitHub release ZIP already matches exactly; no upload is needed.\n'
elif [ "$exists" = false ]; then
	if [ "$mode" = upload ]; then
		gh release upload "$RELEASE_TAG" "$ZIP_PATH"
	else
		printf 'GitHub release ZIP does not exist yet.\n'
	fi
else
	printf 'Could not determine GitHub release asset state.\n' >&2
	exit 1
fi
