# Security and privacy requirements

Version 0.2.0 security design and implementation limits. This is not an independent security assessment.

## Credentials and transport

- Store API tokens server-side in a non-autoloaded WordPress option, with a configuration-constant override for managed installations. The server override is `SIMCLOUD_WC_API_TOKEN`; see the merchant guide.
- Mask the settings display. A blank unchanged form must not erase or reveal the existing token; replacement and removal are explicit actions.
- Use an appropriately privileged capability and nonce for settings, catalogue refresh, connection checks, status refresh, and recovery actions. A nonce alone is not authorization.
- Use WordPress HTTP APIs, verified TLS, a fixed SIMcloud HTTPS host, an endpoint allowlist, explicit timeouts, and no authenticated redirects. Do not offer an arbitrary production base-URL setting.
- Never expose tokens through frontend JavaScript, the Store API, HTML source, action arguments, logs, exports, screenshots, support bundles, or public issues.
- WordPress database storage is not a claim of encryption at rest. Database access and backups remain sensitive; do not invent home-grown encryption.

Data-product import requires both `manage_woocommerce` and `edit_products`, plus the settings nonce. A connection-scoped database lock serializes imports. New API-priced products are published; existing merchant products and prices are preserved.

## Input and purchase integrity

Validate service, recipient format, network mapping, supported bundle/denomination, quantity, currency, and payment eligibility on the server. Derive provider values from trusted product configuration and saved purchase snapshots rather than request fields or the discounted order total.

Escape output for its context, use prepared statements for the plugin table, and use WooCommerce APIs for orders. Privileged actions must reject unauthorized staff and cross-order/item mismatches. Customer views require the correct authenticated account or standard guest order authorization; an order ID alone grants no access.

## Data inventory

| Data | Intended location | Purpose |
| --- | --- | --- |
| Merchant API token | Server-only configuration | Authenticate the merchant's requests |
| Recipient number | Restricted cart/order snapshot | Deliver the purchased recharge |
| Service, network, denomination/bundle | Product and immutable order snapshot | Specify and audit fulfilment |
| Local reference and provider IDs | Fulfilment record and order view | Reconcile and query requests |
| Redacted status, timestamps, error codes | WooCommerce logger and restricted records | Diagnose failures |

The balance endpoint also returns merchant identity fields. Consume only the fields required for settings diagnostics; do not retain or publish the whole response. Do not log raw network-lookup URLs, full request/response bodies, authorization headers, or customer billing details. Mask recipient numbers in diagnostic logs.

## External service disclosure

The plugin sends the recipient number, chosen network, denomination/bundle amount, and a correlation reference to SIMcloud for lookup or fulfilment as appropriate. The store owner must see and enable this integration deliberately. Customer-facing privacy text must explain this transfer and link to current applicable provider terms and privacy information, verified before release.

Payment-card handling remains with the merchant's gateway. The integration must not send card data, full customer profiles, WooCommerce passwords, or unrelated order contents to SIMcloud. Do not add analytics, marketing tracking, remote executable code, or mandatory frontend credit links.

## Retention and lifecycle

The exporter includes prepaid purchase details. The eraser deliberately reports retained records instead of deleting purchase snapshots or redeemable codes automatically; staff must resolve delivery and applicable retention obligations before manual removal. Do not make a universal legal retention claim. The merchant selects a retention policy suitable for their operation and applicable obligations.

The plugin uses the WooCommerce logger and its configured retention policy. Review that policy in WooCommerce settings. Deactivation must pause new submissions and stop plugin-owned jobs; reactivation requires safe reconciliation. Uninstall preserves WooCommerce orders and fulfilment evidence by default. Removal of credentials and any optional irreversible cleanup must be explicit and documented.

## Recovery and incident handling

Use the [lifecycle policy](order-lifecycle.md) for ambiguous submissions. Restore/clone protection is mandatory: copied sites must not replay fulfilment with live credentials. Replacing an API token does not resolve the state of earlier provider requests.

Security reports must use the private channel described in [SECURITY.md](../SECURITY.md). Never request a reporter's real API token or customer database. Before any release, review authorization, CSRF, recipient disclosure, log redaction, SSRF, purchase tampering, concurrency, and backup/restore behaviour with recorded results.


## Redeemable codes and guest access

Voucher codes and electricity pins live in the plugin ledger result column rather than generic order item metadata. Authorized customer/staff views escape every value; email bodies contain a private order link rather than the codes. Guest links use WooCommerce order keys as bearer credentials. Sharing the link grants access to the order and its codes. Database backups remain sensitive.

Current site binding detects URL changes, not a historical database restore at the same URL. Operators must pause before restore/clone and reconcile provider state before enabling again. This limitation cannot be removed by claiming a local lock provides remote exactly-once semantics.
