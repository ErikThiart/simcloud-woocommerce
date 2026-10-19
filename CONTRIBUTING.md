# Contributing

Thank you for contributing to SIMcloud for WooCommerce. The project has an initial installable four-service release. Focused bug reports, tests, documentation improvements and scoped design feedback are welcome.

For merchant instructions, start with [installation](docs/installation.md) and [product setup](docs/product-setup.md). Documentation corrections have a dedicated [issue form](https://github.com/ErikThiart/simcloud-woocommerce/issues/new?template=documentation.yml). Follow the [repository guide](docs/repository-guide.md) and [screenshot provenance instructions](docs/images/README.md) when updating guides.

## Start with the scope

Read the [product specification](docs/product-specification.md), [architecture](docs/architecture.md), [API notes](docs/api-contract.md), and [roadmap](docs/roadmap.md). Open an issue before implementing a new service, changing purchase/payment policies, or adding a dependency. Keep pull requests focused on one problem and explain any tradeoff.

## Development principles

- Use procedural PHP with small, clearly named functions. Avoid frameworks, service containers, speculative abstractions, and unrelated refactors.
- Use native WordPress and WooCommerce APIs. Order access must support HPOS; do not query WooCommerce order tables directly.
- Treat PHP 8.5 deprecations as bugs. Validate nullable input and response fields explicitly.
- Validate input server-side, escape output, protect privileged actions with capabilities and nonces, and prepare plugin-owned SQL.
- Preserve customer purchase snapshots and financial state. Never automatically repeat a create request with an ambiguous outcome.
- Make logs useful without exposing credentials, complete phone numbers, raw provider bodies, or payment details.
- Add meaningful tests for changed behaviour. Do not claim compatibility or provider delivery without evidence.

## Development workflow

```sh
python3 scripts/check_docs.py
bash scripts/setup-tests.sh
bash scripts/test.sh
python3 scripts/package.py
git diff --check
```

Use Python 3.10 or newer. Describe future behaviour as planned until implemented. Every new feature needs matching merchant instructions, API notes where applicable, failure/recovery behaviour, and tests. Do not commit placeholder screenshots or unsupported compatibility claims.

The [testing plan](docs/testing.md) describes the integration checks. Follow its isolated database instructions. Never use a production database or live API credentials in ordinary tests.

## Pull request checklist

1. Explain the concrete problem and resulting behaviour.
2. Link the relevant issue or specification section.
3. Include verification results and material limitations.
4. Update related documentation and the unreleased changelog when appropriate.
5. Confirm no credentials, customer records, production logs, payment details, or private application source are included.

Use synthetic fixtures and mock HTTP transport by default. Never add live tokens to public CI or run paid requests during an ordinary contribution test. If a controlled live test is required, arrange it privately with a maintainer and follow the [acceptance policy](docs/testing.md).

## License and conduct

Submit only material you can license. Contributions are accepted under the project's [GPL-2.0-or-later license](LICENSE), unless separately agreed in writing. Declare third-party dependencies and their compatible licenses. No contributor agreement or transfer of copyright is required by this repository.

Follow the [code of conduct](CODE_OF_CONDUCT.md). Be respectful and specific in technical discussions. Critique the change rather than the person. Report sensitive issues through [SECURITY.md](SECURITY.md); use [SUPPORT.md](SUPPORT.md) for account and transaction boundaries.
