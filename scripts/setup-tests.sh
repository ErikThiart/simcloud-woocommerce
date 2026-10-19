#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TEST_ROOT="${WP_TEST_ROOT:-/tmp/simcloud-wc-tests}"
PHP_BIN="${PHP_BIN:-php}"
DB_NAME="${WP_TEST_DB:-simcloud_wc_test}"
DB_HOST="${WP_TEST_DB_HOST:-127.0.0.1:3306}"
DB_USER="${WP_TEST_DB_USER:-root}"
DB_PASS="${WP_TEST_DB_PASS:-}"
WP_VERSION="${WP_VERSION:-7.1.3}"
WC_VERSION="${WC_VERSION:-11.2.1}"
case "$DB_NAME" in simcloud_wc_test*) ;; *) echo 'Test database name must start with simcloud_wc_test'; exit 1 ;; esac
mkdir -p "$TEST_ROOT"
if [ ! -f "$TEST_ROOT/wp-cli.phar" ]; then
    curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$TEST_ROOT/wp-cli.phar"
fi
wp_test() { "$PHP_BIN" -d memory_limit=512M "$TEST_ROOT/wp-cli.phar" --path="$TEST_ROOT/wordpress" "$@"; }
if [ ! -f "$TEST_ROOT/wordpress/wp-load.php" ]; then
    wp_test core download --version="$WP_VERSION"
fi
if [ ! -f "$TEST_ROOT/wordpress/wp-config.php" ]; then
    wp_test config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --skip-check
    wp_test db create
fi
if ! wp_test core is-installed; then
    wp_test core install --url=http://127.0.0.1:8089 --title='SIMcloud isolated tests' --admin_user=testadmin --admin_password=local-test-only-8291 --admin_email=test@example.invalid --skip-email
fi
wp_test option update home http://127.0.0.1:8089
wp_test option update siteurl http://127.0.0.1:8089
wp_test config set SIMCLOUD_WC_TEST_RUN true --raw
wp_test config set WP_DEBUG true --raw
wp_test config set WP_DEBUG_LOG true --raw
wp_test config set WP_DEBUG_DISPLAY false --raw
if ! wp_test plugin is-installed woocommerce; then
    wp_test plugin install woocommerce --version="$WC_VERSION" --activate
elif [ "$(wp_test plugin get woocommerce --field=version)" != "$WC_VERSION" ]; then
    echo 'Use a fresh WP_TEST_ROOT/database when changing WooCommerce versions.'
    exit 1
fi
if [ ! -e "$TEST_ROOT/wordpress/wp-content/plugins/simcloud-woocommerce" ]; then
    ln -s "$ROOT" "$TEST_ROOT/wordpress/wp-content/plugins/simcloud-woocommerce"
fi
mkdir -p "$TEST_ROOT/wordpress/wp-content/mu-plugins"
if [ ! -e "$TEST_ROOT/wordpress/wp-content/mu-plugins/simcloud-test.php" ]; then
    ln -s "$ROOT/tests/mock-provider.php" "$TEST_ROOT/wordpress/wp-content/mu-plugins/simcloud-test.php"
fi
wp_test plugin activate simcloud-woocommerce
CURRENT_HPOS="$(wp_test option get woocommerce_custom_orders_table_enabled 2>/dev/null || true)"
if [ "$CURRENT_HPOS" != "${HPOS:-yes}" ]; then
    ORDER_COUNT="$(wp_test eval 'echo count(wc_get_orders(["limit" => 1, "return" => "ids"]));')"
    if [ "$ORDER_COUNT" != '0' ]; then
        echo 'Use a fresh WP_TEST_ROOT/database when changing order storage with existing orders.'
        exit 1
    fi
    wp_test option update woocommerce_custom_orders_table_enabled "${HPOS:-yes}"
fi
wp_test rewrite structure '/%postname%/'
echo "Isolated test installation ready at $TEST_ROOT/wordpress"
