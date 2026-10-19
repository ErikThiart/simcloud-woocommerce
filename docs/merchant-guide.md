# Merchant guide

**This is a preview of the intended merchant workflow. The plugin has not been implemented and cannot be installed yet.** Final menu names, screenshots, installation commands, and supported versions will be verified against the first release.

## Before you start

You will need a WordPress site with WooCommerce, HTTPS, a tested payment gateway, a working background-job runner, and your own SIMcloud account with API access and wallet funds. The initial product scope is ZAR sales of South African airtime and data.

Keep customer payment collection and SIMcloud wallet funding in your operating process. They are separate balances. Publish clear product descriptions, recipient-confirmation instructions, support details, delivery expectations, and refund terms before selling.

## Planned setup

1. Install a tagged release ZIP from this repository after one is published. A GitHub source archive from the planning stage is not an installable plugin.
2. Open the plugin's WooCommerce settings and configure the API token from your SIMcloud account's **API → Get Token** screen.
3. Use the connection check to confirm authentication and display the last known balance with its timestamp. This check will not place an order.
4. Refresh the data catalogue if selling bundles.
5. Create eligible virtual simple products and choose service, network, and airtime denomination or catalogue bundle. Set the customer retail price separately.
6. Review payment-method eligibility, cron health, privacy disclosures, retention, and staff permissions.
7. Exercise checkout and error handling on staging with mocked transport. Disable live submissions on staging and on site clones.
8. Arrange a controlled live acceptance test with the project maintainer and a confirmed test recipient before opening sales.

## Products and pricing

Start with a small catalogue, such as a configured R10 airtime product and a few verified data bundles. The retail price is your storefront price. The provider amount selects the airtime denomination or bundle; it is not recalculated from the customer's discounted total.

Use a separate cart line for each recipient and quantity one. The first release will reject physical/prepaid mixed baskets and non-ZAR purchases. Do not advertise vouchers, electricity, or SMS support until a release explicitly includes them.

## Daily operation

Monitor wallet funds, failed jobs, authentication errors, and orders needing review. A successful payment can still be followed by a failed or delayed recharge. Check outstanding provider IDs before taking any action that could send another recharge.

The current API does not provide a merchant-reference lookup for airtime/data. If a submission times out without returning its request ID, use the saved correlation details in a private support conversation. Never “fix” it by making the same purchase again without reconciliation.

## Troubleshooting

| Symptom | First checks |
| --- | --- |
| Cannot connect | Token validity, outbound HTTPS, authentication failure details; never share the token |
| Payment received but recharge not submitted | Payment eligibility, allowed gateway, cancellation/refund state, job runner, fulfilment record |
| Queued for a long time | Saved request ID, last provider status, next scheduled check, SIMcloud support |
| Insufficient balance | Fund the merchant wallet; review the failed order before any new attempt |
| Bundle unavailable | Refresh catalogue and verify mapping; do not substitute another bundle |
| Duplicate conflict | Reconcile the possible earlier request; do not wait and blindly submit again |
| Refund requested | Determine whether delivery occurred; follow the store's policy and preserve the provider evidence |

## Backups, staging, and removal

Disable fulfilment before copying or restoring a site. A restored queue can replay old jobs against a real wallet. Changing the site URL alone is not adequate protection.

Deactivation is intended to stop plugin jobs and new submissions while preserving orders and evidence. Uninstall must not silently delete WooCommerce orders or financial records. Final cleanup controls will be documented and tested before release.

For plugin bugs, see [support](../SUPPORT.md). For transaction, billing, or account questions, use the SIMcloud support channel available through your account; do not post customer details in public issues.
