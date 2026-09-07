#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
STATUS_PARSER="${SCRIPT_DIR}/svn-status.py"
PLUGIN_ENTRYPOINT="itron-polly-tts.php"

SLUG="${SLUG:-}"
VERSION="${VERSION:-}"
BUILD_DIR="${BUILD_DIR:-}"
ASSETS_DIR="${ASSETS_DIR:-${REPO_ROOT}/.wordpress-org}"
SVN_URL="${SVN_URL:-}"
SVN_DIR="${SVN_DIR:-}"
DRY_RUN="${INPUT_DRY_RUN:-${DRY_RUN:-false}}"
KEEP_WORKING_COPY="${KEEP_WORKING_COPY:-false}"
CREATED_WORKING_COPY=false
TAGS_CREATED=false
STATUS_XML=""
MISSING_LIST=""
FINAL_STATUS_XML=""
SVN_HTTP_TIMEOUT="${SVN_HTTP_TIMEOUT:-3600}"
SVN_TAG_RECONCILE_ATTEMPTS="${SVN_TAG_RECONCILE_ATTEMPTS:-3}"
SVN_TAG_RECONCILE_DELAY="${SVN_TAG_RECONCILE_DELAY:-2}"

usage() {
	cat <<'USAGE'
Usage: scripts/deploy-wordpress-svn.sh [options]

Options:
  --slug <slug>             WordPress.org plugin slug.
  --version <version>       Version/tag to publish.
  --build-dir <path>        Unpacked, verified plugin build.
  --assets-dir <path>       WordPress.org assets source directory (defaults to .wordpress-org when present).
  --svn-url <url>           SVN repository URL (defaults to WordPress.org).
  --working-copy <path>     Explicit working-copy path.
  --dry-run                 Prepare and validate without committing.
  --keep-working-copy       Do not remove an automatically-created checkout.
  -h, --help                Show this help.

SVN_USERNAME and SVN_PASSWORD are required unless --dry-run is used.
USAGE
}

fail() {
	printf 'WordPress.org deploy failed: %s\n' "$1" >&2
	exit 1
}

numeric_version_is_older() {
	local candidate="$1"
	local current="$2"
	local candidate_part
	local current_part
	local index
	local max_parts
	local -a candidate_parts
	local -a current_parts

	IFS='.' read -r -a candidate_parts <<< "${candidate}"
	IFS='.' read -r -a current_parts <<< "${current}"
	max_parts="${#candidate_parts[@]}"
	if [ "${#current_parts[@]}" -gt "${max_parts}" ]; then
		max_parts="${#current_parts[@]}"
	fi

	for ((index = 0; index < max_parts; index++)); do
		candidate_part="${candidate_parts[index]:-0}"
		current_part="${current_parts[index]:-0}"
		if ((10#${candidate_part} < 10#${current_part})); then
			return 0
		fi
		if ((10#${candidate_part} > 10#${current_part})); then
			return 1
		fi
	done

	return 1
}

while [ "$#" -gt 0 ]; do
	case "$1" in
		--slug)
			shift
			[ "$#" -gt 0 ] || fail 'missing value for --slug'
			SLUG="$1"
			;;
		--version)
			shift
			[ "$#" -gt 0 ] || fail 'missing value for --version'
			VERSION="$1"
			;;
		--build-dir)
			shift
			[ "$#" -gt 0 ] || fail 'missing value for --build-dir'
			BUILD_DIR="$1"
			;;
		--assets-dir)
			shift
			[ "$#" -gt 0 ] || fail 'missing value for --assets-dir'
			ASSETS_DIR="$1"
			;;
		--svn-url)
			shift
			[ "$#" -gt 0 ] || fail 'missing value for --svn-url'
			SVN_URL="$1"
			;;
		--working-copy)
			shift
			[ "$#" -gt 0 ] || fail 'missing value for --working-copy'
			SVN_DIR="$1"
			;;
		--dry-run)
			DRY_RUN=true
			;;
		--keep-working-copy)
			KEEP_WORKING_COPY=true
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			fail "unknown argument: $1"
			;;
	esac
	shift
done

[[ "${SLUG}" =~ ^[a-z0-9][a-z0-9-]*$ ]] || fail 'SLUG must contain lowercase letters, digits, or hyphens'
[[ "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]] || fail 'VERSION must be a semantic plugin version'
[[ "${SVN_HTTP_TIMEOUT}" =~ ^[1-9][0-9]*$ ]] || fail 'SVN_HTTP_TIMEOUT must be a positive number of seconds'
[[ "${SVN_TAG_RECONCILE_ATTEMPTS}" =~ ^[1-9][0-9]*$ ]] || fail 'SVN_TAG_RECONCILE_ATTEMPTS must be a positive number'
[[ "${SVN_TAG_RECONCILE_DELAY}" =~ ^[0-9]+$ ]] || fail 'SVN_TAG_RECONCILE_DELAY must be a non-negative number of seconds'
[ -d "${BUILD_DIR}" ] || fail "BUILD_DIR does not exist: ${BUILD_DIR}"
[ -f "${STATUS_PARSER}" ] || fail "status parser is missing: ${STATUS_PARSER}"

for tool in find python3 rsync svn svnmucc; do
	command -v "${tool}" >/dev/null 2>&1 || fail "required tool is missing: ${tool}"
done

SVN_NETWORK_ARGS=(
	--config-option "servers:global:http-timeout=${SVN_HTTP_TIMEOUT}"
)

SVN_URL="${SVN_URL:-https://plugins.svn.wordpress.org/${SLUG}/}"
SVN_URL="${SVN_URL%/}/"
BUILD_DIR="$(cd "${BUILD_DIR}" && pwd)"
if [ -d "${ASSETS_DIR}" ]; then
	ASSETS_DIR="$(cd "${ASSETS_DIR}" && pwd)"
else
	ASSETS_DIR=""
fi

if [ -z "${SVN_DIR}" ]; then
	SVN_DIR="$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${SLUG}-svn.XXXXXX")"
	CREATED_WORKING_COPY=true
else
	mkdir -p "${SVN_DIR}"
	SVN_DIR="$(cd "${SVN_DIR}" && pwd)"
fi

cleanup() {
	[ -z "${STATUS_XML}" ] || rm -f -- "${STATUS_XML}"
	[ -z "${MISSING_LIST}" ] || rm -f -- "${MISSING_LIST}"
	[ -z "${FINAL_STATUS_XML}" ] || rm -f -- "${FINAL_STATUS_XML}"
	if [ "${CREATED_WORKING_COPY}" = true ] && [ "${KEEP_WORKING_COPY}" != true ]; then
		rm -rf -- "${SVN_DIR}"
	fi
}
trap cleanup EXIT

if [ -e "${SVN_DIR}/.svn" ]; then
	[ "$(svn info --show-item url "${SVN_DIR}")/" = "${SVN_URL}" ] || fail 'working copy URL does not match SVN_URL'
	svn cleanup "${SVN_DIR}"
	svn revert -R "${SVN_DIR}" >/dev/null
else
	[ -z "$(find "${SVN_DIR}" -mindepth 1 -maxdepth 1 -print -quit)" ] || fail 'working-copy path is not empty'
	svn checkout --depth immediates "${SVN_URL}" "${SVN_DIR}" "${SVN_NETWORK_ARGS[@]}"
fi

cd "${SVN_DIR}"
if svn info trunk >/dev/null 2>&1; then
	svn update --set-depth infinity trunk "${SVN_NETWORK_ARGS[@]}"
else
	[ ! -e trunk ] || fail 'unversioned trunk obstructs the deployment working copy'
	mkdir trunk
	svn add trunk >/dev/null
fi
if svn info tags >/dev/null 2>&1; then
	svn update --set-depth immediates tags "${SVN_NETWORK_ARGS[@]}"
else
	[ ! -e tags ] || fail 'unversioned tags obstructs the deployment working copy'
	mkdir tags
	svn add tags >/dev/null
	TAGS_CREATED=true
fi
if svn info assets >/dev/null 2>&1; then
	svn update --set-depth infinity assets "${SVN_NETWORK_ARGS[@]}"
elif [ -n "${ASSETS_DIR}" ]; then
	[ ! -e assets ] || fail 'unversioned assets obstructs the deployment working copy'
	mkdir assets
	svn add assets >/dev/null
fi

plugin_version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "${BUILD_DIR}/${PLUGIN_ENTRYPOINT}" | head -n 1)"
stable_tag="$(sed -nE 's/^Stable tag:[[:space:]]*([^[:space:]]+).*/\1/p' "${BUILD_DIR}/readme.txt" | head -n 1)"
[ "${plugin_version}" = "${VERSION}" ] || fail "plugin header version ${plugin_version:-missing} does not match ${VERSION}"
[ "${stable_tag}" = "${VERSION}" ] || fail "readme stable tag ${stable_tag:-missing} does not match ${VERSION}"

remote_tag_state() {
	local tag_diff

	if ! svn info "${SVN_URL}tags/${VERSION}" "${SVN_NETWORK_ARGS[@]}" >/dev/null 2>&1; then
		return 1
	fi
	if ! svn update --set-depth infinity "tags/${VERSION}" "${SVN_NETWORK_ARGS[@]}" >/dev/null; then
		return 3
	fi
	tag_diff="$(rsync -rcn --delete --exclude '.svn' --itemize-changes "${BUILD_DIR}/" "tags/${VERSION}/")"
	[ -z "${tag_diff}" ] || return 2
	return 0
}

if remote_tag_state; then
	printf 'Plugin %s version %s is already deployed exactly; no SVN commit is needed.\n' "${SLUG}" "${VERSION}"
	exit 0
else
	tag_state="$?"
	case "${tag_state}" in
		1) ;;
		2) fail "version ${VERSION} exists in SVN but its immutable tag does not match the exact candidate" ;;
		*) fail "version ${VERSION} exists in SVN but its tag could not be verified" ;;
	esac
fi
[ ! -e "tags/${VERSION}" ] || fail "unversioned tags/${VERSION} obstructs the deployment working copy"

trunk_version=""
if [ -f "trunk/${PLUGIN_ENTRYPOINT}" ]; then
	trunk_version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "trunk/${PLUGIN_ENTRYPOINT}" | head -n 1)"
fi
if [[ "${VERSION}" =~ ^[0-9]+(\.[0-9]+)*$ ]] \
	&& [[ "${trunk_version}" =~ ^[0-9]+(\.[0-9]+)*$ ]] \
	&& numeric_version_is_older "${VERSION}" "${trunk_version}"; then
	fail "refusing rollback from trunk version ${trunk_version} to older version ${VERSION}"
fi

printf 'Syncing verified build into SVN trunk...\n'
rsync -rc --delete --delete-excluded "${BUILD_DIR}/" trunk/
if [ -n "${ASSETS_DIR}" ]; then
	printf 'Syncing WordPress.org assets from %s...\n' "${ASSETS_DIR}"
	rsync -rc --delete --delete-excluded "${ASSETS_DIR}/" assets/
fi

# Snapshot status before changing the working copy. Streaming `svn status` into
# `svn rm` races on large removals and caused the source plugin's deployment failure.
STATUS_XML="$(mktemp "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${SLUG}-svn-status.XXXXXX.xml")"
MISSING_LIST="$(mktemp "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${SLUG}-svn-missing.XXXXXX.list")"
svn status --xml > "${STATUS_XML}"
python3 "${STATUS_PARSER}" missing-roots --working-copy "${SVN_DIR}" < "${STATUS_XML}" > "${MISSING_LIST}"
while IFS= read -r -d '' missing_path; do
	svn rm --force -- "${missing_path}" >/dev/null
done < "${MISSING_LIST}"
rm -f -- "${STATUS_XML}" "${MISSING_LIST}"
STATUS_XML=""
MISSING_LIST=""

svn add trunk --force >/dev/null
if [ -n "${ASSETS_DIR}" ]; then
	svn add assets --force >/dev/null
	while IFS= read -r -d '' asset_path; do
		case "${asset_path,,}" in
			*.png)
				svn propset svn:mime-type image/png "${asset_path}" >/dev/null
				;;
			*.jpg|*.jpeg)
				svn propset svn:mime-type image/jpeg "${asset_path}" >/dev/null
				;;
			*.gif)
				svn propset svn:mime-type image/gif "${asset_path}" >/dev/null
				;;
		esac
	done < <(find assets -type f \( -iname '*.png' -o -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.gif' \) -print0)
fi

FINAL_STATUS_XML="$(mktemp "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${SLUG}-svn-final-status.XXXXXX.xml")"
svn status --xml > "${FINAL_STATUS_XML}"
python3 "${STATUS_PARSER}" validate --working-copy "${SVN_DIR}" < "${FINAL_STATUS_XML}"
rm -f -- "${FINAL_STATUS_XML}"
FINAL_STATUS_XML=""

svn status

if [ "${DRY_RUN}" = true ]; then
	printf 'Would copy the committed trunk revision to %stags/%s.\n' "${SVN_URL}" "${VERSION}"
	printf 'Dry run complete; no SVN commit was made.\n'
	exit 0
fi

[ -n "${SVN_USERNAME:-}" ] || fail 'SVN_USERNAME is required'
[ -n "${SVN_PASSWORD:-}" ] || fail 'SVN_PASSWORD is required'

SVN_AUTH_ARGS=(
	--no-auth-cache
	--non-interactive
	--username "${SVN_USERNAME}"
	--password "${SVN_PASSWORD}"
	"${SVN_NETWORK_ARGS[@]}"
)
COMMIT_PATHS=(trunk)
if [ "${TAGS_CREATED}" = true ]; then
	COMMIT_PATHS+=(tags)
fi
if [ -n "${ASSETS_DIR}" ]; then
	COMMIT_PATHS+=(assets)
fi

LC_ALL=C svn commit "${COMMIT_PATHS[@]}" \
	-m "Prepare trunk for version ${VERSION} from GitHub" \
	"${SVN_AUTH_ARGS[@]}"

TRUNK_REVISION="$(svn info --show-item revision "${SVN_URL}trunk" "${SVN_AUTH_ARGS[@]}")"
[[ "${TRUNK_REVISION}" =~ ^[0-9]+$ ]] || fail 'could not determine the committed trunk revision'

svn update --set-depth infinity -r "${TRUNK_REVISION}" trunk "${SVN_AUTH_ARGS[@]}" >/dev/null
trunk_diff="$(rsync -rcn --delete --exclude '.svn' --itemize-changes "${BUILD_DIR}/" trunk/)"
[ -z "${trunk_diff}" ] || fail "committed trunk revision ${TRUNK_REVISION} does not match the exact candidate"
if [ -n "${ASSETS_DIR}" ]; then
	svn update --set-depth infinity -r "${TRUNK_REVISION}" assets "${SVN_AUTH_ARGS[@]}" >/dev/null
	assets_diff="$(rsync -rcn --delete --exclude '.svn' --itemize-changes "${ASSETS_DIR}/" assets/)"
	[ -z "${assets_diff}" ] || fail "committed assets at revision ${TRUNK_REVISION} do not match the exact candidate"
fi

if svnmucc \
	-U "${SVN_URL}" \
	-m "Tag version ${VERSION} from GitHub" \
	"${SVN_AUTH_ARGS[@]}" \
	cp "${TRUNK_REVISION}" trunk "tags/${VERSION}"; then
	printf 'Plugin %s version %s deployed to WordPress.org SVN.\n' "${SLUG}" "${VERSION}"
	exit 0
else
	copy_status="$?"
fi

for ((attempt = 1; attempt <= SVN_TAG_RECONCILE_ATTEMPTS; attempt++)); do
	if remote_tag_state; then
		printf 'Tag %s exists and matches the candidate after the SVN copy response failed.\n' "${VERSION}"
		printf 'Plugin %s version %s deployed to WordPress.org SVN.\n' "${SLUG}" "${VERSION}"
		exit 0
	else
		tag_state="$?"
		if [ "${tag_state}" -eq 2 ]; then
			fail "version ${VERSION} was created concurrently with different immutable contents"
		fi
	fi

	if [ "${attempt}" -lt "${SVN_TAG_RECONCILE_ATTEMPTS}" ]; then
		sleep "${SVN_TAG_RECONCILE_DELAY}"
	fi
done

printf 'WordPress.org tag %s could not be confirmed after the failed SVN copy.\n' "${VERSION}" >&2
exit "${copy_status}"
