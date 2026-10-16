# Test Instructions

This project runs tests in containers for reproducibility.

## Prerequisites
- Docker daemon running locally with host networking enabled for Playwright to reach Nextcloud 35 and Client Push

## Unit Tests (PHPUnit)
- Build the local PHPUnit image:
  - `./tests/phpunit/build-phpunit-image.sh`
- Run full suite:
  - `./tests/run-unit.sh`

Notes:
- Default container image: `php-with-phpunit:latest`
- Override image if needed:
  - `PHPUNIT_IMAGE=my-image:tag ./tests/run-unit.sh`

## UI Tests (Playwright)
- Default run:
  - `./tests/run-playwright.sh`
- The Playwright container is self-contained:
  - no local `.playwright-browsers` cache is required after cloning the repository
  - the wrapper builds the local `analytics-playwright:local` image automatically when it is missing
- Rebuild the local Playwright image manually:
  - `./tests/playwright/build-playwright-image.sh`
- Run the full test suite (stops at the first failure to avoid cascading failures in the shared report):
  - `./tests/run-playwright.sh full`
- Run single scenario e.g. `10` (smoke/navigation):
  - `./tests/run-playwright.sh 10`

Supported identifiers:
- `full`, `regression`
- `10`, `smoke`, `navigation`
- `11`, `report`, `create`, `report-create`
- `12`, `group-create`
- `14`, `sidebar-data`, `data`
- `16`, `sidebar-options`, `report-options`
- `21`, `21-filter`, `filter`
- `22`, `drilldown`
- `23`, `sort`
- `25`, `table-options`
- `26`, `chart-options`
- `27`, `refresh`, `auto-refresh`
- `28`, `translate`
- `29`, `top-n`, `group-top-n`
- `30`, `chart`, `modal`
- `31`, `thresholds`, `options-thresholds`
- `32`, `panorama-filters` (requires the panorama filters migration)
- `41`, `git`, `datasource-git`
- `42`, `json`, `datasource-json`
- `43`, `csv`, `datasource-csv`
- `44`, `automation-dataload`, `column-picker`, `local-csv`
- `45`, `automation-deletion`, `deletion-automation`
- `46`, `flexible-datasets`, `flexible-storage`
- `47`, `chart-mapping` (field roles, live-data preview, sampling, and dialog lifecycle)
- `48`, `chart-mapping-compatibility` (original report layouts, numeric dimensions, data formats, and unchanged dialog Apply)
- `49`, `table-preview` (table preview, formatting, draft lifecycle, pivot validation, and stable column references)
- `50`, `share`, `navigation-share`
- `51`, `favorites`, `navigation-favorites`
- `52`, `reference-assets` (isolated link-preview asset loading)
- `53`, `panorama-pdf-upload` (safe panorama PDF uploads and overwrite protection)
- `54`, `report-transformations` (shared calculated measures, hidden inputs, sorting, preview, and table-calculation migration)
- `55`, `dialog-keyboard-mobile` (keyboard navigation, modal focus, and mobile report creation)
- `56`, `demo-sales-ranking` (two demo reports sharing one dataset, raw table, Top N, mixed chart, pivot calculations, column order, formatting, indicators, and reload)
- `tests/playwright/datasource_preview.js` (live datasource previews in the wizard and sidebar, unsaved options, and error handling)
- `tests/playwright/dashboard_number_format.js` (standalone dashboard values, locales, and thresholds)
- `91`, `91-delete`, `report-delete`, `delete`
- `92`, `92-delete`, `group-delete`

Environment variables:
- `BASE_URL` (default: `http://localhost:8035/apps/analytics/`)
- `NC_USER` (default: `admin`)
- `NC_PASS` (default: `admin`)
- Optional pass-throughs for Playwright scripts:
  - `HEADLESS`
  - `REPORT_NAME`
  - `REPORT_SUBHEADER`
  - `GROUP_NAME`
  - `ARTIFACT_DIR`
- Report lifecycle:
  - Every full run generates a unique report and group name, printed at startup and shared by all scenarios in that run.
  - Standalone scenarios generate their own unique names by default.
  - Set `REPORT_NAME` and `GROUP_NAME` explicitly only to reuse an existing test run, such as when resuming with a starting scenario.
  - The filter scenario removes and saves its filters even when an assertion fails, and captures the cleared report as evidence.

Artifacts:
- After each run, the wrapper keeps that run's artifacts and removes older artifacts. An explicit `ARTIFACT_DIR` overrides this cleanup.
- Full run: `tests/ui-artifacts/<timestamp>/`
  - All screenshots for the chained run are written into that one folder.
  - Filenames are prefixed as `<number>_<current_title>_<capture>.png`.

## License Compliance (REUSE)
- Run REUSE lint:
  - `./tests/run-reuse.sh`

Notes:
- Default container image: `fsfe/reuse`
- Override image if needed:
  - `REUSE_IMAGE=fsfe/reuse:latest ./tests/run-reuse.sh`

## Legacy Panorama Layout Compatibility

This scenario creates a temporary panorama with the original saved HTML layout,
checks that all four reports render, saves it as `layoutId: 3`, and reloads it.
It deletes the temporary panorama afterwards and does not modify its referenced reports.
The local instance must run the current app version and have internal reports owned by `admin`.

```sh
mkdir -p tests/ui-artifacts/legacy-panorama
docker exec -i -u www-data nc35-app-1 php \
  < tests/playwright/fixtures/create_legacy_panorama.php \
  > tests/ui-artifacts/legacy-panorama/fixture.json
ARTIFACT_DIR=/work/tests/ui-artifacts/legacy-panorama \
  ./tests/run-playwright.sh tests/playwright/panorama_legacy_layout.js
```
