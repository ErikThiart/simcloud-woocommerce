# Security and privacy requirements

Status: implementation requirements, not a completed security assessment. No published version is supported yet.

## Credentials and transport

- Store API tokens server-side in a non-autoloaded WordPress option, with a configuration-constant override for managed installations. Confirm the final configuration name in the implementation guide.
- Mask the settings display. A blank unchanged form must not erase or reveal the existing token; replacement and removal are explicit actions.
- Use an appropriately privileged capability and nonce for settings, catalogue refresh, connection checks, status refresh, and recovery actions. A nonce alone is not authorization.
- Use WordPress HTTP APIs, verified TLS, a fixed SIMcloud HTTPS host, an endpoint allowlist, explicit timeouts, and no authenticated redirects. Do not offer an arbitrary production base-URL setting.
- Never expose tokens through frontend JavaScript, the Store API, HTML source, action arguments, logs, exports, screenshots, support bundles, or public issues.
- WordPress database storage is not a claim of encryption at rest. Database access and backups remain sensitive; do not invent home-grown encryption.

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

The plugin will send the recipient number, chosen network, denomination/bundle amount, and a correlation reference to SIMcloud for lookup or fulfilment as appropriate. The store owner must see and enable this integration deliberately. Customer-facing privacy text must explain this transfer and link to current applicable provider terms and privacy information, verified before release.

Payment-card handling remains with the merchant's gateway. The integration must not send card data, full customer profiles, WooCommerce passwords, or unrelated order contents to SIMcloud. Do not add analytics, marketing tracking, remote executable code, or mandatory frontend credit links.

## Retention and lifecycle

Integrate with WordPress privacy exporter/eraser facilities and document which records must remain for unresolved orders or accounting obligations. Do not make a universal legal retention claim. The merchant selects a retention policy suitable for their operation and applicable obligations.

Logs require a documented bounded retention period. Deactivation must pause new submissions and stop plugin-owned jobs; reactivation requires safe reconciliation. Uninstall preserves WooCommerce orders and fulfilment evidence by default. Removal of credentials and any optional irreversible cleanup must be explicit and documented.

## Recovery and incident handling

Use the [lifecycle policy](order-lifecycle.md) for ambiguous submissions. Restore/clone protection is mandatory: copied sites must not replay fulfilment with live credentials. Replacing an API token does not resolve the state of earlier provider requests.

Security reports must use the private channel described in [SECURITY.md](../SECURITY.md). Never request a reporter's real API token or customer database. Before any release, review authorization, CSRF, recipient disclosure, log redaction, SSRF, purchase tampering, concurrency, and backup/restore behaviour with recorded results.
