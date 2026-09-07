#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="${SOURCE_DIR:-$ROOT_DIR}"
cd "$SOURCE_DIR"

find plugin-dir tests -type f -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n 1 php -l
# WPCS 2.x emits PHP deprecations; its upgrade is tracked separately.
php -d 'error_reporting=E_ALL & ~E_DEPRECATED' plugin-dir/vendor/bin/phpcs --standard=phpcs.xml --parallel=1

for test in tests/*.php; do
	# This fixture needs an explicitly bootstrapped, isolated WordPress database.
	[ "$test" != tests/wp-integration.php ] || continue
	printf '\nRunning %s\n' "$test"
	php "$test"
done
