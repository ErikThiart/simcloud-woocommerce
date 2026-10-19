# Screenshot provenance and refresh instructions

The illustrated installation, product guide and gallery use actual application screenshots captured on **19 October 2026**. They are not interface mockups.

| Item | Capture context |
| --- | --- |
| Plugin | Released `simcloud-woocommerce-0.1.0.zip`, v0.1.0 prerelease |
| Package SHA-256 | `71990eb53dd0542c4510ce44b721fa364ad5a6043b55e723b76088a3733cfba5` |
| WordPress / WooCommerce | 7.1.3 / 11.2.1, local individual-site installation |
| PHP / database | PHP 8.4, isolated local MariaDB database |
| Installation | Actual WordPress upload, install and activation using the release ZIP |
| Provider transport | `tests/mock-provider.php` under the explicit isolated-test configuration |
| Balance / catalogues | Synthetic R2000 balance, data product `7`, VAS product `12` |
| Products | Four products saved through the WooCommerce product editor |
| Checkout / payment | Checkout Blocks with core BACS; synthetic billing and explicit staff confirmation |
| Final delivery | Intercepted provider responses; non-redeemable test voucher/token |

The local capture environment excluded its synthetic gateway from storefront checkout so the examples could use WooCommerce's native BACS flow. The `simcloud_test` ID shown in settings is test infrastructure and is not shipped as a payment gateway. Final mocked delivery jobs were advanced locally for capture; the production background runner was not exercised against a live supplier.

## Image groups

- `installation/01…08`: release asset selection, WordPress upload, install, activation, settings, connection, catalogue refresh and enablement.
- `products/`: saved airtime, data, VAS and electricity product settings.
- `screenshots/`: customer forms, Checkout Blocks, awaiting payment, staff action and final order delivery.
- Root-level `merchant-settings.png`, `vas-delivery.png` and `electricity-delivery.png`: earlier v0.1.0 verification captures retained as evidence in the verification document.
- `../branding/`: code-native SVG banner and its raster render; artwork rather than an application screenshot.

## Refresh checklist

Use an isolated local database with intercepted provider transport and synthetic customers. Install the actual release ZIP and record versions and checksum. Capture real saved states, give images descriptive filenames and crop only to improve legibility without hiding relevant context. Do not edit screenshots to imply a different application result.

Remove credentials, real customer details, order-key links, redeemable codes, browser profile data and production logs before capture. Keep demonstration labels in the guide text and fake codes visibly non-redeemable. Inspect images at GitHub reading width, update alt text and captions, run the documentation checker and review the rendered guides.

[← Screenshot gallery](../screenshots.md) · [Repository maintenance](../repository-guide.md)
