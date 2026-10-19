# SIMcloud for WooCommerce

Sell South African prepaid airtime and data through a WooCommerce store, with fulfilment through the [SIMcloud API](https://simcloud.co.za/api/).

**Project status: planning and specification. There is no installable plugin or production release yet.** This repository currently contains the implementation plan, integration research, merchant workflow, and contribution guidelines. Features described as planned are not available functionality.

[![Documentation](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/docs.yml/badge.svg)](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/docs.yml)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

## What we are building

A store owner connects their own funded SIMcloud account, configures prepaid products and retail prices, and accepts payment through their existing WooCommerce payment gateway. The customer supplies a recipient number and buys a product. After payment confirmation, the plugin submits the recharge and tracks its delivery.

The plugin will be open source under **GPL-2.0-or-later**. SIMcloud remains an external commercial service: merchants need an account, API access, and sufficient wallet funds. The plugin license does not make airtime, data, payment processing, or SIMcloud services free.

## Planned scope

| Capability | First release | Later work |
| --- | --- | --- |
| Airtime for MTN, Vodacom, Cell C, and Telkom | Planned | |
| Data bundles from the SIMcloud catalogue | Planned | |
| WooCommerce checkout and existing payment gateways | Planned | |
| Recipient validation and network lookup | Planned | |
| Background fulfilment and delivery tracking | Planned | |
| Merchant diagnostics and reconciliation tools | Planned | |
| Classic checkout, Checkout Blocks, and HPOS | Required before stable release | |
| VAS / retail vouchers | | Proposed |
| Prepaid electricity | | Proposed |
| SMS services | | Requires a separate product decision |

Read the [product specification](docs/product-specification.md) for the exact first-release boundary and the [roadmap](docs/roadmap.md) for acceptance gates.

## How an order will work

1. The customer selects a configured airtime denomination or data bundle.
2. The customer enters and confirms the South African recipient number.
3. WooCommerce processes payment using the merchant's configured gateway.
4. A background job submits the purchased product to SIMcloud once the order is eligible.
5. The plugin saves SIMcloud's request ID and queries its delivery status.
6. The customer sees the result; exceptions appear in the merchant's order view.

Payment receipt and recharge delivery are separate events. A queued API response does not mean airtime or data has been delivered.

## Integration constraint to understand first

The current airtime/data API returns a request ID after submission. It does not document a durable idempotency key or a direct lookup by merchant reference. A timed-out submission can therefore have an unknown outcome. The design sends such orders for reconciliation and **does not automatically submit them again**. See [order lifecycle and recovery](docs/order-lifecycle.md).

## Documentation

| Audience | Start here |
| --- | --- |
| Store owners | [Merchant guide](docs/merchant-guide.md) |
| Product reviewers | [Product specification](docs/product-specification.md) and [roadmap](docs/roadmap.md) |
| Developers | [Architecture](docs/architecture.md) and [API contract](docs/api-contract.md) |
| Operators | [Order lifecycle](docs/order-lifecycle.md) and [security and privacy](docs/security-and-privacy.md) |
| Testers and release maintainers | [Testing](docs/testing.md) and [release process](docs/releases.md) |
| Contributors | [Contributing](CONTRIBUTING.md), [support](SUPPORT.md), and [security reporting](SECURITY.md) |
| Research and decisions | [Sources and verification](docs/sources.md) |

## Development

There is no application build or plugin ZIP at this stage. To review the documentation locally:

```sh
git clone https://github.com/ErikThiart/simcloud-woocommerce.git
cd simcloud-woocommerce
python3 scripts/check_docs.py
```

The initial implementation will use procedural PHP, WordPress APIs, WooCommerce order APIs, and WooCommerce's Action Scheduler. Compatibility version numbers will be set from an actual tested matrix before the first installable release. PHP 8.5 compatibility is a project requirement; it has not yet been demonstrated by a plugin build.

## Contributing and support

Use [GitHub Issues](https://github.com/ErikThiart/simcloud-woocommerce/issues) for reproducible bugs and scoped feature proposals. Do not publish API tokens, customer phone numbers, payment details, or unredacted logs. See [SUPPORT.md](SUPPORT.md) for the difference between plugin support and account/order support.

## License

Copyright (C) 2026 Erik Thiart and contributors. SPDX license identifier: `GPL-2.0-or-later`.

This project is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version. Project material uses these terms unless a file states otherwise. The project is distributed without any warranty; see [LICENSE](LICENSE) for the complete license text.

WordPress and WooCommerce are the names of their respective projects; this project does not claim endorsement by either.
