# Sources and verification

Research date: 19 October 2026. Sources may change; recheck them before implementation and release.

## SIMcloud

- [API home](https://simcloud.co.za/api/)
- [Machine-readable OpenAPI contract](https://simcloud.co.za/api/openapi.yaml)
- [Airtime documentation](https://simcloud.co.za/api/airtime.html)
- [Data documentation](https://simcloud.co.za/api/data.html)
- [AI/integration guide](https://simcloud.co.za/api/ai.html)

The OpenAPI, airtime, and data pages returned HTTP 200 during planning. The live OpenAPI bytes matched the local contract inspected for this review. Handler review established the queue acknowledgement/status distinction, current network codes, denomination validation, catalogue selection fields, last-known balance behaviour, and airtime/data lookup limitations. Public documentation in this project was written independently; no private application source, configuration, database dump, customer record, or credential was copied into the repository.

The review used read-only documentation retrieval and local source inspection. It did not authenticate to a merchant account, query private balances, verify paid delivery, or establish a sandbox. The [API contract notes](api-contract.md) distinguish documented fields from implementation observations.

## WordPress and WooCommerce

- [WordPress detailed plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) — licensing, external commercial services, privacy, readable code, branding, and directory submission.
- [WooCommerce HPOS extension recipe book](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/) — order CRUD APIs and storage compatibility.
- [Cart and Checkout extensibility](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/) — dedicated Blocks extension surfaces.
- [Action Scheduler](https://actionscheduler.org/) — persistent background processing and supported operation.
- [GNU GPL version 2 text](https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt) — project license text.

The design uses these sources as guidance. It does not claim WordPress.org approval, WooCommerce Marketplace certification, compatibility test results, or a completed security review.
