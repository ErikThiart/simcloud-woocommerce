# Product specification

Status: proposed implementation baseline, 19 October 2026. WooCommerce integration has been confirmed by the project owner. Service scope and policies below are recommendations for review before implementation.

## Purpose and commercial flow

Let South African merchants sell prepaid products from their own WooCommerce websites using their own SIMcloud accounts. Each store owns its customer relationship, retail pricing, payment gateway, and refund decisions. SIMcloud fulfils the prepaid service from the merchant's funded wallet.

There are two independent money flows: the customer pays the store, and SIMcloud charges the merchant according to the merchant's service terms. WooCommerce payment settlement does not automatically fund the SIMcloud wallet. A customer payment, a SIMcloud queue acknowledgement, and confirmed delivery must be recorded separately.

## First-release assumptions

- South African airtime and data only, sold in ZAR.
- Existing WooCommerce simple products, marked virtual and configured for SIMcloud fulfilment. No new product class unless implementation proves it necessary.
- Fixed airtime denominations configured by the merchant. The current API handler permits R2 through R999; revalidate this before release.
- Data bundles selected from a refreshed SIMcloud catalogue, retaining the selected network, provider amount/sellvalue, and product description.
- One recipient per cart line, quantity one. Identical products for different recipients must remain separate lines. Quantity changes above one are rejected on the server.
- Multiple prepaid lines may share an order. Mixed physical/prepaid baskets, subscriptions, renewals, variable products, customer-entered airtime amounts, and multisite activation are outside the initial supported scope.
- Guest checkout and customer-account checkout both need coverage.
- Classic checkout and Checkout Blocks must both pass the release tests.
- The merchant's gateway confirms payment through WooCommerce. The plugin is a fulfilment integration rather than a payment gateway.

## Merchant experience

The planned settings live within WooCommerce and use its native admin patterns. The merchant can save or replace a masked API token, run an explicit connection check, inspect the last known wallet balance and its timestamp, refresh the data catalogue, and enable eligible products. A connection check uses the balance endpoint and never places a recharge.

The product editor stores service, network, provider denomination or bundle amount, and merchant retail price as distinct values. Catalogue refresh must not overwrite a merchant's retail prices or historical orders. A stale or unavailable bundle must not silently fall back to a different bundle or denomination.

The order view shows line-level fulfilment status, recipient, local reference, request ID, platform order number, last check, and a redacted failure reason. Staff can refresh status using an existing request ID. New submissions after failures require an explicit, audited recovery flow and proof that an earlier recharge was not accepted.

## Customer experience

The customer chooses a product and enters a South African recipient number on its product page. The cart and checkout show the recipient and selected product so mistakes can be corrected before payment. Validation and recipient persistence happen on the server and must survive both supported checkout paths.

Network prefixes are not sufficient to identify a ported number. Resolve the network through the API and map it explicitly to the selected service's network code. A mismatch requires a customer correction; never switch the purchased bundle silently.

The order page distinguishes waiting for payment, queued, processing, delivered, failed, and needs review. Receipts must describe payment accurately and avoid claiming delivery before confirmation. Accessibility includes associated labels, keyboard use, readable errors, and screen-reader status announcements where appropriate.

## Pricing, discounts, and tax

Store provider denominations and retail prices separately, using integer cents or validated decimal strings. Coupons and taxes affect the customer's order total; they must not change the purchased airtime face value or data bundle. Historical orders retain an immutable purchase snapshot.

The merchant chooses retail pricing and tax settings with their adviser. No fixed profit margin, provider discount, tax outcome, or settlement time is promised by the plugin. Reject non-ZAR purchases in the initial release rather than converting provider amounts implicitly.

## Payments, failures, and refunds

Automatic fulfilment requires an eligible paid order and an allowed payment method. Never trigger solely from a thank-you-page visit or a generic status change. COD is excluded from automatic fulfilment; bank-transfer/manual payments require an explicit staff confirmation of receipt. A gateway's delayed payment confirmation must be tested.

A failed recharge does not automatically refund a customer payment. Refunding a customer does not reverse delivered airtime or data. An order awaiting reconciliation cannot be treated as safely undelivered. The initial release surfaces these cases for a merchant decision and records the decision.

## Deliberately deferred

VAS voucher delivery, electricity tokens and meter validation, SMS sending, reseller-account provisioning, customer wallets, multi-currency, automatic wallet funding, bulk CSV imports, bespoke checkout pages, and referral or commission programmes are not first-release requirements. Each additional service needs a separate specification and acceptance tests.

## Definition of a stable release

The merchant can complete the documented setup, publish an eligible product, take a confirmed payment, observe exactly one local submission attempt for that purchase, and reconcile confirmed provider delivery. The tests must also show safe behaviour for duplicate hooks, concurrent workers, process crashes, timeouts, invalid credentials, insufficient balance, catalogue changes, and cancellation/refund races.

No stable release is declared until the [testing matrix](testing.md), [security requirements](security-and-privacy.md), and [release checklist](releases.md) have evidence. Local submission guards cannot promise exactly-once remote delivery without provider support.
