# SIMcloud for WooCommerce

Sell South African **airtime, data bundles, VAS vouchers and prepaid electricity** through WooCommerce using the [SIMcloud API](https://simcloud.co.za/api/).

[![Plugin tests](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/plugin.yml/badge.svg)](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/plugin.yml)
[![Documentation](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/docs.yml/badge.svg)](https://github.com/ErikThiart/simcloud-woocommerce/actions/workflows/docs.yml)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

**Version 0.1.0 — initial release.** The plugin is implemented and installable. Automated tests use a real WordPress/WooCommerce database and intercepted SIMcloud HTTP responses. They do not establish live supplier delivery. Complete a controlled acceptance purchase with your own SIMcloud account before production sales. See [verification evidence](docs/verification.md).

## Features

| Service | Configuration | Customer delivery |
| --- | --- | --- |
| Airtime | Network and denomination, R2–R999 | Recharge to a validated South African mobile number |
| Data | Current catalogue product, network and provider amount | Selected bundle to the validated recipient |
| VAS | Current catalogue product and available denomination | Redeemable voucher code(s) in authorized order details |
| Electricity | Whole-rand provider amount, R55–R1000 | Meter validation, then token(s) and units in order details |

The plugin provides protected API-token settings, connection/balance diagnostics, data/VAS catalogue refresh, recipient and meter input, classic checkout, Checkout Blocks/Store API integration, HPOS-compatible order access, Action Scheduler jobs, a persistent line-level fulfilment ledger, bounded status polling, staff status refresh, and privacy exporter/retention reporting.

Retail prices, tax and coupons stay separate from provider denominations. Duplicate hooks and concurrent workers converge on an atomic local submission claim. A queued request does not complete the WooCommerce order: all purchased lines must have matching confirmed delivery, including redeemable codes for VAS/electricity.

## Requirements

- WordPress 6.8.3+, WooCommerce 10.0.4+, PHP 8.2+, HTTPS, and a working WordPress/Action Scheduler runner.
- A ZAR store, virtual simple prepaid products, quantity one per recipient line.
- Your own SIMcloud account, API token and sufficient wallet funds.
- A payment gateway that confirms payment through WooCommerce, explicitly allowed in plugin settings, or staff confirmation of actually received funds.

The initial scope excludes mixed prepaid/other baskets, variable products, subscriptions, network-wide activation, multi-currency, bulk imports, customer wallets, automatic refunds and repeat submissions. Supported version combinations and their evidence are in [testing](docs/testing.md).

## Install and configure

1. Download the installable ZIP from [GitHub Releases](https://github.com/ErikThiart/simcloud-woocommerce/releases). Upload it through **Plugins → Add New → Upload Plugin**, then activate it on the individual site.
2. Open **WooCommerce → SIMcloud**. Save the token from your SIMcloud account's **API → Get Token** screen. Run **Check connection / balance**.
3. Refresh catalogues. In each simple product's **Product data → General** section, configure SIMcloud service, provider amount, network and catalogue product where applicable. Set the WooCommerce retail price separately.
4. Configure allowed payment gateway IDs, cron, privacy and store support/refund policies. Submissions start disabled; enable them deliberately after acceptance checks.

Read the [merchant guide](docs/merchant-guide.md) for setup, payment handling, operations and recovery. Voucher codes and electricity tokens appear on the authorized order page; emails link to it without embedding the codes.

## Submission recovery

The current airtime/data and VAS contracts do not guarantee durable idempotent creation or lookup by the merchant reference. A POST can be accepted even when its response is lost. Such purchases go to **needs review** without an automatic second POST. Staff can refresh identified provider requests; unidentified outcomes require private SIMcloud reconciliation. There is no resubmit button in this release.

A refund does not reverse prepaid delivery. A balance check does not reserve funds. Read [order lifecycle and recovery](docs/order-lifecycle.md) before operating a live store.

## Documentation

| Audience | Documentation |
| --- | --- |
| Merchants | [Merchant guide](docs/merchant-guide.md), [support](SUPPORT.md) |
| Developers | [Product specification](docs/product-specification.md), [architecture](docs/architecture.md), [API contract](docs/api-contract.md) |
| Operators | [Lifecycle and recovery](docs/order-lifecycle.md), [security and privacy](docs/security-and-privacy.md) |
| Testers | [Test setup and matrix](docs/testing.md), [verification evidence](docs/verification.md) |
| Maintainers | [Release process](docs/releases.md), [roadmap](docs/roadmap.md), [contributing](CONTRIBUTING.md), [security reporting](SECURITY.md) |
| Reference | [Sources](docs/sources.md), [changelog](CHANGELOG.md) |

## Development and packaging

```sh
git clone https://github.com/ErikThiart/simcloud-woocommerce.git
cd simcloud-woocommerce
python3 scripts/check_docs.py
# Requires an isolated local MySQL/MariaDB server; see docs/testing.md.
bash scripts/setup-tests.sh
bash scripts/test.sh
python3 scripts/package.py
```

The package builder uses an explicit runtime allowlist and produces a deterministic ZIP and SHA-256 checksum under `dist/`. Development tests, credentials and logs are excluded. Application code is procedural PHP using native WordPress, WooCommerce and Action Scheduler APIs.

## Open source and commercial service

Copyright (C) 2026 Erik Thiart and contributors. SPDX license identifier: `GPL-2.0-or-later`.

This project is free software: you can redistribute it and/or modify it under the GNU General Public License published by the Free Software Foundation, version 2 or, at your option, any later version. It is distributed without warranty. See [LICENSE](LICENSE).

The plugin license does not include prepaid value, SIMcloud account services, payment processing or a support contract. WordPress and WooCommerce are the names of their respective projects; no endorsement or directory approval is claimed.
