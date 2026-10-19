# Roadmap

## Version 0.1.0

The initial implementation includes airtime, data, VAS vouchers and prepaid electricity. It provides native merchant settings, catalogue mapping, classic/Blocks checkout, restricted code delivery, HPOS-compatible order access, durable submission guards, polling, status refresh, privacy tools, isolated tests and reproducible packaging.

Release verification and limits are recorded in [verification](verification.md). The project does not claim WordPress.org approval, an independent security audit or live provider acceptance.

## Next work

1. Run controlled merchant-owned acceptance for all four services and record provider delivery/wallet reconciliation privately.
2. Gather first-store operating feedback on product setup, gateway confirmation and support-led reconciliation.
3. Add further compatibility combinations only with executable evidence.
4. Consider WordPress.org submission after applicable provider terms/privacy disclosures and directory review requirements are satisfied.

Mixed baskets, variable products, subscriptions, customer wallets, bulk order/CSV imports, SMS products, automatic refunds and guarded resubmission are separate proposals rather than hidden first-release commitments.

## Provider improvement

Account-scoped durable idempotency, payload matching and reference lookup across queued/final records would simplify recovery. Electricity currently transforms the submitted reference by appending a suffix; the plugin preserves the returned reference. Changing the provider contract is separate backend work. This repository does not alter SIMcloud production.

## Data-product import — v0.2.0

Implemented automatic publication of missing data bundles from the API product list, using the API amount as the initial regular retail price. Repeat imports preserve merchant edits and prices. Scheduled mirroring/repricing and VAS product import are outside this change.
