# SIMcloud API contract for the plugin

Review date: 19 October 2026. This records the integration inputs observed during planning. Revalidate against the live [API documentation](https://simcloud.co.za/api/) and [OpenAPI document](https://simcloud.co.za/api/openapi.yaml) before implementation and release. It is not a replacement for the provider contract.

The live OpenAPI download matched the local contract inspected during this review. Its SHA-256 was `464f67ea710f43699a499e9374eb8b0b130ca2427110220f7322f9696e65f1b7`. Runtime source inspection informed the additional notes below; authenticated requests and actual provider delivery were not exercised.

## Transport and credentials

Base URL: `https://simcloud.co.za`. All endpoints below use a server-side `Authorization: Bearer <token>` header. JSON creates use `Content-Type: application/json`. Credentials belong to the merchant account. Never transmit them to a browser or include them in URLs.

The token is obtained through the merchant's SIMcloud account under **API → Get Token**. No OAuth flow, public signup API, token-generation endpoint, webhook secret, or sandbox environment is assumed for this plugin.

## Endpoints used by the first release

| Purpose | Method and path | Parameters / payload |
| --- | --- | --- |
| Connection and last known balance | `GET /api/balance.php` | None |
| Recipient network | `GET /api/network.php` | `msisdn` |
| Data catalogue | `GET /api/data.php?products=1` | Optional `network` |
| Airtime submission | `POST /api/airtime.php` | `msisdn`, `network`, `amount`, `reference` |
| Airtime status | `GET /api/airtime.php` | `request_id` or `orderno` |
| Data submission | `POST /api/data.php` | `msisdn`, `network`, `amount`, `reference` |
| Data status | `GET /api/data.php` | `request_id` or `orderno` |
| VAS catalogue / status | `GET /api/vas.php` | No identifier for catalogue; `order_id` for status |
| VAS submission | `POST /api/vas.php` | `product_id`, `amount`, `reference` |
| Electricity meter support | `GET /api/electricity.php` | `meter_number` |
| Electricity submission | `POST /api/electricity.php` | `meter_number`, `amount`, `client_reference`, `recipient`, `send_sms: false` |
| Electricity status | `GET /api/electricity.php` | `order_id` |

Keep catalogue and status requests distinct: bare data GET without an order identifier returns the catalogue, not the status of a previous purchase.

## Recipient and networks

Normalize accepted South African mobile input to `+27` followed by nine digits and validate on the server. Do not copy permissive provider normalization as the only local validation. Do not infer network from a number prefix; use the lookup result and handle unknown or unsupported responses explicitly.

| Network | Airtime code | Data code |
| --- | --- | --- |
| MTN | `p-mtn` | `pd-mtn` |
| Vodacom | `p-vodacom` | `pd-vodacom` |
| Cell C | `p-cellc` | `pd-cellc` |
| Telkom | `p-heita` | `pd-heita` |

Lookup output must be mapped to the appropriate service code. Validate its actual response vocabulary in controlled integration testing; do not assume it already returns the final purchase code.

## Airtime request

Example request shape only. This is not a test command and must not be sent with invented recipient details:

```json
{
  "msisdn": "<confirmed South African recipient>",
  "network": "p-mtn",
  "amount": 10.00,
  "reference": "wc-a1b2c3-1042-73"
}
```

The current airtime handler validates the amount between R2 and R999. Persist cents locally and serialize the configured face value; WooCommerce discounts and taxes must not alter that face value.

An HTTP `201` response indicates a queued request. Require a successful body with a positive `request_id`, correct service, and expected purchase details before entering `queued`. Persist the returned ID immediately. If the acknowledgement is missing, malformed, or inconsistent, treat the submission as ambiguous.

## Data catalogue and request

Catalogue items currently expose `product_id`, `network`, `network_name`, `description`, `groupname`, `amount`, and `sellvalue`. Data submission selects a bundle by network plus amount/sellvalue. The create contract does not accept a catalogue `product_id` as a substitute.

From v0.2.0, a privileged catalogue refresh imports missing data bundles as published WooCommerce products. Names, groups, networks and IDs come from the catalogue; the exact API amount becomes the initial regular retail price. Existing products and prices are preserved. Invalid or ambiguous mappings are skipped. Import makes no create/purchase request. See [import instructions](data-product-import.md).

Store the selected catalogue item for audit, but submit the documented network and amount. Confirm the mapping still exists before accepting a purchase. If two products cannot be distinguished by the supported create fields, do not offer them as independently selectable products until the ambiguity is resolved. Cache catalogue reads with a visible refresh timestamp; never silently overwrite a merchant's retail price.

## Status responses

The inspected handlers expose top-level `status` values `queued`, `pending`, `delivered`, and `failed`. Relevant fields include `service`, `request_id`, `msisdn`, `network`, `amount`, `reference`, `platform_order_number`, `queue_status`, `final_order_id`, `final_order_status`, `error`, `created_at`, and `updated_at`.

Validate identifiers and purchase details against the saved snapshot. Unknown statuses, mismatches, or invalid bodies require review. HTTP `200` alone is insufficient evidence of delivery. Handle nullable fields explicitly; no PHP null-to-string assumptions.

## Error policy

| Result | Plugin action |
| --- | --- |
| `400` | Surface the validation or balance failure; do not blindly resubmit |
| `401` | Stop new fulfilment and alert the merchant to configuration/authentication failure |
| `404` on status | Retain the ID, flag reconciliation; do not interpret as permission to create again |
| `409` on create | Possible duplicate conflict; do not claim delivery or retry after five minutes |
| `429` | Back off reads; an attempted create requires reconciliation before any new POST |
| Timeout, connection loss, `5xx`, invalid JSON on create | Unknown outcome; persist `needs_review`, no automatic repeat POST |
| Temporary failure on an identified status GET | Retry reads within the documented polling bounds |

The OpenAPI document describes a five-minute same-number-and-amount duplicate rule. The inspected implementation checks recent order records; that rule is not a durable idempotency guarantee and does not prove a queued race cannot occur.

## Known gaps and release implications

1. Airtime/data status queries accept a request ID or platform order number, not the merchant's `reference`.
2. No durable idempotency-key semantics are documented for these create endpoints.
3. No airtime/data delivery webhook is documented in the inspected contract; use bounded status reads.
4. No sandbox or provider test credentials were established during this review. Mock tests must be labelled as local simulations.
5. Balance is a last-known value with a timestamp, not a reservation. A balance check cannot guarantee a subsequent purchase will succeed.

A future provider change could add account-scoped idempotent submission and reference lookup that includes queued requests. That would reduce reconciliation work but requires a separate SIMcloud API change. This repository does not change the backend API or claim that capability exists.


## VAS vouchers (implemented)

The catalogue also exposes `logo_url`. From v0.3.0, known SIMcloud logo filenames select bundled image files; runtime does not fetch image URLs. VAS import creates one product per unique available denomination. The API amount is the initial retail price. Quick setup combines balance and both catalogue GETs; no paid create is part of setup.

`GET /api/vas.php` returns `products` with `product_id`, `voucher_type`, `slug`, `default_amount`, and `available_amounts`. Configuration and purchase checks require an exact product ID and an available denomination. Submit `product_id`, `amount`, and an opaque `reference` through POST. A valid `201` returns `order_id`, `transaction_id`, product/amount/reference, `process_status` and billing/fee fields.

Query with `order_id` or `transaction_id`. Top-level `pending` means processing; `failed` is terminal failure; `success` requires `vouchers`. Each voucher includes `voucher_type`, `voucher_code`, nullable `value` and `expiry_date`. The plugin verifies purchase identity and requires nonempty redeemable codes before delivery. It stores normalized codes/descriptions/expiry only in its restricted ledger; raw provider bodies are not retained. No merchant-reference lookup or durable idempotency is assumed for VAS.

## Electricity (implemented)

`GET /api/electricity.php?meter_number=...` checks support. Require the matching meter and `supported: true`; null/unknown/internal-error responses cannot authorize purchase. The plugin validates a 1–50 character alphanumeric meter locally.

POST fields are `meter_number`, whole-rand `amount` (R55–R1000), `client_reference`, `recipient`, and `send_sms: false`. The provider amount includes the SIMcloud fee. This plugin delivers tokens through the order page instead of requesting provider SMS. It does not claim the total becomes electricity units.

A valid `201` contains positive `order_id`, `submitted_reference`, transformed `client_reference`, matching meter/recipient/amount, and fee/electricity allocation fields. SIMcloud appends a timestamp suffix to the submitted reference; preserve the returned reference. Do not assume the original reference can be queried unchanged.

Query using `order_id` (never include `meter_number` in a status query because that selects meter validation). A pending response may contain only `status` and `message`. Final `success` includes `transaction_details` and `recharge_pin_information`; require matching meter/recipient and nonempty pin entries before delivery. Normalize descriptor, pin and units. Multiple entries, including key-change tokens, are retained in order. Final transaction amount is not compared with gross provider amount because provider fees can reduce the electricity allocation.

Known ambiguous POST outcomes for every service require reconciliation; this plugin never repeats them automatically. The source review and contract are not live paid delivery evidence.
