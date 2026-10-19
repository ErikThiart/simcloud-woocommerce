# SIMcloud WooCommerce plugin FAQ

Answers for store owners evaluating **v0.2.0 (prerelease)**. Start with the [illustrated installation guide](installation.md) or [screenshot gallery](screenshots.md).

## Is the plugin free and open source?

Yes. The code is licensed under [GPL-2.0-or-later](../LICENSE). The license does not provide prepaid value, SIMcloud account services, gateway processing or a support contract. Your SIMcloud wallet must have funds for purchases.

## Do I need WordPress and WooCommerce?

Yes. This is a WooCommerce integration running inside WordPress. WooCommerce supplies products, checkout, orders and payment integrations. It is not a standalone shop or payment gateway. See [requirements](installation.md#before-you-start).

## Which services can I sell?

South African airtime, data bundles, VAS vouchers and prepaid electricity through the SIMcloud API. VAS means value-added services; available voucher products and denominations come from your current account catalogue. This release does not claim worldwide airtime coverage. [Configure each service](product-setup.md).

## Does it work with Checkout Blocks and HPOS?

It implements classic checkout, Checkout Blocks/Store API and HPOS-compatible order access. The [verification matrix](verification.md) records the exact combinations checked. A supported integration is not a guarantee for every theme, checkout extension or gateway; include those in store acceptance testing.

## Which payment gateways work?

A gateway must authenticate its payment result and call WooCommerce's `payment_complete`. Add its actual gateway ID to the allowed list in SIMcloud settings. The list of installed IDs is a configuration aid, not a tested gateway compatibility list. Test your own integration before taking live orders.

BACS, cheque and COD are excluded from automatic authorization. Staff must confirm actually received funds using the explicit order action. The `simcloud_test` gateway in documentation screenshots is local test infrastructure, not a customer payment method shipped with the plugin.

## Does a customer payment fund my SIMcloud wallet?

No. Customer funds settle through your payment gateway. Provider purchases use your separately funded SIMcloud account. The connection check shows a last known balance; it does not reserve funds.

## Can the product-list API populate data bundles automatically?

Yes. Click **Refresh catalogues / import data** in WooCommerce → SIMcloud. Missing unambiguous data bundles are published automatically with API details configured and the API amount as the regular retail price. Existing prices and merchant edits are preserved. See [automatic data-product import](data-product-import.md).

## Can I set my own prices?

Yes. WooCommerce retail prices, taxes and coupons are separate from the provider amount. A discount does not change the prepaid denomination. Electricity's provider amount includes the SIMcloud fee and does not promise an equal value in electricity units. Confirm commercial and tax treatment for your store.

## Where are voucher codes and electricity tokens delivered?

In the authorized WooCommerce order details after confirmed delivery. Emails direct customers to that page without embedding redeemable codes. Guest order-key links are private access credentials. [See delivery screenshots](screenshots.md#electricity-token-delivery).

## Can I sell physical goods and prepaid products together?

The initial release requires prepaid-only baskets, simple virtual products and quantity one per recipient line. Mixed prepaid/other baskets, variable products, subscriptions, bulk order imports, customer wallets, multi-currency and network-wide activation are outside this release's scope.

## Does refunding an order cancel the recharge?

No. A WooCommerce refund does not reverse prepaid delivery or cancel an uncertain provider request. Reconcile the original purchase before deciding on customer resolution. [Read the recovery procedure](order-lifecycle.md).

## Why is there no automatic retry or resubmit button?

A create request can be accepted even when its response is lost. The current API contracts do not universally guarantee durable idempotent creation or lookup by merchant reference. Automatically submitting again could buy twice. Known provider IDs can be refreshed with read-only checks; unidentified outcomes need private SIMcloud reconciliation.

## Is there a sandbox or a proven live supplier test?

The project does not provide a SIMcloud sandbox or test wallet. Its automated and documented browser tests use intercepted HTTP responses with synthetic data. No live paid supplier transaction has been verified by the project. Arrange controlled live acceptance with your own funded account before production sales. [Review evidence and limits](verification.md).

## Can I install it from WordPress.org or get automatic updates?

This release is distributed through [GitHub Releases](https://github.com/ErikThiart/simcloud-woocommerce/releases). No WordPress.org listing or automatic directory update channel is claimed. Use the named installable ZIP and follow the [update procedure](installation.md#updates-and-removal).

## Where should I ask for help?

Use [troubleshooting](troubleshooting.md) first. GitHub Issues are for reproducible plugin bugs, documentation corrections and feature proposals. Private account, wallet, payment and transaction details belong with the store or SIMcloud support as appropriate. [Read support boundaries](../SUPPORT.md).

[← Documentation index](README.md)
