#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/aws-polly-svn-deploy.XXXXXX")"
REPOSITORY="${WORKDIR}/repository"
WORKING_COPY="${WORKDIR}/working-copy"
ASSETS_V1="${WORKDIR}/assets-v1"
ASSETS_V2="${WORKDIR}/assets-v2"
SLUG="ai-text-to-speech-using-aws-polly"

cleanup() {
	rm -rf -- "${WORKDIR}"
}
trap cleanup EXIT

fail() {
	printf 'SVN deploy test failed: %s\n' "$1" >&2
	exit 1
}

assert_revision() {
	local expected="$1"
	local actual
	actual="$(svnlook youngest "${REPOSITORY}")"
	[ "${actual}" = "${expected}" ] || fail "expected repository revision ${expected}, got ${actual}"
}

make_candidate() {
	local directory="$1"
	local plugin_version="$2"
	local stable_tag="$3"
	local marker="$4"

	mkdir -p "${directory}"
	printf '%s\n' '<?php' '/**' ' * Plugin Name: AI Text-to-Speech using AWS Polly' " * Version: ${plugin_version}" ' */' > "${directory}/itron-polly-tts.php"
	printf '%s\n' '=== AI Text-to-Speech using AWS Polly ===' "Stable tag: ${stable_tag}" > "${directory}/readme.txt"
	printf '%s\n' "${marker}" > "${directory}/release-marker.txt"
}

deploy() {
	SVN_USERNAME=local-test SVN_PASSWORD=local-test \
		"${REPO_ROOT}/scripts/deploy-wordpress-svn.sh" \
		--slug "${SLUG}" \
		--svn-url "file://${REPOSITORY}" \
		--working-copy "${WORKING_COPY}" \
		"$@"
}

for tool in python3 rsync svn svnadmin svnlook; do
	command -v "${tool}" >/dev/null 2>&1 || {
		printf 'SVN deploy test failed: required tool is missing: %s\n' "${tool}" >&2
		exit 2
	}
done

BUILD_V1="${WORKDIR}/build-v1"
make_candidate "${BUILD_V1}" 1.0.8 1.0.8 'initial release'
mkdir -p \
	"${BUILD_V1}/vendor/legacy/deep" \
	"${BUILD_V1}/vendor/package with spaces/nested" \
	"${ASSETS_V1}"
printf '%s\n' 'obsolete nested dependency' > "${BUILD_V1}/vendor/legacy/deep/Legacy.php"
printf '%s\n' 'obsolete spaced dependency' > "${BUILD_V1}/vendor/package with spaces/nested/Legacy.php"
printf '%s\n' 'initial screenshot' > "${ASSETS_V1}/screenshot-1.png"
printf '%s\n' 'obsolete screenshot' > "${ASSETS_V1}/stale-screenshot.png"

svnadmin create "${REPOSITORY}"
assert_revision 0

deploy --version 1.0.8 --build-dir "${BUILD_V1}" --assets-dir "${ASSETS_V1}" >/dev/null
assert_revision 1
svn info "file://${REPOSITORY}/trunk" >/dev/null 2>&1 || fail 'initial publish did not create trunk'
svn info "file://${REPOSITORY}/tags/1.0.8" >/dev/null 2>&1 || fail 'initial publish did not create the version tag'
[ "$(svnlook cat "${REPOSITORY}" trunk/release-marker.txt)" = 'initial release' ] || fail 'initial trunk does not contain the candidate'
[ "$(svnlook cat "${REPOSITORY}" tags/1.0.8/release-marker.txt)" = 'initial release' ] || fail 'initial tag does not contain the candidate'
[ "$(svn propget svn:mime-type "file://${REPOSITORY}/assets/screenshot-1.png")" = 'image/png' ] || fail 'screenshot MIME type was not set'

rerun_output="$(deploy --version 1.0.8 --build-dir "${BUILD_V1}" --assets-dir "${ASSETS_V1}")"
case "${rerun_output}" in
	*'already deployed exactly'*) ;;
	*) fail 'exact retry was not treated as idempotent' ;;
esac
assert_revision 1

BUILD_CHANGED="${WORKDIR}/build-changed"
make_candidate "${BUILD_CHANGED}" 1.0.8 1.0.8 'changed release with reused version'
if changed_output="$(deploy --version 1.0.8 --build-dir "${BUILD_CHANGED}" --assets-dir "${ASSETS_V1}" 2>&1)"; then
	fail 'changed candidate reused an existing version'
fi
case "${changed_output}" in
	*'exists in SVN but trunk/tag do not match'*) ;;
	*) fail 'same-version refusal did not report the expected reason' ;;
esac
assert_revision 1

BUILD_V2="${WORKDIR}/build-v2"
make_candidate "${BUILD_V2}" 1.0.9 1.0.9 'upgrade release'
mkdir -p "${BUILD_V2}/vendor/current/deep" "${ASSETS_V2}"
printf '%s\n' 'retained current dependency' > "${BUILD_V2}/vendor/current/deep/Current.php"
printf '%s\n' 'updated screenshot' > "${ASSETS_V2}/screenshot-1.png"

deploy --version 1.0.9 --build-dir "${BUILD_V2}" --assets-dir "${ASSETS_V2}" >/dev/null
assert_revision 2
svn info "file://${REPOSITORY}/trunk/vendor/legacy" >/dev/null 2>&1 && fail 'upgrade retained an obsolete nested directory'
svn info "file://${REPOSITORY}/trunk/vendor/package%20with%20spaces" >/dev/null 2>&1 && fail 'upgrade retained an obsolete path containing spaces'
svn info "file://${REPOSITORY}/assets/stale-screenshot.png" >/dev/null 2>&1 && fail 'upgrade retained an obsolete asset'
svn info "file://${REPOSITORY}/tags/1.0.9/vendor/current/deep/Current.php" >/dev/null 2>&1 || fail 'upgrade tag is missing the candidate dependency'
svn info "file://${REPOSITORY}/tags/1.0.8/vendor/legacy/deep/Legacy.php" >/dev/null 2>&1 || fail 'upgrade changed the prior immutable tag'

BUILD_ROLLBACK="${WORKDIR}/build-rollback"
make_candidate "${BUILD_ROLLBACK}" 1.0.7 1.0.7 'accidental rollback'
if rollback_output="$(deploy --version 1.0.7 --build-dir "${BUILD_ROLLBACK}" 2>&1)"; then
	fail 'older candidate without an existing tag was accepted'
fi
case "${rollback_output}" in
	*'refusing rollback from trunk version 1.0.9 to older version 1.0.7'*) ;;
	*) fail 'rollback refusal did not report the expected reason' ;;
esac
assert_revision 2
svn info "file://${REPOSITORY}/tags/1.0.7" >/dev/null 2>&1 && fail 'rollback refusal created a repository tag'
[ "$(svnlook cat "${REPOSITORY}" trunk/release-marker.txt)" = 'upgrade release' ] || fail 'rollback refusal changed trunk'

BUILD_PLUGIN_MISMATCH="${WORKDIR}/build-plugin-mismatch"
make_candidate "${BUILD_PLUGIN_MISMATCH}" 2.0.1 2.0.0 'plugin mismatch'
if mismatch_output="$(deploy --version 2.0.0 --build-dir "${BUILD_PLUGIN_MISMATCH}" 2>&1)"; then
	fail 'plugin header mismatch was accepted'
fi
case "${mismatch_output}" in
	*'plugin header version 2.0.1 does not match 2.0.0'*) ;;
	*) fail 'plugin header mismatch did not report the expected reason' ;;
esac
assert_revision 2

BUILD_README_MISMATCH="${WORKDIR}/build-readme-mismatch"
make_candidate "${BUILD_README_MISMATCH}" 2.0.0 2.0.1 'readme mismatch'
if mismatch_output="$(deploy --version 2.0.0 --build-dir "${BUILD_README_MISMATCH}" 2>&1)"; then
	fail 'readme stable tag mismatch was accepted'
fi
case "${mismatch_output}" in
	*'readme stable tag 2.0.1 does not match 2.0.0'*) ;;
	*) fail 'readme stable tag mismatch did not report the expected reason' ;;
esac
assert_revision 2

BUILD_DRY_RUN="${WORKDIR}/build-dry-run"
make_candidate "${BUILD_DRY_RUN}" 1.0.10 1.0.10 'dry-run release'
dry_run_output="$(deploy --version 1.0.10 --build-dir "${BUILD_DRY_RUN}" --dry-run)"
case "${dry_run_output}" in
	*'Dry run complete; no SVN commit was made.'*) ;;
	*) fail 'dry run did not report completion' ;;
esac
assert_revision 2
svn info "file://${REPOSITORY}/tags/1.0.10" >/dev/null 2>&1 && fail 'dry run created a repository tag'
svn status --xml "${WORKING_COPY}" | python3 "${REPO_ROOT}/scripts/svn-status.py" validate --working-copy "${WORKING_COPY}"

STATUS_FIXTURE="${WORKDIR}/status.xml"
cat > "${STATUS_FIXTURE}" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<status><target path=".">
<entry path="assets/stale-screenshot.png"><wc-status item="missing" props="none" revision="1"/></entry>
<entry path="trunk/vendor/parent"><wc-status item="missing" props="none" revision="1"/></entry>
<entry path="trunk/vendor/parent/child.php"><wc-status item="missing" props="none" revision="1"/></entry>
<entry path="trunk/vendor/path with spaces"><wc-status item="missing" props="none" revision="1"/></entry>
</target></status>
XML

mapfile -d '' missing_roots < <(python3 "${REPO_ROOT}/scripts/svn-status.py" missing-roots --working-copy "${WORKING_COPY}" < "${STATUS_FIXTURE}")
[ "${#missing_roots[@]}" -eq 3 ] || fail 'status parser did not collapse nested missing paths'
[ "${missing_roots[0]}" = 'assets/stale-screenshot.png' ] || fail 'status parser returned the wrong asset root'
[ "${missing_roots[1]}" = 'trunk/vendor/parent' ] || fail 'status parser returned the wrong nested root'
[ "${missing_roots[2]}" = 'trunk/vendor/path with spaces' ] || fail 'status parser did not preserve a path containing spaces'

printf 'SVN deploy test passed: local publish, guards, upgrade, and dry run are safe.\n'
