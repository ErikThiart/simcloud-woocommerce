# Maintaining the GitHub repository and documentation

Keep the repository understandable to a store owner searching for a **WordPress airtime plugin**, **WooCommerce data bundles**, **VAS vouchers** or **prepaid electricity in South Africa**. These are descriptions of implemented scope, not measured search-volume claims.

## GitHub discoverability

The About description should state the platform, four services, South African scope and SIMcloud API. Keep relevant lowercase topics for WordPress, WooCommerce, PHP, airtime, data, VAS, vouchers and prepaid electricity. GitHub allows up to 20 topics; avoid unrelated or repetitive additions. The repository website field points to the [installation guide](installation.md) on GitHub.

The README is the entry point. Keep one descriptive H1, a clear explanation, the current release status, an installable ZIP link, the illustrated guide and screenshots near the top. Use descriptive headings, meaningful link labels, relative internal links and accurate image alt text. Link advanced details rather than making new merchants read the architecture before installation.

GitHub controls the repository's HTML templates, metadata and indexing behaviour. README keyword repetition, a fake meta-tag block, a sitemap file or schema pasted into Markdown will not provide control over those templates. Search engines decide whether and where to show the repository; no ranking or indexing guarantee is made. Documentation remains on GitHub; this project currently has no GitHub Pages site.

## Social preview and branding

The editable source is [repository-banner.svg](branding/repository-banner.svg); the browser-rendered [PNG](branding/repository-banner.png) is 1280 × 640 pixels. Use the PNG in **Settings → General → Social preview → Edit → Upload an image**. Keep it under GitHub's 1 MB upload limit. The same artwork appears in the README so visitors can see it without sharing the link.

## Documentation maintenance

- Keep [installation](installation.md), [product setup](product-setup.md), [FAQ](faq.md) and [troubleshooting](troubleshooting.md) consistent with the released runtime.
- Refresh the [documentation index](README.md) whenever a guide is added or moved.
- Use actual application captures, with synthetic data and explicit provenance. Never illustrate an unimplemented feature as a working screen. Follow the [image record](images/README.md).
- Preserve the distinction between implemented behaviour, mocked verification and live supplier acceptance. Cite exact evidence in [verification](verification.md).
- Update download links and version labels for a new release. Keep release binaries immutable; documentation-only updates do not require silently replacing a ZIP.
- Run `python3 scripts/check_docs.py` and `git diff --check`; inspect GitHub's rendered README, guide images and navigation after publishing.

Documentation corrections can be submitted using the repository's documentation issue form. Contribution, support, security and conduct links should remain easy to find.

## Primary guidance

GitHub documents [repository customization](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository), [topics](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/classifying-your-repository-with-topics), [README presentation](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-readmes) and [social previews](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/customizing-your-repositorys-social-media-preview). Google's [helpful-content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content) supports writing useful instructions for readers rather than repeating keywords.

[← Documentation index](README.md)
