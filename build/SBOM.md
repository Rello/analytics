<!-- SPDX-FileCopyrightText: 2026 Marcel Scherello -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# SBOM security maintenance

The `SBOM security` workflow runs on relevant pushes to `master`, manually, and every Sunday at 06:23 UTC.
Pushes trigger it only when dependency manifests, bundled libraries, app metadata, the SBOM, its generator/scanner,
scanner tests, or the workflow itself change. Documentation-only pushes do not trigger it; manual and scheduled runs are unaffected.
Pushes and manual runs with `refresh` enabled regenerate and commit `sbom.cdx.json` before scanning.
Scheduled runs and manual runs with `refresh` disabled scan the existing SBOM without changing it.
Identical inventories retain their timestamp and UUID to avoid empty refresh commits.

The scanner sends only supported, exact Composer/npm package identifiers and versions to api.osv.dev.
It saves the SBOM and JSON report as workflow artifacts for 90 days. Unknown library versions and
platform requirements are listed as coverage gaps, not reported as clean. PHP and Nextcloud installed
versions require separate deployment scanning.

Each advisory/package pair has one `sbom-security` issue. Repeated findings do not add comments or
create duplicates. Successfully checked packages without the previous advisory resolve their issue;
missing packages, unsupported versions, and failed scans do not resolve issues. Recurring findings
reopen completed issues. To accept an inapplicable finding, explain why in a comment and close it as
**not planned**. Review such decisions when runtime usage changes. Reports still include those matches.
The workflow succeeds when scanning and issue synchronization succeed; findings themselves are tracked
in issues. It does not upgrade dependencies or merge PRs.

Dependabot checks Composer and GitHub Actions weekly and proposes version updates. Security alerts and
security updates must also be enabled in repository settings. Composer PRs update `composer.json` and/or
`composer.lock`; no workflow installs or commits bundled PHP libraries to those PRs.
The generator uses installed vendor metadata rather than claiming the versions in a newer lock file
are already shipped. It warns if these differ.

## Install PHP libraries locally

After pulling the reviewed Composer version updates, run from the repository root:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
```

`composer install` uses the exact versions in `composer.lock`. The existing `pre-autoload-dump` Composer
hook runs `build/prune-phpspreadsheet.php` automatically before generating the autoloader; keep scripts
enabled for this local command. To choose newer versions yourself, use `composer update --no-dev
--prefer-dist --optimize-autoloader` instead, which also updates the lock file.

Review the generated `vendor/` changes, run `./tests/run-unit.sh`, and commit the bundled libraries
along with any manifest changes. Until that local update is committed, the app still ships the old
bundled versions. The SBOM workflow inventories the bundled files after they are pushed.

## Manual verification

1. Run **SBOM security** on `master` with `refresh` enabled. Inspect its artifact and created issues.
2. Run again with `refresh` disabled to exercise the weekly path and confirm no duplicate issues.
3. Fix a bundled dependency and push to `master`. Check that the SBOM changes and resolved findings close.
4. Dependabot's manual trigger is under **Insights → Dependency graph → Dependabot → Recent update jobs → Check for updates**.

Local checks:

```sh
python3 -m unittest discover -s tests/sbom -p 'test_*.py'
sh build/generate-sbom.sh
python3 build/scan-sbom.py
```

The final command requires network access. `--issues` additionally requires an authenticated `gh` CLI,
`GITHUB_REPOSITORY`, and the `sbom-security` label. Do not use it for offline tests.

## SVG chart export dependency

`js/3rdParty/svgcanvas.esm.js` is the unchanged `dist/svgcanvas.esm.js` from
`https://registry.npmjs.org/svgcanvas/-/svgcanvas-2.6.0.tgz` (MIT; bundled license
in `js/3rdParty/svgcanvas.LICENSE`). The archive SHA-512 integrity is
`sha512-H/2iVTLyuLxbLNYArnTaR8RmCR7fi8Gx7Hul7+lnPcN/b6AXLS56iVzUudgfMmkKrF9+GvXm+YJlT9Tg4eIj3A==`.
The SBOM generator records the pinned version and hashes the shipped module.
When upgrading, replace the upstream distribution and update the pinned version together.
