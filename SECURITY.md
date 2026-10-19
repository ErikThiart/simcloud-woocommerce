# Security policy

## Supported versions

Version 0.1.x is the current initial release line. Report issues against the latest published commit/release. Live production acceptance remains a separate merchant responsibility. This policy also covers sensitive information accidentally exposed in project files.

## Report privately

Use GitHub's **Report a vulnerability** action on this repository's [Security page](https://github.com/ErikThiart/simcloud-woocommerce/security) to submit a private report. Do not open a public issue for an exploitable vulnerability or secret exposure. If the private report action is unavailable, open a public issue requesting a private reporting channel without including exploit details or sensitive data.

Include the affected commit/version, a concise description, prerequisites, reproduction steps using synthetic data, potential impact, and any suggested fix. Do not include real API tokens, full customer numbers, payment information, private databases, or unredacted logs. Do not spend wallet funds or test against stores/accounts you do not own or have permission to assess.

## Handling

Maintainers will assess reports, coordinate a fix when needed, and publish an advisory for affected released versions. There is no guaranteed response time or paid bounty programme. If a merchant token is exposed, the account owner should revoke/replace it through their SIMcloud account support process and reconcile potentially affected transactions.

The plugin's intended protections and review scope are documented in [security and privacy requirements](docs/security-and-privacy.md). A documented control is not evidence that it has been implemented or independently audited.
