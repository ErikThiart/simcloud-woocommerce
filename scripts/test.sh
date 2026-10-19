#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TEST_ROOT="${WP_TEST_ROOT:-/tmp/simcloud-wc-tests}"
PHP_BIN="${PHP_BIN:-php}"
wp_test() { "$PHP_BIN" -d memory_limit=512M "$TEST_ROOT/wp-cli.phar" --path="$TEST_ROOT/wordpress" "$@"; }
find "$ROOT/includes" "$ROOT/tests" -name '*.php' -print0 | xargs -0 -n 1 "$PHP_BIN" -l
"$PHP_BIN" -l "$ROOT/simcloud-woocommerce.php"
"$PHP_BIN" -l "$ROOT/uninstall.php"
wp_test eval-file "$ROOT/tests/data-import.php"
wp_test eval-file "$ROOT/tests/integration.php"
START="$("$PHP_BIN" -r 'echo microtime(true) + 3;')"
wp_test eval-file "$ROOT/tests/claim.php" "$START" > "$TEST_ROOT/claim-a.txt" &
WORKER_A=$!
wp_test eval-file "$ROOT/tests/claim.php" "$START" > "$TEST_ROOT/claim-b.txt" &
WORKER_B=$!
wait "$WORKER_A"
wait "$WORKER_B"
CLAIM_A="$(cat "$TEST_ROOT/claim-a.txt")"
CLAIM_B="$(cat "$TEST_ROOT/claim-b.txt")"
if [ "$CLAIM_A$CLAIM_B" != '10' ] && [ "$CLAIM_A$CLAIM_B" != '01' ]; then
    echo 'FAIL: Concurrent workers did not produce exactly one atomic winner.'
    exit 1
fi
echo 'PASS: Two separate WordPress/PHP processes produce exactly one atomic submission claim.'
wp_test eval-file "$ROOT/tests/lifecycle.php"
python3 "$ROOT/scripts/check_docs.py"
