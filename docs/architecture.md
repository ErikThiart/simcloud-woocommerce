# Architecture

Version 0.2.0. The implementation is procedural PHP; runtime code is split by responsibility.

## Responsibilities

| Component | Responsibility |
| --- | --- |
| WordPress | Plugin lifecycle, capabilities, settings, HTTP transport, privacy integration |
| WooCommerce | Products, cart, checkout, payment integration, orders, customer views, emails |
| SIMcloud plugin | Prepaid configuration, recipient validation, purchase snapshots, submission guards, fulfilment, polling, diagnostics |
| SIMcloud API | Network lookup, catalogue, account balance, prepaid submission, provider delivery state |
| Action Scheduler | Persistent background jobs and bounded read-only polling |

Use WordPress HTTP functions with HTTPS verification, an explicit timeout, and redirects disabled on authenticated calls. Do not introduce a PHP framework, an HTTP library, a second queue, or a separate Node service. A narrow class is acceptable only if a required WooCommerce extension interface demands it; document the reason.

## Source layout

The package contains these runtime modules. Tests and build tooling stay outside the runtime ZIP.

```text
simcloud-woocommerce.php       Plugin header, dependency checks, hooks
includes/settings.php         Token configuration and connection diagnostics
includes/api.php              Fixed SIMcloud endpoints and response validation
includes/products.php         Prepaid product settings, catalogue mapping and data-product import
includes/cart.php             Recipient input, validation, cart isolation
includes/orders.php           Purchase snapshots and order views
includes/fulfilment.php        Submission state machine, scheduling, polling, watchdog
includes/storage.php           Atomic fulfilment claims and schema migration
includes/privacy.php           Retention, exporter, eraser integration
uninstall.php                  Explicit cleanup with record preservation
tests/                         Unit and integration tests
```

Prefix global functions, options, actions, and database identifiers consistently with `simcloud_wc_`. Load code only after dependency checks. Use WooCommerce's native UI rather than importing AdminLTE into WordPress.

## Order storage and HPOS

Use `wc_get_order()`, order/item getters and setters, metadata methods, and `save()` for WooCommerce order data. Do not directly read or write WooCommerce orders through `wp_posts` or `wp_postmeta`. This follows the [HPOS extension guidance](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/).

Save an immutable snapshot for each purchased line: service, recipient, network, provider denomination/bundle, catalogue description, retail amount, currency, and purchase reference. Jobs read the snapshot rather than the current product editor. Editing or deleting a product must not change an already-paid recharge.

## Atomic submission control

Order metadata alone does not provide an atomic cross-worker claim. The implementation uses a small plugin-owned fulfilment table with one unique row per order-item identity. It stores local state, an opaque site-scoped reference, request ID, platform order number, poll counters/times, and redacted error information. Personal data should remain in the order snapshot where possible.

Use `$wpdb->prefix`, prepared statements, versioned migrations, a unique key on the purchase identity, and a conditional state update whose affected-row count decides ownership. Two workers must not both pass a read-then-write metadata check. Store all irreversible-action markers before the network call.

Submission intent survives a crash. An interrupted `submitting` row becomes `needs_review`; expiration of a worker lock must never make it eligible for an automatic second POST. A crash before the POST can therefore require manual review too. That tradeoff is intentional until the provider offers durable idempotency.

The table is the authoritative local fulfilment record; WooCommerce metadata is a display/projection. A repair must restore that projection from the table without submitting another recharge. A lost HTTP response after remote acceptance remains a reconciliation case even with local transactions. No transaction can atomically cover the WordPress database and the remote API.

## Scheduling

Use WooCommerce's Action Scheduler and pass only the local fulfilment ID in job arguments. Do not place API tokens or full phone numbers in job payloads. Queue uniqueness helps reduce duplicate jobs; the database state check is still required.

Polling cadence: 30 seconds, 1 minute, 2 minutes, 5 minutes, then every 5 minutes up to a 60-minute observation window. These are application scheduling targets, not delivery promises. Stop immediately on a confirmed terminal result. At the observation limit, keep the provider request ID and move the item to review. Status refresh remains safe; a new submission does not.

Low-traffic stores need a working cron runner. Document server cron setup and job monitoring before release, using Action Scheduler's supported execution path. Do not fork or bundle another copy unnecessarily. See [Action Scheduler documentation](https://actionscheduler.org/).

## Completion and compatibility

Prevent WooCommerce's virtual-order auto-completion from claiming recharge fulfilment early. A prepaid-only order can complete after all its prepaid items are confirmed delivered. A partial or ambiguous result leaves the order available for staff review. Reject unsupported mixed baskets at both cart and checkout validation.

Checkout Blocks and classic checkout use different extension surfaces. Product-page input alone is insufficient: cart-line data, Store API validation/serialization, order-item creation, and customer display need explicit coverage. Blocks and HPOS declarations are backed by the matrix recorded in verification.md; other version combinations need their own evidence.

See the [lifecycle](order-lifecycle.md) for transitions and the [security requirements](security-and-privacy.md) for configuration, privacy, and recovery controls.
