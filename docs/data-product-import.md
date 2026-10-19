# Automatically import WooCommerce data bundles and VAS vouchers

Version **0.3.0 (prerelease)** uses SIMcloud's catalogue APIs to populate products with default brand logos. New stores should follow [one-click quick setup](quick-start.md). Existing stores can click **WooCommerce → SIMcloud → Refresh catalogues / import products**.

## What gets created?

| Field | Data bundle | VAS voucher |
| --- | --- | --- |
| Product | One per available unambiguous data product ID | One per product ID and available denomination |
| Name | Network and API description | Voucher type and denomination |
| Description | API description and product group | API voucher type |
| Type | Simple, virtual, non-downloadable | Simple, virtual, non-downloadable |
| State | Published | Published |
| Initial regular retail price | Exact API amount, in ZAR | Exact API denomination, in ZAR |
| Provider configuration | Data, network, amount and product ID | VAS, amount and product ID |
| Initial SKU | `simcloud-data-{id}` | `simcloud-vas-{id}-{amount-in-cents}` |
| Default image | Bundled mobile-network logo | Matching bundled VAS brand logo |

The API calls are `GET /api/data.php?products=1` and `GET /api/vas.php`. Quick setup also performs a balance GET. Import does not create a prepaid purchase or reserve wallet funds.

The provider amount and retail price remain separate fields, even when initially equal. Merchant retail changes, coupons and WooCommerce tax settings do not change the provider denomination. Review pricing and tax configuration before live sales.

## Repeat imports preserve merchant work

The importer recognizes previous imports and manually configured products with the same provider identity. For VAS, the amount is part of that identity. Existing prices, names, descriptions, SKUs and publication states are preserved. Drafts remain drafts; trashed products are not restored. Original import markers prevent recreation after a merchant removes the prepaid mapping.

Existing custom images are preserved. A matching product with no image receives its bundled default; one Media Library attachment is reused per brand. Imported images remain ordinary WordPress media after plugin updates or removal, so product imagery does not depend on an installed plugin URL.

A refresh creates missing products and fills empty images. It does not schedule a catalogue mirror or automatically reprice, unpublish or delete existing products when the API changes. Checkout/submission still validate current availability; paid orders continue to use their saved snapshots.

## Skips, failures and image warnings

The data-order API selects by **network plus amount**, so indistinguishable catalogue entries are skipped. Duplicate IDs, unsupported networks, invalid amounts, missing descriptions and unavailable VAS denominations are also skipped. Repeated denominations within one VAS product are deduplicated.

Concurrent imports are rejected while the database lock is held. Save failures are counted and logged under **WooCommerce → Status → Logs → `simcloud-woocommerce`**, including provider IDs and controlled error codes. A conflicting SKU does not authorize overwriting another product. Resolve the cause and refresh again.

Image warnings identify unknown brands or Media Library write/processing failures. The plugin uses only its bundled files, selected by exact network/brand mappings or a matching known SIMcloud `logo_url` filename. It does not fetch arbitrary image URLs. Existing products remain intact if an image cannot be added; repair the upload issue or set a suitable custom product image.

An empty or failed catalogue read never deletes products. Both services report their own results, so a failed catalogue is visible even when the other service succeeds.

## Screenshots and asset sources

See [one-click setup screenshots](quick-start.md), [asset provenance](../assets/images/NOTICE.md), [source URLs and checksums](../assets/images/sources.json), and the [four-service product guide](product-setup.md).

[← Documentation index](README.md) · [Installation](installation.md) · [Troubleshooting](troubleshooting.md)
