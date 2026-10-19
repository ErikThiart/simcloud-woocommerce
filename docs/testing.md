# Testing

Tests run in actual WordPress and WooCommerce against MySQL/MariaDB. Only SIMcloud HTTP transport and payment collection are synthetic. No live token or prepaid spend is required. See [verification](verification.md) for recorded results and limits.

## Isolated installation

Requirements: PHP 8.2+ with mysqli, curl, mbstring, XML and ZIP, Python 3.10+, curl, and a local MySQL/MariaDB server with a user allowed to create a dedicated database. The setup downloads official WordPress, WooCommerce and WP-CLI packages. Never point these scripts at a production database.

```sh
export WP_TEST_ROOT=/tmp/simcloud-wc-tests
export WP_TEST_DB=simcloud_wc_test
export WP_TEST_DB_HOST=127.0.0.1:3306
export WP_TEST_DB_USER=root
# Set WP_TEST_DB_PASS locally if your test server requires it.
bash scripts/setup-tests.sh
bash scripts/test.sh
```

Use `PHP_BIN=/absolute/path/to/php` when multiple interpreters exist. Optional `WP_VERSION`, `WC_VERSION` and `HPOS=yes|no` select the matrix. Defaults are WordPress 7.1.3, WooCommerce 11.2.1 and HPOS enabled.

The database name must begin with `simcloud_wc_test`; a separate `SIMCLOUD_WC_TEST_RUN` constant enables fixtures. Setup installs the mock as a must-use plugin in the isolated site. It intercepts SIMcloud traffic and blocks other WordPress outbound HTTP and email. The test token and all voucher/token codes are deliberately nonfunctional synthetic values. Tests create products/orders and must be run only in this isolated database.

## Automated checks

`test.sh` lints runtime/test PHP, executes the integration assertions, launches two separate PHP/WordPress processes against one purchase to verify an atomic claim, verifies interrupted-attempt recovery and deactivation/reactivation/uninstall, then checks documentation links.

The integration suite covers all four service contracts, exact cents, denomination validation, network/meter checks, confirmed payment, duplicate hooks/jobs, immutable amounts, final delivery, VAS/electricity code retention, guest authorization, timeouts, invalid acknowledgements, duplicate conflicts, mismatched responses, provider failure, unavailable status, authentication pause, copied-site binding, classic checkout, and actual Checkout Blocks Store API add-item/checkout routes.

Warnings/deprecations from exercised plugin paths become test failures under `E_ALL`. A mock is not evidence of live provider acceptance. Concurrent claim tests use the real database rather than a sequential in-memory imitation.

## CI matrix

| PHP | WordPress | WooCommerce | Order storage |
| --- | --- | --- | --- |
| 8.2 | 6.8.3 | 10.0.4 | Legacy |
| 8.4 | 7.1.3 | 11.2.1 | HPOS |
| 8.5 | 7.1.3 | 11.2.1 | HPOS |

GitHub Actions supplies MariaDB 11.4, runs the suite and builds the installable ZIP. Review the actual [workflow runs](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/plugin.yml) before claiming a result. Each matrix row is tested independently. The documentation workflow covers local Markdown targets and whitespace; it does not test plugin behaviour or remote URLs.

## Browser testing

After the suite, re-enable the synthetic test site with `wp eval-file tests/browser-setup.php` using its WordPress path. Start the local server from the repository:

```sh
php -d memory_limit=512M -S 127.0.0.1:8089 \
  -t /tmp/simcloud-wc-tests/wordpress tests/router.php
```

Open the printed product URLs/IDs and checkout page. Verify recipient/meter labels, archive links, cart data, checkout and authorized code display on desktop and mobile widths. The mock gateway supports server-side checkout tests; the core BACS method provides a browser-visible Blocks option for testing explicit staff confirmation. Browser fixtures and router are excluded from release packages.

## Live acceptance boundary

Before live sales, use a merchant-owned account and confirm the final recipient, network, denomination/bundle, meter/product and reference for each controlled service test. Real POST requests can deliver irreversible value and spend wallet funds. Keep tokens and customer data out of CI and public reports.

Record API acknowledgement, saved IDs, matching terminal query, voucher/token output where applicable, and wallet reconciliation. A `201`, payment success, green CI or working mocked checkout is not proof of live supplier delivery. No live paid provider test is claimed by the initial automated suite.
