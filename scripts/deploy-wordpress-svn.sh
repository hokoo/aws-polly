#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
STATUS_PARSER="${SCRIPT_DIR}/svn-status.py"
TREE_METADATA_TOOL="${SCRIPT_DIR}/tree-release-metadata.py"
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
TAGS_REMOTE_ABSENT=false
STATUS_XML=""
MISSING_LIST=""
FINAL_STATUS_XML=""
REMOTE_VERIFY_DIR=""
VERIFIED_REVISION=""
SVN_HTTP_TIMEOUT="${SVN_HTTP_TIMEOUT:-900}"
SVN_RECONCILE_ATTEMPTS="${SVN_RECONCILE_ATTEMPTS:-6}"
SVN_RECONCILE_DELAY="${SVN_RECONCILE_DELAY:-5}"

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

report_state() {
	printf 'RELEASE_STATE %s\n' "$*"
	if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
		printf -- '- `%s`\n' "$*" >> "${GITHUB_STEP_SUMMARY}"
	fi
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
[[ "${SVN_RECONCILE_ATTEMPTS}" =~ ^[1-9][0-9]*$ ]] || fail 'SVN_RECONCILE_ATTEMPTS must be a positive number'
[[ "${SVN_RECONCILE_DELAY}" =~ ^[0-9]+$ ]] || fail 'SVN_RECONCILE_DELAY must be a non-negative number of seconds'
[ -d "${BUILD_DIR}" ] || fail "BUILD_DIR does not exist: ${BUILD_DIR}"
[ -f "${STATUS_PARSER}" ] || fail "status parser is missing: ${STATUS_PARSER}"
[ -f "${TREE_METADATA_TOOL}" ] || fail "tree metadata tool is missing: ${TREE_METADATA_TOOL}"

for tool in cut find grep python3 rsync sha256sum svn svnmucc; do
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
	[ -z "${REMOTE_VERIFY_DIR}" ] || rm -rf -- "${REMOTE_VERIFY_DIR}"
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
IFS=$'\t' read -r TRUNK_DIGEST TRUNK_FILES TRUNK_ENTRIES TRUNK_BYTES < <(
	python3 "${TREE_METADATA_TOOL}" "${BUILD_DIR}"
)
ASSETS_DIGEST="none"
ASSETS_FILES=0
ASSETS_ENTRIES=0
ASSETS_BYTES=0
if [ -n "${ASSETS_DIR}" ]; then
	IFS=$'\t' read -r ASSETS_DIGEST ASSETS_FILES ASSETS_ENTRIES ASSETS_BYTES < <(
		python3 "${TREE_METADATA_TOOL}" "${ASSETS_DIR}"
	)
fi
DEPLOYMENT_ID="$(
	printf '%s\n' "${SLUG}" "${VERSION}" "${TRUNK_DIGEST}" "${ASSETS_DIGEST}" \
		| sha256sum | cut -c 1-32
)"
COMMIT_MESSAGE="Prepare ${SLUG} ${VERSION} deployment=${DEPLOYMENT_ID} trunk=${TRUNK_DIGEST} assets=${ASSETS_DIGEST}"
report_state "candidate_verified version=${VERSION} deployment=${DEPLOYMENT_ID} trunk_files=${TRUNK_FILES} trunk_entries=${TRUNK_ENTRIES} trunk_bytes=${TRUNK_BYTES} assets_files=${ASSETS_FILES} assets_entries=${ASSETS_ENTRIES} assets_bytes=${ASSETS_BYTES}"

reset_remote_verify_dir() {
	if [ -z "${REMOTE_VERIFY_DIR}" ]; then
		REMOTE_VERIFY_DIR="$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${SLUG}-svn-remote.XXXXXX")"
	else
		rm -rf -- "${REMOTE_VERIFY_DIR}"
		mkdir -p "${REMOTE_VERIFY_DIR}"
	fi
}

remote_tag_state() {
	local root_listing
	local tag_listing
	local tag_diff

	if ! tag_listing="$(svn list "${SVN_URL}tags/" "${SVN_NETWORK_ARGS[@]}" 2>/dev/null)"; then
		if ! root_listing="$(svn list "${SVN_URL}" "${SVN_NETWORK_ARGS[@]}" 2>/dev/null)"; then
			return 3
		fi
		if grep -Fxq 'tags/' <<< "${root_listing}"; then
			return 3
		fi
		TAGS_REMOTE_ABSENT=true
		return 1
	fi
	TAGS_REMOTE_ABSENT=false
	if ! grep -Fxq "${VERSION}/" <<< "${tag_listing}"; then
		return 1
	fi

	reset_remote_verify_dir
	if ! svn export --quiet "${SVN_URL}tags/${VERSION}" "${REMOTE_VERIFY_DIR}/tag" "${SVN_NETWORK_ARGS[@]}"; then
		return 3
	fi
	tag_diff="$(rsync -rcn --delete --itemize-changes "${BUILD_DIR}/" "${REMOTE_VERIFY_DIR}/tag/")"
	[ -z "${tag_diff}" ] || return 2
	return 0
}

verify_remote_asset_properties() {
	local actual_mime
	local expected_mime
	local relative_path
	local source_path

	[ -n "${ASSETS_DIR}" ] || return 0
	while IFS= read -r -d '' source_path; do
		relative_path="${source_path#"${ASSETS_DIR}/"}"
		case "${source_path,,}" in
			*.png) expected_mime='image/png' ;;
			*.jpg|*.jpeg) expected_mime='image/jpeg' ;;
			*.gif) expected_mime='image/gif' ;;
			*) continue ;;
		esac
		if ! actual_mime="$(svn propget --strict svn:mime-type "${REMOTE_VERIFY_DIR}/assets/${relative_path}" 2>/dev/null)"; then
			return 1
		fi
		[ "${actual_mime}" = "${expected_mime}" ] || return 1
	done < <(find "${ASSETS_DIR}" -type f -print0)
	return 0
}

verify_candidate_at_revision() {
	local remote_assets_metadata
	local remote_trunk_metadata
	local remote_digest
	local revision="$1"

	[[ "${revision}" =~ ^[0-9]+$ ]] || return 3
	reset_remote_verify_dir
	if ! svn checkout --quiet -r "${revision}" "${SVN_URL}trunk" "${REMOTE_VERIFY_DIR}/trunk" "${SVN_NETWORK_ARGS[@]}"; then
		return 3
	fi
	if ! remote_trunk_metadata="$(python3 "${TREE_METADATA_TOOL}" "${REMOTE_VERIFY_DIR}/trunk")"; then
		return 3
	fi
	IFS=$'\t' read -r remote_digest _ <<< "${remote_trunk_metadata}"
	[ "${remote_digest}" = "${TRUNK_DIGEST}" ] || return 2

	if [ -n "${ASSETS_DIR}" ]; then
		if ! svn checkout --quiet -r "${revision}" "${SVN_URL}assets" "${REMOTE_VERIFY_DIR}/assets" "${SVN_NETWORK_ARGS[@]}"; then
			return 3
		fi
		if ! remote_assets_metadata="$(python3 "${TREE_METADATA_TOOL}" "${REMOTE_VERIFY_DIR}/assets")"; then
			return 3
		fi
		IFS=$'\t' read -r remote_digest _ <<< "${remote_assets_metadata}"
		[ "${remote_digest}" = "${ASSETS_DIGEST}" ] || return 2
		verify_remote_asset_properties || return 2
	fi

	VERIFIED_REVISION="${revision}"
	return 0
}

observe_prepared_candidate() {
	local current_revision
	local deployment_conflict=false
	local log_xml
	local revision
	local root_listing
	local state
	local -a matching_revisions

	if ! root_listing="$(svn list "${SVN_URL}" "${SVN_NETWORK_ARGS[@]}")"; then
		return 3
	fi
	if ! grep -Fxq 'trunk/' <<< "${root_listing}"; then
		return 1
	fi
	if [ -n "${ASSETS_DIR}" ] && ! grep -Fxq 'assets/' <<< "${root_listing}"; then
		return 1
	fi
	if ! current_revision="$(svn info --show-item revision "${SVN_URL}" "${SVN_NETWORK_ARGS[@]}")"; then
		return 3
	fi
	if verify_candidate_at_revision "${current_revision}"; then
		return 0
	else
		state="$?"
		[ "${state}" -ne 3 ] || return 3
	fi

	if ! log_xml="$(svn log --xml --search "deployment=${DEPLOYMENT_ID}" --limit 100 "${SVN_URL}" "${SVN_NETWORK_ARGS[@]}")"; then
		return 3
	fi
	mapfile -t matching_revisions < <(
		printf '%s' "${log_xml}" | python3 -c \
			'import sys, xml.etree.ElementTree as ET; sys.stdout.write("\n".join(entry.attrib["revision"] for entry in ET.parse(sys.stdin).findall("logentry")))'
	)
	for revision in "${matching_revisions[@]}"; do
		if verify_candidate_at_revision "${revision}"; then
			return 0
		else
			state="$?"
			[ "${state}" -ne 3 ] || return 3
			deployment_conflict=true
		fi
	done
	[ "${deployment_conflict}" = false ] || return 2
	return 1
}

observe_prepared_candidate_with_retry() {
	local attempt
	local state=3

	for ((attempt = 1; attempt <= SVN_RECONCILE_ATTEMPTS; attempt++)); do
		if observe_prepared_candidate; then
			return 0
		else
			state="$?"
			[ "${state}" -ne 2 ] || return 2
		fi
		if [ "${attempt}" -lt "${SVN_RECONCILE_ATTEMPTS}" ]; then
			sleep "${SVN_RECONCILE_DELAY}"
		fi
	done
	return "${state}"
}

if remote_tag_state; then
	report_state "tag_exact version=${VERSION}"
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
report_state "tag_absent version=${VERSION}"
[ ! -e "tags/${VERSION}" ] || fail "unversioned tags/${VERSION} obstructs the deployment working copy"

prepared_before_sync=false
if observe_prepared_candidate; then
	prepared_before_sync=true
	report_state "trunk_exact revision=${VERIFIED_REVISION} deployment=${DEPLOYMENT_ID} recovered=true"
else
	prepared_state="$?"
	case "${prepared_state}" in
		1) report_state "trunk_prepare_required deployment=${DEPLOYMENT_ID}" ;;
		2) fail "deployment ${DEPLOYMENT_ID} exists in SVN history with different contents" ;;
		*) fail 'remote SVN state could not be observed safely; no write was attempted' ;;
	esac
fi

if [ "${prepared_before_sync}" = false ]; then
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
				*.png) svn propset svn:mime-type image/png "${asset_path}" >/dev/null ;;
				*.jpg|*.jpeg) svn propset svn:mime-type image/jpeg "${asset_path}" >/dev/null ;;
				*.gif) svn propset svn:mime-type image/gif "${asset_path}" >/dev/null ;;
			esac
		done < <(find assets -type f \( -iname '*.png' -o -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.gif' \) -print0)
	fi

	FINAL_STATUS_XML="$(mktemp "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${SLUG}-svn-final-status.XXXXXX.xml")"
	svn status --xml > "${FINAL_STATUS_XML}"
	python3 "${STATUS_PARSER}" validate --working-copy "${SVN_DIR}" < "${FINAL_STATUS_XML}"
	rm -f -- "${FINAL_STATUS_XML}"
	FINAL_STATUS_XML=""

	svn status
fi

if [ "${DRY_RUN}" = true ]; then
	report_state "dry_run_ready version=${VERSION}"
	if [ "${prepared_before_sync}" = true ]; then
		printf 'Would copy verified trunk revision %s to %stags/%s.\n' "${VERIFIED_REVISION}" "${SVN_URL}" "${VERSION}"
	else
		printf 'Would commit candidate changes, verify the resulting revision, and copy it to %stags/%s.\n' "${SVN_URL}" "${VERSION}"
	fi
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
if [ -n "${ASSETS_DIR}" ]; then
	COMMIT_PATHS+=(assets)
fi

commit_attempted=false
commit_status=0
if [ "${prepared_before_sync}" = false ]; then
	if [ -n "$(svn status --quiet "${COMMIT_PATHS[@]}")" ]; then
		commit_attempted=true
		report_state "trunk_commit_started version=${VERSION} deployment=${DEPLOYMENT_ID}"
		if LC_ALL=C svn commit "${COMMIT_PATHS[@]}" \
			-m "${COMMIT_MESSAGE}" \
			"${SVN_AUTH_ARGS[@]}"; then
			commit_status=0
			report_state "trunk_commit_response status=0"
		else
			commit_status="$?"
			report_state "trunk_commit_response_uncertain status=${commit_status}"
		fi
	fi

	if observe_prepared_candidate_with_retry; then
		report_state "trunk_exact revision=${VERIFIED_REVISION} deployment=${DEPLOYMENT_ID}"
	else
		prepared_state="$?"
		case "${prepared_state}" in
			2) fail "deployment ${DEPLOYMENT_ID} exists in SVN history with different contents" ;;
			3) fail "SVN state remained unavailable after ${SVN_RECONCILE_ATTEMPTS} observations; no further write was attempted" ;;
		esac
		if [ "${commit_attempted}" = true ] && [ "${commit_status}" -ne 0 ]; then
			fail "SVN commit returned status ${commit_status} and no exact prepared revision was found"
		fi
		if [ "${commit_attempted}" = true ]; then
			fail 'SVN commit completed but no exact prepared revision was found'
		fi
		fail 'working copy had no pending changes but no exact prepared revision was found'
	fi
fi

if remote_tag_state; then
	report_state "tag_exact version=${VERSION} reconciled=true"
	printf 'Plugin %s version %s is already deployed exactly; no SVN tag copy is needed.\n' "${SLUG}" "${VERSION}"
	exit 0
else
	tag_state="$?"
	case "${tag_state}" in
		1) ;;
		2) fail "version ${VERSION} was created concurrently with different immutable contents" ;;
		*) fail "remote SVN tag state could not be observed safely; no tag write was attempted" ;;
	esac
fi

SVNMUCC_OPERATIONS=()
if [ "${TAGS_REMOTE_ABSENT}" = true ]; then
	SVNMUCC_OPERATIONS+=(mkdir tags)
fi
SVNMUCC_OPERATIONS+=(cp "${VERIFIED_REVISION}" trunk "tags/${VERSION}")

report_state "tag_copy_started version=${VERSION} source_revision=${VERIFIED_REVISION} create_parent=${TAGS_REMOTE_ABSENT}"
if svnmucc \
	-U "${SVN_URL}" \
	-m "Tag ${SLUG} ${VERSION} deployment=${DEPLOYMENT_ID} source=${VERIFIED_REVISION}" \
	"${SVN_AUTH_ARGS[@]}" \
	"${SVNMUCC_OPERATIONS[@]}"; then
	report_state "release_complete version=${VERSION} source_revision=${VERIFIED_REVISION}"
	printf 'Plugin %s version %s deployed to WordPress.org SVN.\n' "${SLUG}" "${VERSION}"
	exit 0
else
	copy_status="$?"
	report_state "tag_copy_response_uncertain status=${copy_status}"
fi

for ((attempt = 1; attempt <= SVN_RECONCILE_ATTEMPTS; attempt++)); do
	if remote_tag_state; then
		report_state "release_complete version=${VERSION} source_revision=${VERIFIED_REVISION} reconciled=true"
		printf 'Tag %s exists and matches the candidate after the SVN copy response failed.\n' "${VERSION}"
		printf 'Plugin %s version %s deployed to WordPress.org SVN.\n' "${SLUG}" "${VERSION}"
		exit 0
	else
		tag_state="$?"
		if [ "${tag_state}" -eq 2 ]; then
			fail "version ${VERSION} was created concurrently with different immutable contents"
		fi
	fi

	if [ "${attempt}" -lt "${SVN_RECONCILE_ATTEMPTS}" ]; then
		sleep "${SVN_RECONCILE_DELAY}"
	fi
done

printf 'WordPress.org tag %s could not be confirmed after the failed SVN copy.\n' "${VERSION}" >&2
exit "${copy_status}"
