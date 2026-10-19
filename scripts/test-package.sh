#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TEST_ROOT="${WP_TEST_ROOT:-/tmp/simcloud-wc-tests}"
PHP_BIN="${PHP_BIN:-php}"
wp_test() { "$PHP_BIN" -d memory_limit=512M "$TEST_ROOT/wp-cli.phar" --path="$TEST_ROOT/wordpress" "$@"; }
PLUGIN_PATH="$TEST_ROOT/wordpress/wp-content/plugins/simcloud-woocommerce"
VERSION="$(wp_test plugin get simcloud-woocommerce --field=version)"
# Remove only the test symlink, never its source checkout or an arbitrary installation.
if [ ! -L "$PLUGIN_PATH" ]; then
    echo 'Package smoke test requires the isolated setup symlink. Refusing to replace a real directory.'
    exit 1
fi
wp_test plugin deactivate simcloud-woocommerce
unlink "$PLUGIN_PATH"
wp_test plugin install "$ROOT/dist/simcloud-woocommerce-$VERSION.zip" --activate
if [ "$(wp_test eval 'echo SIMCLOUD_WC_VERSION;')" != "$VERSION" ]; then
    echo 'Installed ZIP version does not match the source plugin.'
    exit 1
fi
wp_test eval 'if (!defined("SIMCLOUD_WC_TEST_RUN") || !str_starts_with(DB_NAME, "simcloud_wc_test")) { throw new RuntimeException("Isolated database required"); } if (simcloud_wc_live_ready() || !is_file(SIMCLOUD_WC_DIR . "readme.txt")) { throw new RuntimeException("Packaged plugin activation failed"); } echo "PASS: Exact release ZIP installed and activated with submissions paused.\n";'
bash "$ROOT/scripts/test.sh"
