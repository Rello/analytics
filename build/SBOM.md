<!-- SPDX-FileCopyrightText: 2026 Marcel Scherello -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# SBOM security maintenance

The `SBOM security` workflow runs on pushes to `master`, manually, and every Monday at 06:23 UTC.
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

Dependabot checks Composer and GitHub Actions weekly and proposes updates. Security alerts and security
updates must also be enabled in repository settings. Composer updates do not refresh committed vendor
files. Before considering a fix complete, update the bundled libraries with the project's Composer/pruning
process, test them, and commit the resulting metadata and files. The generator uses installed vendor
metadata rather than claiming the versions in a newer lock file are already shipped. It warns if these differ.

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
