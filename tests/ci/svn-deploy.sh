#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
WORKDIR="$(mktemp -d "${TMPDIR:-/tmp}/aws-polly-svn-deploy.XXXXXX")"
REPOSITORY="${WORKDIR}/repository"
WORKING_COPY="${WORKDIR}/working-copy"
ASSETS_V1="${WORKDIR}/assets-v1"
ASSETS_V2="${WORKDIR}/assets-v2"
RECOVERY_REPOSITORY="${WORKDIR}/recovery-repository"
RECOVERY_WORKING_COPY="${WORKDIR}/recovery-working-copy"
RECOVERY_SEED_COPY="${WORKDIR}/recovery-seed-copy"
RECOVERY_IMPORT="${WORKDIR}/recovery-import"
TIMEOUT_REPOSITORY="${WORKDIR}/timeout-repository"
TIMEOUT_WORKING_COPY="${WORKDIR}/timeout-working-copy"
COLLISION_REPOSITORY="${WORKDIR}/collision-repository"
COLLISION_WORKING_COPY="${WORKDIR}/collision-working-copy"
MUTATION_REPOSITORY="${WORKDIR}/mutation-repository"
MUTATION_WORKING_COPY="${WORKDIR}/mutation-working-copy"
MUTATION_ACTOR_COPY="${WORKDIR}/mutation-actor-copy"
MUTATION_SENTINEL="${WORKDIR}/mutation-sentinel"
SVN_SHIM_DIR="${WORKDIR}/svn-shim"
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
	local repository="${2:-${REPOSITORY}}"
	local actual
	actual="$(svnlook youngest "${repository}")"
	[ "${actual}" = "${expected}" ] || fail "expected repository revision ${expected}, got ${actual}"
}

assert_tag_copy() {
	local repository="$1"
	local tag_revision="$2"
	local version="$3"
	local trunk_revision="$4"
	local changed
	local changed_paths

	changed="$(svnlook changed --copy-info -r "${tag_revision}" "${repository}")"
	changed_paths="$(svnlook changed -r "${tag_revision}" "${repository}")"
	case "${changed}" in
		*"A + tags/${version}/"*"(from trunk/:r${trunk_revision})"*) ;;
		*) fail "tag ${version} was not copied from trunk revision ${trunk_revision}: ${changed}" ;;
	esac
	[ "${changed_paths}" = "A   tags/${version}/" ] || fail "tag revision ${tag_revision} contains changes beyond the server-side copy: ${changed_paths}"
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

deploy_to() {
	local repository="$1"
	local working_copy="$2"
	shift 2

	SVN_USERNAME=local-test SVN_PASSWORD=local-test \
		"${REPO_ROOT}/scripts/deploy-wordpress-svn.sh" \
		--slug "${SLUG}" \
		--svn-url "file://${repository}" \
		--working-copy "${working_copy}" \
		"$@"
}

deploy() {
	deploy_to "${REPOSITORY}" "${WORKING_COPY}" "$@"
}

for tool in python3 rsync svn svnadmin svnlook svnmucc; do
	command -v "${tool}" >/dev/null 2>&1 || {
		printf 'SVN deploy test failed: required tool is missing: %s\n' "${tool}" >&2
		exit 2
	}
done

REAL_SVN_BIN="$(command -v svn)"
REAL_SVNMUCC_BIN="$(command -v svnmucc)"
mkdir -p "${SVN_SHIM_DIR}"
cat > "${SVN_SHIM_DIR}/svn" <<'SH'
#!/usr/bin/env bash
set -u

if [ "${SVN_TEST_MUTATE_TRUNK:-false}" = true ] \
	&& [ "${1:-}" = info ] \
	&& [ "${2:-}" = --show-item ] \
	&& [ "${3:-}" = revision ] \
	&& [ ! -e "${SVN_TEST_MUTATION_SENTINEL}" ]; then
	"${REAL_SVN_BIN}" checkout -q "${SVN_TEST_REPOSITORY_URL}" "${SVN_TEST_MUTATION_WORKING_COPY}"
	printf '%s\n' 'concurrent manual change' > "${SVN_TEST_MUTATION_WORKING_COPY}/trunk/concurrent-change.txt"
	"${REAL_SVN_BIN}" add -q "${SVN_TEST_MUTATION_WORKING_COPY}/trunk/concurrent-change.txt"
	"${REAL_SVN_BIN}" commit -q "${SVN_TEST_MUTATION_WORKING_COPY}/trunk/concurrent-change.txt" -m 'Simulate a concurrent trunk mutation'
	touch "${SVN_TEST_MUTATION_SENTINEL}"
fi

"${REAL_SVN_BIN}" "$@"
SH
chmod +x "${SVN_SHIM_DIR}/svn"

cat > "${SVN_SHIM_DIR}/svnmucc" <<'SH'
#!/usr/bin/env bash
set -u

if [ "${SVN_TEST_CREATE_COLLIDING_TAG:-false}" = true ]; then
	"${REAL_SVNMUCC_BIN}" \
		-m 'Create a concurrent immutable tag collision' \
		cp HEAD "${SVN_TEST_COLLISION_SOURCE}" "${SVN_TEST_COLLISION_DESTINATION}"
fi

"${REAL_SVNMUCC_BIN}" "$@"
status="$?"
if [ "${status}" -eq 0 ] && [ "${SVN_TEST_FAIL_AFTER_COPY:-false}" = true ]; then
	exit 75
fi
exit "${status}"
SH
chmod +x "${SVN_SHIM_DIR}/svnmucc"

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
assert_revision 2
assert_tag_copy "${REPOSITORY}" 2 1.0.8 1
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
assert_revision 2

BUILD_CHANGED="${WORKDIR}/build-changed"
make_candidate "${BUILD_CHANGED}" 1.0.8 1.0.8 'changed release with reused version'
if changed_output="$(deploy --version 1.0.8 --build-dir "${BUILD_CHANGED}" --assets-dir "${ASSETS_V1}" 2>&1)"; then
	fail 'changed candidate reused an existing version'
fi
case "${changed_output}" in
	*'exists in SVN but its immutable tag does not match'*) ;;
	*) fail 'same-version refusal did not report the expected reason' ;;
esac
assert_revision 2

BUILD_V2="${WORKDIR}/build-v2"
make_candidate "${BUILD_V2}" 1.0.9 1.0.9 'upgrade release'
mkdir -p "${BUILD_V2}/vendor/current/deep" "${ASSETS_V2}"
printf '%s\n' 'retained current dependency' > "${BUILD_V2}/vendor/current/deep/Current.php"
printf '%s\n' 'updated screenshot' > "${ASSETS_V2}/screenshot-1.png"

deploy --version 1.0.9 --build-dir "${BUILD_V2}" --assets-dir "${ASSETS_V2}" >/dev/null
assert_revision 4
assert_tag_copy "${REPOSITORY}" 4 1.0.9 3
svn info "file://${REPOSITORY}/trunk/vendor/legacy" >/dev/null 2>&1 && fail 'upgrade retained an obsolete nested directory'
svn info "file://${REPOSITORY}/trunk/vendor/package%20with%20spaces" >/dev/null 2>&1 && fail 'upgrade retained an obsolete path containing spaces'
svn info "file://${REPOSITORY}/assets/stale-screenshot.png" >/dev/null 2>&1 && fail 'upgrade retained an obsolete asset'
svn info "file://${REPOSITORY}/tags/1.0.9/vendor/current/deep/Current.php" >/dev/null 2>&1 || fail 'upgrade tag is missing the candidate dependency'
svn info "file://${REPOSITORY}/tags/1.0.8/vendor/legacy/deep/Legacy.php" >/dev/null 2>&1 || fail 'upgrade changed the prior immutable tag'

old_release_output="$(deploy --version 1.0.8 --build-dir "${BUILD_V1}" --assets-dir "${ASSETS_V1}")"
case "${old_release_output}" in
	*'already deployed exactly'*) ;;
	*) fail 'an exact older tag was not treated as an idempotent release' ;;
esac
assert_revision 4
[ "$(svnlook cat "${REPOSITORY}" trunk/release-marker.txt)" = 'upgrade release' ] || fail 'older release retry rolled back trunk'

mkdir -p "${RECOVERY_IMPORT}/trunk" "${RECOVERY_IMPORT}/tags" "${RECOVERY_IMPORT}/assets"
cp -R "${BUILD_V2}/." "${RECOVERY_IMPORT}/trunk/"
cp -R "${ASSETS_V2}/." "${RECOVERY_IMPORT}/assets/"
svnadmin create "${RECOVERY_REPOSITORY}"
svn import -q "${RECOVERY_IMPORT}" "file://${RECOVERY_REPOSITORY}" -m 'Seed a committed trunk without its release tag'
svn checkout -q "file://${RECOVERY_REPOSITORY}" "${RECOVERY_SEED_COPY}"
svn propset svn:mime-type image/png "${RECOVERY_SEED_COPY}/assets/screenshot-1.png" >/dev/null
svn commit -q "${RECOVERY_SEED_COPY}/assets/screenshot-1.png" -m 'Seed release asset properties'
assert_revision 2 "${RECOVERY_REPOSITORY}"

deploy_to \
	"${RECOVERY_REPOSITORY}" \
	"${RECOVERY_WORKING_COPY}" \
	--version 1.0.9 \
	--build-dir "${BUILD_V2}" \
	--assets-dir "${ASSETS_V2}" >/dev/null
assert_revision 3 "${RECOVERY_REPOSITORY}"
assert_tag_copy "${RECOVERY_REPOSITORY}" 3 1.0.9 2
[ "$(svnlook cat "${RECOVERY_REPOSITORY}" tags/1.0.9/release-marker.txt)" = 'upgrade release' ] || fail 'recovery tag does not contain the committed trunk candidate'

svnadmin create "${TIMEOUT_REPOSITORY}"
timeout_output="$(
	export PATH="${SVN_SHIM_DIR}:${PATH}"
	export REAL_SVN_BIN REAL_SVNMUCC_BIN SVN_TEST_FAIL_AFTER_COPY=true SVN_TAG_RECONCILE_DELAY=0
	deploy_to \
		"${TIMEOUT_REPOSITORY}" \
		"${TIMEOUT_WORKING_COPY}" \
		--version 1.0.8 \
		--build-dir "${BUILD_V1}" \
		--assets-dir "${ASSETS_V1}"
)"
case "${timeout_output}" in
	*'exists and matches the candidate after the SVN copy response failed'*) ;;
	*) fail 'a successful tag copy with a failed client response was not reconciled' ;;
esac
assert_revision 2 "${TIMEOUT_REPOSITORY}"
assert_tag_copy "${TIMEOUT_REPOSITORY}" 2 1.0.8 1

svnadmin create "${COLLISION_REPOSITORY}"
svn import -q "${BUILD_CHANGED}" "file://${COLLISION_REPOSITORY}/collision-source" -m 'Seed different contents for a concurrent tag'
if collision_output="$(
	export PATH="${SVN_SHIM_DIR}:${PATH}"
	export REAL_SVN_BIN REAL_SVNMUCC_BIN SVN_TEST_CREATE_COLLIDING_TAG=true
	export SVN_TEST_COLLISION_SOURCE="file://${COLLISION_REPOSITORY}/collision-source"
	export SVN_TEST_COLLISION_DESTINATION="file://${COLLISION_REPOSITORY}/tags/1.0.8"
	deploy_to \
		"${COLLISION_REPOSITORY}" \
		"${COLLISION_WORKING_COPY}" \
		--version 1.0.8 \
		--build-dir "${BUILD_V1}" \
		--assets-dir "${ASSETS_V1}" 2>&1
)"; then
	fail "a concurrent immutable tag collision was accepted: ${collision_output}"
fi
case "${collision_output}" in
	*'created concurrently with different immutable contents'*) ;;
	*) fail 'a concurrent tag collision did not report the expected reason' ;;
esac
assert_revision 3 "${COLLISION_REPOSITORY}"
[ "$(svnlook cat "${COLLISION_REPOSITORY}" tags/1.0.8/release-marker.txt)" = 'changed release with reused version' ] || fail 'concurrent tag collision did not preserve the immutable winner'

svnadmin create "${MUTATION_REPOSITORY}"
if mutation_output="$(
	export PATH="${SVN_SHIM_DIR}:${PATH}"
	export REAL_SVN_BIN REAL_SVNMUCC_BIN SVN_TEST_MUTATE_TRUNK=true
	export SVN_TEST_REPOSITORY_URL="file://${MUTATION_REPOSITORY}"
	export SVN_TEST_MUTATION_WORKING_COPY="${MUTATION_ACTOR_COPY}"
	export SVN_TEST_MUTATION_SENTINEL="${MUTATION_SENTINEL}"
	deploy_to \
		"${MUTATION_REPOSITORY}" \
		"${MUTATION_WORKING_COPY}" \
		--version 1.0.8 \
		--build-dir "${BUILD_V1}" \
		--assets-dir "${ASSETS_V1}" 2>&1
)"; then
	fail 'a concurrently modified trunk revision was tagged'
fi
case "${mutation_output}" in
	*'committed trunk revision '*' does not match the exact candidate'*) ;;
	*) fail 'a concurrent trunk mutation did not report the expected reason' ;;
esac
assert_revision 2 "${MUTATION_REPOSITORY}"
svn info "file://${MUTATION_REPOSITORY}/tags/1.0.8" >/dev/null 2>&1 && fail 'a concurrent trunk mutation created a release tag'

BUILD_ROLLBACK="${WORKDIR}/build-rollback"
make_candidate "${BUILD_ROLLBACK}" 1.0.7 1.0.7 'accidental rollback'
if rollback_output="$(deploy --version 1.0.7 --build-dir "${BUILD_ROLLBACK}" 2>&1)"; then
	fail 'older candidate without an existing tag was accepted'
fi
case "${rollback_output}" in
	*'refusing rollback from trunk version 1.0.9 to older version 1.0.7'*) ;;
	*) fail 'rollback refusal did not report the expected reason' ;;
esac
assert_revision 4
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
assert_revision 4

BUILD_README_MISMATCH="${WORKDIR}/build-readme-mismatch"
make_candidate "${BUILD_README_MISMATCH}" 2.0.0 2.0.1 'readme mismatch'
if mismatch_output="$(deploy --version 2.0.0 --build-dir "${BUILD_README_MISMATCH}" 2>&1)"; then
	fail 'readme stable tag mismatch was accepted'
fi
case "${mismatch_output}" in
	*'readme stable tag 2.0.1 does not match 2.0.0'*) ;;
	*) fail 'readme stable tag mismatch did not report the expected reason' ;;
esac
assert_revision 4

BUILD_DRY_RUN="${WORKDIR}/build-dry-run"
make_candidate "${BUILD_DRY_RUN}" 1.0.10 1.0.10 'dry-run release'
dry_run_output="$(
	env -u SVN_USERNAME -u SVN_PASSWORD \
		"${REPO_ROOT}/scripts/deploy-wordpress-svn.sh" \
		--slug "${SLUG}" \
		--svn-url "file://${REPOSITORY}" \
		--working-copy "${WORKING_COPY}" \
		--version 1.0.10 \
		--build-dir "${BUILD_DRY_RUN}" \
		--dry-run
)"
case "${dry_run_output}" in
	*'Dry run complete; no SVN commit was made.'*) ;;
	*) fail 'dry run did not report completion' ;;
esac
assert_revision 4
svn info "file://${REPOSITORY}/tags/1.0.10" >/dev/null 2>&1 && fail 'dry run created a repository tag'
[ ! -e "${WORKING_COPY}/tags/1.0.10" ] || fail 'dry run scheduled a local tag copy'
svn status --xml "${WORKING_COPY}" | python3 "${REPO_ROOT}/scripts/svn-status.py" validate --working-copy "${WORKING_COPY}"

if timeout_validation_output="$(SVN_HTTP_TIMEOUT=invalid deploy --version 1.0.10 --build-dir "${BUILD_DRY_RUN}" --dry-run 2>&1)"; then
	fail 'an invalid SVN HTTP timeout was accepted'
fi
case "${timeout_validation_output}" in
	*'SVN_HTTP_TIMEOUT must be a positive number of seconds'*) ;;
	*) fail 'an invalid SVN HTTP timeout did not report the expected reason' ;;
esac

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

printf 'SVN deploy test passed: two-phase publish, recovery, concurrency guards, and dry run are safe.\n'
