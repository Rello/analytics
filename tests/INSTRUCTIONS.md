# Test Instructions

This project runs tests in containers for reproducibility.

## Prerequisites
- Docker daemon running locally

## Unit Tests (PHPUnit)
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
- Run the full test suite:
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
- `41`, `git`, `datasource-git`
- `42`, `json`, `datasource-json`
- `43`, `csv`, `datasource-csv`
- `44`, `automation-dataload`, `column-picker`, `local-csv`
- `45`, `automation-deletion`, `deletion-automation`
- `50`, `share`, `navigation-share`
- `51`, `favorites`, `navigation-favorites`
- `91`, `91-delete`, `report-delete`, `delete`
- `92`, `92-delete`, `group-delete`

Environment variables:
- `BASE_URL` (default: `http://host.docker.internal:8032/apps/analytics/`)
- `NC_USER` (default: `admin`)
- `NC_PASS` (default: `admin`)
- Optional pass-throughs for Playwright scripts:
  - `HEADLESS`
  - `REPORT_NAME`
  - `REPORT_SUBHEADER`
  - `GROUP_NAME`
  - `ARTIFACT_DIR`
- Shared-run recommendation:
  - `./tests/run-playwright.sh` defaults to the shared report name `Playwright Regression`.
  - Override `REPORT_NAME` only when you intentionally want a different shared report.

Artifacts:
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
