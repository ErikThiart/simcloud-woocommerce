# Release process

No release exists. The repository currently checks documentation only. This checklist governs the future first installable build and stable release.

## Before an alpha

1. Implement the approved airtime/data baseline, dependency checks, protected settings, product mapping, both checkout paths, durable fulfilment records, and status queries.
2. Choose and test minimum supported runtime versions. Plugin headers must reflect actual evidence, including PHP 8.5 coverage.
3. Add meaningful PHP syntax, coding-standard, unit, integration, concurrency, and checkout checks. A green documentation workflow is not a plugin test result.
4. Provide reproducible local setup, mocked transport instructions, a development `readme.txt`, configuration references, and safe packaging.
5. Implement migrations and deactivation/reactivation/uninstall behaviour. Verify upgrades preserve customer orders and provider IDs.

## Before a stable release

Complete the [acceptance matrix](testing.md), security review, and controlled provider test. Resolve every scenario that could cause an unguarded repeat submission or a false delivery confirmation. Record any remaining limitations in the release notes and merchant guide.

Prepare verified installation/configuration instructions, screenshots without real customer data, payment-method policy, cron instructions, privacy text, troubleshooting, support boundaries, and rollback instructions. No placeholder compatibility claim or unfinished safety-critical feature is acceptable.

## Packaging and publication

- Use semantic versions. Keep plugin header, package version, WordPress readme stable tag, and changelog consistent.
- Build a ZIP with one `simcloud-woocommerce/` root containing runtime files, required assets/translations, license, and readme. Exclude tests, fixtures, credentials, logs, development dependencies, `.git`, and internal operational records.
- Install the exact ZIP on a clean test site and exercise upgrade from the previous supported release. Publish checksums with the artifact.
- Create a Git tag and GitHub release only after all gates pass. Label alpha/beta builds as prereleases with clear testing limitations.
- Consider WordPress.org submission after a complete plugin is available. GitHub publication alone does not mean WordPress.org or WooCommerce Marketplace approval.

WordPress.org distribution requires its own review and service disclosure. The project uses GPL-2.0-or-later and must keep bundled dependencies compatible. Follow the [official plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) at submission time.

## Rollback and maintenance

Pause new submissions before rollback. Preserve and reconcile already-submitted requests and pending jobs; rolling back PHP files does not reverse prepaid delivery. Migrations should avoid destructive changes and document compatibility with the prior version. Never restore an old database and allow it to replay fulfilment automatically.

Publish a clear supported-version policy with the first release. Security fixes require an advisory and upgrade instructions. Keep API research, compatibility evidence, and merchant documentation current whenever an endpoint or supported runtime changes.
