# Changelog

## 0.2.0 — 19 October 2026

- Refresh catalogues now imports missing data-bundle products automatically from the product-list API.
- New products are published, simple and virtual, with network/provider ID/amount configured and the API amount as the initial regular retail price.
- Preserve existing merchant products, prices, descriptions, SKUs and publication state, including drafts and trash.
- Skip malformed/ambiguous catalogue mappings; guard concurrent imports and report per-import counts and save failures.
- Added real-database import tests and illustrated import documentation. No purchase or wallet spend is performed by import.

Prerelease; live paid supplier acceptance remains separate.

## Repository documentation — 19 October 2026

- Added illustrated WordPress installation, four-service product setup, screenshot gallery, FAQ and troubleshooting guides.
- Reworked the README and documentation navigation, with screenshot provenance, repository presentation guidance and a documentation issue form.
- Added a code of conduct and expanded documentation checks. Runtime v0.1.0 and its release ZIP are unchanged.

## 0.1.0 — 19 October 2026

Initial implementation for WooCommerce:

- Airtime and data fulfilment with network validation and catalogue-backed bundle mapping.
- VAS catalogue products/denominations and restricted redeemable voucher delivery.
- Electricity whole-rand validation, meter support checks and restricted token/units delivery.
- Server-only token configuration, wallet diagnostics, catalogue refresh and explicit live enablement/site binding.
- Separate retail/provider values, per-line recipient snapshots, classic checkout and Checkout Blocks Store API integration.
- HPOS order APIs, persistent atomic submission claims, bounded background polling and interrupted-attempt review.
- Explicit gateway eligibility, audited staff funds confirmation and read-only provider status refresh.
- Privacy exporter/retention reporting and safe deactivation/reactivation/uninstall.
- Real WordPress/WooCommerce database tests with synthetic transport, concurrent-process verification, PHP compatibility CI and deterministic ZIP packaging.

Initial prerelease. No live paid provider acceptance or directory approval is claimed. See [verification](docs/verification.md).

## Project foundation — 19 October 2026

Established the public repository, GPL-2.0-or-later license, API research, specification, contributor guidance, private security-reporting policy and documentation checks.
