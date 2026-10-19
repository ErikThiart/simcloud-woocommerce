# Verification evidence

Review date: 19 October 2026. This file records evidence without claiming unperformed provider tests.

## Version 0.3.0 — one-click setup and local default logos

The suites passed **29 quick-setup/media assertions**, **22 data-import assertions** and the existing **76 fulfilment assertions**, plus concurrency/lifecycle checks against both the source and installed v0.3.0 ZIP, on PHP 8.4.17 / WordPress 7.1.3 / WooCommerce 11.2.1 / MariaDB 13.0.2. The quick-setup suite covers VAS denominations, shared brand attachments, custom-image preservation, exact aliases, unknown URL rejection, upload/metadata failures, permissions, invalid tokens, connection failures, both catalogue imports and zero paid POST requests. The [CI matrix](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/plugin.yml) runs source and installed-ZIP suites on PHP 8.2/8.4/8.5.

Security-category WordPress Plugin Check on the installed v0.3.0 runtime returned **zero errors and 18 warnings** for deliberate uncached ledger/import-lock queries. This is not an independent audit.

All **23 original logo JPEGs** were downloaded successfully from public SIMcloud image URLs; [the manifest](../assets/images/sources.json) records byte counts, sources and SHA-256 checksums. Packaging verifies this complete asset set. This confirms public image retrieval, not authenticated provider catalogue access or supplier acceptance.

A fresh local WordPress installation used the packaged v0.3.0 plugin. Entering the synthetic token and clicking **Connect & set up products** created **four data products and six VAS products**, all with default logos, using **seven shared attachments**. A repeat setup added zero products and zero images. All ten storefront images loaded from local uploads, and the tested 390-pixel viewport had no horizontal overflow. Live submissions remained unchecked. A settings POST without the nonce was rejected with HTTP 403. [Quick-start screenshots](quick-start.md) record the workflow.

The catalogues and wallet responses in these tests were intercepted fixtures. No authenticated live catalogue or paid supplier acceptance is claimed.

## Version 0.2.0 — automatic data-product import

The real-database data-import suite passed **22 assertions**, alongside the **76-assertion** fulfilment suite, concurrency and lifecycle checks, against both source and the installed v0.2.0 ZIP on PHP 8.4.17 / WordPress 7.1.3 / WooCommerce 11.2.1 / MariaDB 13.0.2.

The data-import suite checks publication using the API amount as the regular retail price, exact provider configuration, manual/imported product matching, preserved merchant edits/prices/drafts/trash, invalid/ambiguous rows, permissions, currency, concurrent database locks, save failures and zero prepaid POST requests. It runs alongside the existing 76-assertion four-service integration suite, concurrent-worker and lifecycle checks against source and the exact installable ZIP. The [CI workflow](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/plugin.yml) runs both suites across the three version combinations below.

WordPress Plugin Check security-category checks on the exact v0.2.0 runtime returned **zero errors and 18 warnings**. The warnings concern uncached direct ledger queries and the prepared connection-scoped import-lock queries; caching a lock or atomic claim would be incorrect. This is not an independent audit.

Browser verification installed the v0.2.0 ZIP and clicked the actual settings import button. Two synthetic products were published automatically at R49 and R35; a manually configured product retained its R27 retail price. A second refresh created zero duplicates and preserved all three data products. The product editor confirmed the imported service, network, provider amount and catalogue ID. See [screenshots and instructions](data-product-import.md) and [capture provenance](images/README.md). No authenticated live catalogue or paid supplier transaction was performed; the connected catalogue read was unavailable because no bearer token was configured.

## Version 0.1.0 — baseline integration evidence

### Local integration

WordPress 7.1.3, WooCommerce 11.2.1, PHP 8.4.17 and MariaDB 13.0.2 were used in a dedicated local test installation. The four-service integration suite passed 76 assertions, including actual classic checkout and Checkout Blocks Store API order creation. Two separate WordPress/PHP processes produced exactly one atomic submission-claim winner. Interrupted-attempt and deactivation/reactivation/uninstall checks also passed.

Tests used intercepted HTTP responses and synthetic payment collection. They exercised real WooCommerce products, orders, sessions, API routes, database constraints and job registration. No live merchant token or paid recharge was used.

### Additional evidence

The [three-job CI matrix passed](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/plugin.yml) for PHP 8.2/8.4/8.5, legacy/HPOS storage and minimum/current WordPress/WooCommerce combinations. Every job passed both the source-checkout tests and installation/testing of the exact release ZIP.

| PHP | WordPress | WooCommerce | MariaDB | Storage | Source and ZIP suite |
| --- | --- | --- | --- | --- | --- |
| 8.2.34 | 6.8.3 | 10.0.4 | 11.4.13 | Legacy | 76 assertions each, plus concurrency/lifecycle |
| 8.4.26 | 7.1.3 | 11.2.1 | 11.4.13 | HPOS | 76 assertions each, plus concurrency/lifecycle |
| 8.5.11 | 7.1.3 | 11.2.1 | 11.4.13 | HPOS | 76 assertions each, plus concurrency/lifecycle |

The exact runtime ZIP was installed through WordPress and passed the same 76-assertion suite plus concurrent-worker and lifecycle checks.

### Browser validation

A browser completed a classic-checkout VAS purchase using synthetic payment, and a Checkout Blocks electricity purchase using the core BACS gateway followed by explicit staff funds confirmation. Authorized order views displayed the expected non-redeemable test voucher and electricity token/units. A 390-pixel mobile viewport showed no horizontal overflow on the tested order view.

A data product was also saved successfully through the actual WooCommerce product editor with its catalogue mapping preserved. Merchant settings were exercised through WordPress admin: token save/masking, excluded offline gateway filtering, live enablement, balance diagnostics and both catalogue refreshes. These tests used the same intercepted transport and made no paid requests.

![Synthetic merchant settings](images/merchant-settings.png)

![Synthetic VAS delivery](images/vas-delivery.png)

![Synthetic electricity delivery](images/electricity-delivery.png)

### Illustrated documentation capture

A fresh local WordPress installation used the released ZIP through the actual Upload Plugin interface. Installation, activation, connection/balance, catalogue refresh and four saved product settings were captured. Checkout Blocks with core BACS produced the customer examples; staff confirmation and intercepted provider responses produced final VAS/electricity delivery views. These captures are demonstrations with synthetic data, not additional live supplier acceptance. See the [gallery](screenshots.md) and [capture provenance](images/README.md).

### Static security checks

WordPress Plugin Check 2.1.0 security-category checks against the packaged runtime returned **zero errors** and 14 warnings. All warnings concern direct database access and lack of caching in the plugin-owned ledger/watchdog. These queries are deliberate: state claims and recovery require fresh database state, use prepared parameters/identifiers and do not access WooCommerce order tables directly. This check is not an independent security audit.

### Limits

This is not an independent security audit, a certification, a complete gateway compatibility matrix, or proof of live SIMcloud/supplier delivery. Merchant-owned controlled acceptance remains required before production sales. WordPress.org submission and current applicable service terms/privacy review are separate release/distribution steps.
