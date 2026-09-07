#!/usr/bin/env bash
set -Eeuo pipefail

tag="${RELEASE_TAG:-}"
mode="${RELEASE_MODE:-verify}"

case "$mode" in
	verify|dry-run|publish) ;;
	*) printf 'Unknown release mode: %s\n' "$mode" >&2; exit 1 ;;
esac

if [ -z "$tag" ]; then
	if [ "$mode" != verify ] || [ "${GITHUB_EVENT_NAME:-}" = release ]; then
		printf 'An existing GitHub release tag is required.\n' >&2
		exit 1
	fi
	exit 0
fi

version="$(printf '%s' "$tag" | sed -E 's/^(v|version)-?//')"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]] || {
	printf 'Invalid release tag: %s\n' "$tag" >&2
	exit 1
}

release="$(gh release view "$tag" --json tagName,isDraft,isPrerelease)"
[ "$(jq -r '.tagName' <<< "$release")" = "$tag" ] || exit 1
[ "$(jq -r '.isDraft' <<< "$release")" = false ] || {
	printf 'Draft releases cannot be published.\n' >&2
	exit 1
}
prerelease="$(jq -r '.isPrerelease' <<< "$release")"
[[ "$prerelease" = true || "$prerelease" = false ]] || exit 1
if [ "$mode" = publish ] && [ "$prerelease" != false ]; then
	printf 'Mark the GitHub release as stable before publishing to WordPress.org.\n' >&2
	exit 1
fi
if [ -n "${EXPECTED_PRERELEASE:-}" ] && [ "$prerelease" != "$EXPECTED_PRERELEASE" ]; then
	printf 'GitHub release state changed after verification; run the workflow again.\n' >&2
	exit 1
fi

{
	printf 'tag=%s\n' "$tag"
	printf 'version=%s\n' "$version"
	printf 'prerelease=%s\n' "$prerelease"
	printf 'ref=refs/tags/%s\n' "$tag"
} >> "${GITHUB_OUTPUT:?GITHUB_OUTPUT is required}"
