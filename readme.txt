=== SIMcloud for WooCommerce ===
Contributors: erikthiart
Tags: woocommerce, airtime, prepaid, vouchers, electricity
Requires at least: 6.8.3
Tested up to: 7.1.3
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell South African airtime, data, VAS vouchers and prepaid electricity with your own SIMcloud account.

== Description ==

SIMcloud for WooCommerce connects eligible paid WooCommerce orders to the SIMcloud API. Merchants configure virtual simple products, provider denominations and retail prices independently. Customers enter a recipient number and, for electricity, a meter number.

The plugin validates networks, bundles, voucher denominations and meter support, then submits eligible purchases in background jobs. A dedicated fulfilment record protects against repeated payment hooks and concurrent workers. Order completion waits for provider-confirmed delivery. Voucher codes and electricity tokens appear in authorized order details; they are not added to public logs or email bodies.

This is an initial 0.1.0 release. Provider transport is covered by deterministic mocks; see the repository test evidence for the exact WordPress, WooCommerce, PHP and database combinations. Merchants must complete controlled live acceptance with their own account before production sales. No SIMcloud sandbox is claimed.

Supported scope: ZAR, virtual simple prepaid products, quantity one per recipient line, classic checkout, Checkout Blocks and HPOS. Mixed prepaid/other baskets, subscriptions, variable products, bulk orders, automatic refunds and automatic retry of ambiguous submissions are excluded.

== External services ==

This plugin connects to SIMcloud at https://simcloud.co.za/api/ using the merchant's server-side bearer token. A separate funded SIMcloud account is required. The plugin license does not include free airtime, data, vouchers, electricity or payment processing.

Requests send the selected network/product, provider amount, correlation reference and, when applicable, recipient mobile number or electricity meter number for validation and fulfilment. Payment card details are not sent to SIMcloud by this plugin. The merchant enables the integration explicitly. No analytics or marketing telemetry is included.

SIMcloud API documentation: https://simcloud.co.za/api/
SIMcloud account and applicable service information: https://simcloud.co.za/
Before enabling sales, review the current SIMcloud account terms and privacy information and update the store privacy policy. Detailed installation, privacy and recovery guidance is maintained in the public repository.

== Installation ==

1. Install WooCommerce 10.0.4 or newer on WordPress 6.8.3 or newer with PHP 8.2 or newer and HTTPS.
2. Upload the tagged release ZIP through Plugins > Add New > Upload Plugin and activate it on the individual site.
3. Open WooCommerce > SIMcloud. Save the token from your SIMcloud account's API > Get Token screen.
4. Check the connection, refresh the data/VAS catalogues, and configure automatic payment gateway IDs.
5. Configure virtual simple products with a service, provider amount, network and catalogue product where needed. Set retail prices separately.
6. Review cron, privacy, payment/refund policies and controlled acceptance tests, then explicitly enable live submissions.

Use SIMCLOUD_WC_DISABLE_SUBMISSIONS in wp-config.php to pause fulfilment. Non-production WordPress environments are blocked. Copies/restores must be paused and reconciled before jobs resume.

== Frequently Asked Questions ==

= Can I use my existing payment gateway? =

Yes, if it confirms payment through WooCommerce payment_complete and its ID is explicitly allowed in the SIMcloud settings. COD, BACS and cheque never authorize automatic fulfilment. Staff can use the audited order action to confirm actually received funds.

= Are voucher codes and electricity tokens emailed? =

They appear in authorized WooCommerce order details after confirmed fulfilment. Order emails link to that page without including redeemable codes in the email body. Treat guest order links as private bearer links.

= What happens after a timeout? =

The purchase moves to needs review. The plugin does not repeat a create request automatically. Use provider IDs for safe status reads and reconcile unknown submissions through private SIMcloud support.

= Does a refund reverse a recharge? =

No. Customer payments and prepaid delivery are separate. Reconcile provider state and follow your store policy. The plugin never automatically refunds or resubmits a purchase.

= What does uninstall remove? =

Saved plugin credentials, settings, catalogue cache and plugin jobs are removed. WooCommerce orders, purchase snapshots and fulfilment evidence are retained. A server-configured token constant must be removed separately.

== Changelog ==

= 0.1.0 =

Initial implementation for airtime, data, VAS and electricity, with background fulfilment, restricted code delivery, HPOS/Blocks integration, privacy tools, documentation and isolated integration tests.
