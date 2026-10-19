# Order lifecycle and recovery

Version 0.1.0 implementation and operator policy.

Each purchased line has its own fulfilment record. Payment state belongs to WooCommerce; recharge state belongs to the plugin. Never use one field to mean both.

## States

| Local state | Meaning | Allowed next action |
| --- | --- | --- |
| `awaiting_payment` | Snapshot saved; payment is not eligible | Wait for confirmed payment or cancel before submission |
| `ready` | Payment and product checks passed | Atomically claim submission |
| `submitting` | Durable submission intent recorded before POST | Save validated acknowledgement, record a clear rejection, or require review |
| `queued` | SIMcloud returned a persisted request ID | Query status using that ID |
| `pending` | Provider is processing the identified request | Query status within polling limits |
| `delivered` | Matching provider query confirmed delivery | Terminal; no further submission |
| `failed` | Identified final provider failure | Merchant review; no automatic resubmission or refund |
| `needs_review` | Outcome uncertain, evidence inconsistent, or polling window exceeded | Read-only reconciliation and audited staff resolution |

Automatic order completion occurs only when every prepaid line is confirmed delivered. Staff must not manually mark delivery complete while fulfilment is outstanding. One delivered item must never be sent again because another line failed. Failed prepaid fulfilment must not erase a received payment or mark the whole financial order unpaid.

## Submission sequence

1. Load the order through WooCommerce and verify payment eligibility, payment-method policy, currency, cancellation/refund state, and immutable purchase data.
2. Atomically change the unique fulfilment row from `ready` to `submitting`, persisting an opaque reference and timestamp. Exit if another worker owns the row or any earlier attempt exists.
3. Recheck eligibility immediately before the network call. If a cancellation or refund has intervened, stop and preserve evidence. A cancellation arriving during the remote call may still require reconciliation.
4. Submit exactly the saved service, recipient, network, and provider amount.
5. On a valid `201` acknowledgement, save the request ID before scheduling any status job. Failure to persist after the remote call is an ambiguous outcome.
6. On a create rejection, retain a redacted reason and require review. On a timeout, crash, unexpected response, or uncertain outcome, enter `needs_review`.
7. Schedule bounded GET queries only for identified provider requests. A poll job never calls a create endpoint.

Repeated payment webhooks, repeated hooks, refreshed browser pages, and concurrent jobs all converge on the same unique purchase row. None authorizes another POST. Recovery of a stuck worker must not reset `submitting` to `ready` automatically.

## Operator recovery

| Situation | Required response |
| --- | --- |
| Request ID exists, status temporarily unavailable | Refresh status with the same ID; preserve financial state |
| POST timed out before an ID was returned | Search/reconcile through SIMcloud account support using the saved reference, recipient, amount, and time; keep private customer details out of GitHub |
| Provider reports `409` | Investigate the possible previous order; waiting five minutes is not proof it is safe to recharge again |
| Wallet insufficient | Alert merchant and record failure; funding the wallet alone must not silently restart failed purchases |
| Delivered but customer wants a refund | Merchant decides under their policy; a WooCommerce refund cannot reverse airtime/data |
| Customer payment refunded while a request is queued | Keep provider monitoring and flag the financial exception; do not lose the request ID |
| Polling limit reached | Mark review required, keep the ID, permit status refresh, and alert staff |
| Restore or clone of a store | Pause submissions before jobs resume; reconcile every outstanding attempt against provider records |

Any future resubmission action must require an authorized capability, nonce, an explicit warning showing recipient and amount, evidence that the prior request was not accepted, and an audit note. It must create a separate linked attempt record rather than overwriting the original evidence. Version 0.1.0 uses support-led recovery and has no resubmit button.

## Reference design

Use a generated site identifier plus WooCommerce order ID and line-item ID, for example `wc-a1b2c3-1042-73`. Do not use email, phone number, customer name, or a raw site URL. The reference is for correlation; it is not a provider-enforced unique key. Cloned stores must start paused and receive a new site identity before intentional live use.

## Customer and merchant messages

Use plain descriptions such as “Payment received; recharge awaiting delivery confirmation” and “Recharge needs review; please contact the store.” A success animation, payment redirect, `201`, or status HTTP `200` must never be described as confirmed delivery without the matching terminal provider status.
