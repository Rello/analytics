<!--
SPDX-FileCopyrightText: 2026 Marcel Scherello
SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Analytics bundles (version 1)

A bundle is a JSON file imported using the normal report file picker or
`POST /apps/analytics/report/import/` with `path` (a file in the current user's
Nextcloud files) or `raw` (JSON text). Existing single-report files retain their
format, behavior, and integer response. Bundles return an object with `kind:
"bundle"`, key-to-ID maps for folders/datasets/reports/panoramas, and an optional
`panoramaId` entry point. The normal UI opens that Panorama after import.

`resources/demo.json` is the complete example and the first-start demo source.
It contains the saved six-tile Business performance Panorama, its seven reports
(including the raw sales report), and five datasets. Both KPI reports share one
dataset; Sales Ranking and Sales raw data share another. Definitions contain no
source user or database IDs. Import always creates an independent copy owned by
the current user and never matches or updates existing objects by name.

## Envelope and references

- `format`: exactly `analytics-bundle`.
- `version`: integer `1`; unsupported versions are rejected.
- `id`: optional descriptive package identifier, not an overwrite instruction.
- `folders`: objects with `key`, `name`, and `scope` (`datasets`, `reports`, or
  `panoramas`). Version 1 supports root folders only.
- `datasets`: objects with `key`, `name`, optional `folder` key, `dimension1`,
  `dimension2`, `value`, and optional `data` (three-element rows). Omitting data
  creates an empty dataset for a structure-only transfer.
- `reports`: objects with `key`, `name`, `dataset` key, optional `folder` key,
  `subheader`, `visualization`, `chart`, and optional `chartoptions`, `dataoptions`,
  `filteroptions`, `tableoptions`, and `thresholds`. Options are JSON objects/arrays,
  not JSON-encoded strings. Thresholds preserve array order and have `dimension`,
  `value`, `option`, `severity`, and `coloring`.
- `panoramas`: objects with `key`, `name`, optional `folder`, `pages`, and `filters`.
  Pages use existing `layoutId` values (1–6). Report tiles use `type: 0` and a report
  **key** in `value`; text tiles use `type: 1` and Markdown in `value`. Filter
  mappings use report **keys** in `reportId`. IDs are resolved during import.
- `entrypoint`: optional key of a Panorama to open after import.

Keys start with a letter, contain letters, digits, hyphens or underscores, and
are unique within each collection. References must resolve within the same
bundle. Current installation IDs, foreign datasets, image file IDs, unknown
layouts, and broken filter mappings are rejected. Validation precedes writes;
creation is transactional, so a write failure rolls back bundle objects.

## Initial scope

Version 1 supports classic stored datasets (two text dimensions and a numeric
measure), stored reports, display thresholds, and report/text Panorama tiles.
Classic storage has two decimal places; higher precision input is rejected rather
than silently rounded. The demo stores growth as percentage points and calculates
the ratio in the report before percentage formatting.

Flexible datasets, external sources/credentials, dataload schedules, notification
thresholds, image assets, shares, favorites, nested folders, and replacing an
existing installation are not part of this initial bundle format. The normal
single-report importer continues to support its existing formats, including
flexible datasets. A future exporter can produce this same versioned envelope;
unsupported capabilities should get explicit format support rather than embedding
installation-specific IDs or secrets.

## First-start installation

The wizard calls `POST /apps/analytics/demo`. The server loads the bundled JSON
and calls the same bundle creation service. A per-user lock and stored entry
Panorama ID make repeated clicks reopen the installed demo without overwriting
user edits. If that Panorama was deleted, installation creates a fresh copy.
The generic importer does not apply this duplicate prevention: importing the same
JSON twice intentionally creates two separate copies.

The test helper `tests/playwright/setup_business_demo.js` calls this same installer
and retains the result. `tests/playwright/bundle_import.js` checks ordinary JSON
and Nextcloud-file imports, invalid references, sharing, header filters, old
single-report imports, and first-start navigation/reuse. It removes only its test
objects; it does not modify the saved development demo.
