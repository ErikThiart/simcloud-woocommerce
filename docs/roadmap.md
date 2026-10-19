# Roadmap

The milestones describe planned work, not delivery dates. There is no installable release yet.

| Milestone | Deliverable | Completion gate | Current status |
| --- | --- | --- | --- |
| 0 — Project foundation | Public repository, license, specification, integration research, contribution and support guidance | Documents reviewed, links checked, public visibility verified | Documentation prepared; implementation scope awaits review |
| 1 — Plugin and merchant configuration | Bootstrap, dependencies, token settings, diagnostics, catalogue/product mapping, safe development setup | Protected settings and read-only API tests pass | Not started |
| 2 — Purchase flow | Recipient entry, network validation, snapshots, classic/Blocks checkout, payment eligibility | Both checkout paths and pricing/tampering tests pass | Not started |
| 3 — Reliable fulfilment | Atomic submission, persisted provider IDs, bounded polling, review tools, safe lifecycle | Concurrent-worker, crash, duplicate-hook, timeout, and refund tests pass | Not started |
| 4 — First stable release | Security/privacy integration, compatibility matrix, merchant docs, release ZIP | Recorded acceptance evidence and controlled provider delivery test | Not started |
| 5 — Additional services | VAS and electricity specifications and implementation | Separate service-specific review, recovery and customer-delivery tests | Proposed |

## Decisions established

- Build a WooCommerce integration, as selected by the project owner.
- Keep the project separate from the private SIMcloud application.
- Publish the plugin project openly, with readable source and comprehensive documentation.

## Proposed baseline for review

- First release: fixed-denomination airtime and catalogue-based data, ZAR only.
- Procedural PHP, existing WooCommerce products and payment gateways, Action Scheduler.
- Native WordPress/WooCommerce admin UI, HPOS, classic checkout, and Checkout Blocks.
- GPL-2.0-or-later, with a funded merchant SIMcloud account required for actual services.
- Conservative reconciliation when remote submission outcome is unknown.

## Decisions to record during implementation

Choose runtime minimums from verified ecosystem requirements; define the exact gateway allowlist/manual-payment confirmation workflow; verify catalogue identity and network lookup vocabulary; settle retention defaults and validated provider terms/privacy links; decide whether the first version includes a guarded resubmission tool or support-led recovery only.

## Provider improvement to consider separately

Account-scoped, durable idempotent submission with payload matching and merchant-reference lookup across queued and final requests would improve recovery substantially. Specify retention and conflict semantics before changing the API. This is not implemented or included in the current plugin project.
