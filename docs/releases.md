# Release process

## Reproduce the package

```sh
python3 scripts/check_docs.py
bash scripts/test.sh
python3 scripts/package.py
bash scripts/test-package.sh
```

Tests require the isolated setup described in [testing](testing.md). The builder emits `dist/simcloud-woocommerce-0.3.0.zip` and a SHA-256 file. Its explicit allowlist includes the bootstrap, uninstall file, includes, assets/translations, WordPress readme and license. Tests, fixtures, repository metadata, credentials, development scripts, logs and private operational files are excluded.

ZIP paths have one `simcloud-woocommerce/` root. Timestamps and permissions are fixed so unchanged inputs produce the same bytes. Install the exact ZIP on a clean WordPress site before publishing it.

## Release gates

1. Run the automated PHP/database/checkout/concurrency/lifecycle suite and documentation checks.
2. Run WordPress Plugin Check against runtime files; inspect findings and distinguish expected direct ledger queries from actual vulnerabilities.
3. Check credential/customer-data exclusion and inspect the ZIP manifest.
4. Test clean installation and reinstallation/activation with existing fulfilment evidence.
5. Update version headers, WordPress readme, changelog, merchant instructions and verification evidence together.
6. Commit and push scoped changes, wait for the exact commit's CI matrix, then tag and publish the reviewed ZIP/checksum as a GitHub release.

Version 0.3.0 is a prerelease pending merchant-owned live provider acceptance. Do not label mock-provider evidence as live delivery. A stable production recommendation requires the controlled four-service checks described in [testing](testing.md).

## Rollback and maintenance

Pause submissions before rollback. Preserve and reconcile existing provider IDs, fulfilment rows, purchase snapshots and queued work. Rolling back PHP does not reverse delivered prepaid value. Never restore an older database and allow it to replay jobs without reconciliation.

Schema migrations must be versioned and preserve the unique purchase key and irreversible-attempt evidence. Deactivation/reactivation/uninstall are tested to preserve records and keep fulfilment disabled until deliberately enabled.

No WordPress.org or WooCommerce Marketplace listing is implied by GitHub publication. Follow the [official plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) if submitting a future build. Publish security advisories and supported-version changes alongside any affected release.
