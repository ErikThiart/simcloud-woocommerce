# Verification evidence — version 0.1.0

Review date: 19 October 2026. This file records evidence without claiming unperformed provider tests.

## Local integration

WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.4.17 and MariaDB 13.0.2 were used in a dedicated local test installation. The four-service integration suite passed 60 assertions, including actual classic checkout and Checkout Blocks Store API order creation. Two separate WordPress/PHP processes produced exactly one atomic submission-claim winner. Interrupted-attempt and deactivation/reactivation/uninstall checks also passed.

Tests used intercepted HTTP responses and synthetic payment collection. They exercised real WooCommerce products, orders, sessions, API routes, database constraints and job registration. No live merchant token or paid recharge was used.

## Additional evidence

The release workflow adds PHP 8.2/8.4/8.5, legacy/HPOS storage and minimum/current WordPress/WooCommerce combinations. See the exact [CI runs](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/plugin.yml) for their results. Browser, package-install and static-check results are recorded as release validation completes.

## Limits

This is not an independent security audit, a certification, a complete gateway compatibility matrix, or proof of live SIMcloud/supplier delivery. Merchant-owned controlled acceptance remains required before production sales. WordPress.org submission and current applicable service terms/privacy review are separate release/distribution steps.
