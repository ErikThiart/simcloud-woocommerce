# Testing and acceptance

Current verification covers documentation only. The PHP plugin, WordPress environment, provider mocks, integration tests, and end-to-end suite do not exist yet. Do not present the scenarios below as passing tests.

## Documentation checks available now

Run `python3 scripts/check_docs.py` with Python 3.10 or newer. It checks required project documents and relative Markdown file targets. It does not validate remote URLs, Markdown anchor fragments, security, API behaviour, or plugin functionality. The GitHub documentation workflow runs the same command.

## Compatibility matrix to establish

Record exact versions and evidence before setting minimum/maximum compatibility headers. Include the current WordPress and WooCommerce stable versions at release time, the chosen minimum supported versions, PHP 8.5, the chosen minimum PHP version, and supported MySQL/MariaDB versions.

Run checkout coverage with classic and Blocks paths, guest and account purchases, HPOS enabled and legacy order storage, clean installs and upgrades, and a tested payment gateway with delayed/server-side confirmation. Unsupported combinations must be documented rather than implied to work.

## Required acceptance scenarios

| Area | Scenario | Expected evidence |
| --- | --- | --- |
| Settings | Unauthorized user, missing nonce, masked token update/removal | Configuration remains protected; token never appears in output |
| Validation | Invalid/ported number, unknown network, bundle mismatch | Purchase blocked with useful correction; no create call |
| Cart | Same product for two recipients, tampered quantity, mixed basket | Separate correct snapshots; unsupported purchases rejected |
| Pricing | Coupon, tax, sale price, rounded totals | Purchased provider denomination/bundle stays unchanged |
| Payment | Unpaid, delayed, repeated webhook, COD, manual transfer | Only eligible confirmed payment leads to a submission |
| Concurrency | Two workers/hook calls claim one item | One atomic claim and at most one local POST attempt |
| Crash | Terminate before POST and after acceptance before ID persistence | Durable intent survives; no automatic second POST |
| API | Valid `201`, wrong/missing ID, invalid JSON, `401`, `409`, `5xx`, timeout | Correct queued/failure/review state and redacted diagnostics |
| Status | `queued`, `pending`, `delivered`, `failed`, unknown, mismatched purchase, `404` | No premature completion; no poll job makes a POST |
| Polling | Temporary failures, low traffic, exhausted observation window | Bounded GET retries, visible review state, retained provider ID |
| Multi-line | One delivered item and one failed/unknown item | Delivered item never replayed; order shows partial fulfilment |
| Refund | Cancellation/refund before claim, during request, after delivery | Safe guard where possible; visible financial exception otherwise |
| Catalogue | Product changed/deleted after payment, stale or ambiguous bundle | Historical snapshot preserved; no silent substitution |
| Lifecycle | Deactivate/reactivate, uninstall, restore, staging clone | No uncontrolled replay; records preserved by default |
| Privacy | Logs, Store API, exports, guest/customer order access | No token leakage or unauthorized recipient disclosure |
| Runtime | Full PHP 8.5 error reporting | No warnings/deprecations in the exercised paths |

Use mocked HTTP transport with synthetic recipients for deterministic tests. Test negative assertions such as “no create call occurred” and “exactly one attempt was recorded”, not just final labels. Exercise concurrency against a real test database; sequential mocks alone cannot prove the atomic claim works.

## Controlled provider acceptance

Mock mode is not a SIMcloud sandbox. A real POST may spend wallet funds and deliver irreversible value. Arrange a controlled test only after the recipient, network, amount/bundle, and reference have been explicitly confirmed. Do not put live tokens in CI or enable paid acceptance tests on public pull requests.

Record the release commit, exact runtime matrix, redacted local/order/provider identifiers, acknowledgement, final query result, and wallet reconciliation. Never equate HTTP acceptance with provider delivery. No paid API calls were made during this repository's planning review.
