# Troubleshoot SIMcloud for WooCommerce

Use this guide for **v0.2.0 (prerelease)** installation, payment and prepaid delivery problems. Start with [installation](installation.md) and [product setup](product-setup.md) if this is a new store.

**If a purchase may already have reached SIMcloud, reconcile that original attempt before taking another payment or submitting another purchase.** A lost response is not proof that the provider rejected it. The [order lifecycle guide](order-lifecycle.md) explains recovery states and evidence.

## Installation and configuration

| Symptom | Check and action |
| --- | --- |
| WordPress cannot find a plugin in the ZIP | Use the named `simcloud-woocommerce-0.2.0.zip` release asset. GitHub source archives are not the documented installation package. |
| Activation fails | Confirm WooCommerce is active, PHP/WordPress/WooCommerce meet requirements, and this is an individual-site activation. Record the exact redacted error. |
| SIMcloud menu is missing | Check the plugin is active and your user has WooCommerce management permission. Open WooCommerce → SIMcloud. |
| Connection check fails | Confirm the saved token and any overriding `SIMCLOUD_WC_API_TOKEN` constant, outbound HTTPS and the displayed error. Save a replacement token before checking. Never post it publicly. |
| Product cannot be purchased | Check ZAR currency, simple product type, complete service fields, live enablement, token/authentication pause, environment type and home-URL binding. |
| Catalogue product/amount is unavailable | Refresh catalogues and use a current matching product/denomination. The cache lasts 15 minutes. Update future product settings without editing historical purchase snapshots. |
| Data selection is ambiguous | Choose a supported network/amount mapping. The plugin cannot silently select between ambiguous provider products. |
| Recipient or meter validation fails | Check the entered number/network or meter. A failed or unknown validation is not permission to bypass the check. Retry validation later if the provider is unavailable. |
| A clone or restored site is paused | Verify the intended URL and production environment. Never copy live credentials to staging. Reconcile open purchases before deliberately enabling the intended installation. Same-URL database restores cannot be detected reliably. |

## Data products were not imported

Read the import result after **Refresh catalogues / import data**. Existing products are deliberately preserved, including drafts and trash. Skipped rows have invalid/ambiguous catalogue details; the API cannot distinguish products with the same network and amount. Failed saves are logged with provider IDs; check for SKU conflicts or WooCommerce save errors. A busy/unavailable database lock requires a later refresh. No products are deleted on an empty or failed catalogue read. See [import behaviour](data-product-import.md).

## Payment received but no delivery

1. Open the order and inspect the separate **Prepaid fulfilment** state for each line.
2. Verify the actual payment event. For automatic gateways, check the allowed gateway ID and the gateway's authenticated `payment_complete` flow.
3. For BACS, cheque or COD, confirm funds were received outside the plugin. Authorized staff can then select **SIMcloud: confirm received funds and queue** and click **Update**. Do not use this action just because checkout succeeded.
4. Inspect **WooCommerce → Status → Scheduled Actions**, group **`simcloud-wc`**, and **WooCommerce → Status → Logs**, source **`simcloud-woocommerce`**.
5. Confirm submissions are enabled, the wallet is funded and no authentication/environment pause remains. Use a successful **Check connection / balance** to clear an authentication pause after correcting credentials.

The plugin does not add a payment gateway, collect customer funds or fund the SIMcloud wallet. A listed installed gateway is not a tested compatibility guarantee.

## Delivery is delayed or needs review

| Situation | Action |
| --- | --- |
| Jobs are pending or failing | Check Action Scheduler and the host's cron runner. Low traffic can delay WP-Cron. Correct the runner, then inspect the saved line state before any manual action. |
| Provider request ID is known | Use **SIMcloud: refresh identified provider requests** to schedule read-only GET status checks. This does not create a replacement purchase. |
| Submission timed out with no provider ID | Preserve the correlation reference, order/line ID and attempt time. Ask SIMcloud privately to reconcile the original attempt. Do not place the same purchase again blindly. |
| Wallet was insufficient | Fund the wallet and investigate the recorded result. Funding does not automatically repeat an attempted purchase. |
| Provider says complete but code/token is missing | Keep the line under review. VAS/electricity completion requires the expected delivery information, not just a generic complete flag. |
| Customer asks for a refund | Investigate the original purchase and delivery first. A WooCommerce refund does not reverse prepaid delivery or cancel an uncertain supplier request. |

Polling is bounded: a long-running purchase can move to review after the observation limit. This is a recovery decision, not a promise of provider failure. The release has no resubmit button and no automatic purchase retry.

## Customer cannot find a voucher or token

Codes appear in the authorized order details after confirmed delivery. Ask the customer to use their order account or the private order link from the store email. Guest order-key links grant access: do not paste them into a public issue. Codes are deliberately omitted from email bodies and generic order metadata/logs.

If the order is still awaiting payment, queued, pending or under review, investigate that state first. If a delivered record lacks expected information, use private support with redacted technical evidence.

## Getting help

For a plugin bug, include versions, order storage mode (HPOS/legacy), gateway ID, the affected state, a redacted error and synthetic reproduction steps in [GitHub Issues](https://github.com/ErikThiart/simcloud-woocommerce/issues/new/choose). For money, account access or provider transaction investigation, use the private SIMcloud account/support channel. For vulnerabilities, follow [SECURITY.md](../SECURITY.md).

Never publish tokens, full customer phone/meter numbers, receipts, order keys, redeemable codes or raw provider responses. [Support boundaries](../SUPPORT.md) explain who can resolve each type of problem.

[← Documentation index](README.md) · [FAQ](faq.md) · [Order lifecycle](order-lifecycle.md)
