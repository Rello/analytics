# Test Instructions

This project runs tests in containers for reproducibility.

## Prerequisites
- Docker daemon running locally
- From repository root: `/Users/Rello/Downloads/docker/apps/analytics`

## Unit Tests (PHPUnit)
- Run full suite:
  - `./tests/run-unit.sh`
- Run a specific test/class/filter:
  - `./tests/run-unit.sh --filter DatasourceControllerTest`

Notes:
- Default container image: `php-with-phpunit:latest`
- Override image if needed:
  - `PHPUNIT_IMAGE=my-image:tag ./tests/run-unit.sh`

## UI Tests (Playwright)
- Run scenario `10` (smoke/navigation):
  - `./tests/run-playwright.sh 10`
- Run scenario `20` (report creation PoC):
  - `./tests/run-playwright.sh 20`

Supported identifiers:
- `10`, `smoke`, `navigation`
- `20`, `report`, `create`
- or a direct script path, e.g. `tests/ui_playwright_20_report_create.js`

Environment variables:
- `BASE_URL` (default: `http://host.docker.internal:8032/apps/analytics/`)
- `NC_USER` (default: `admin`)
- `NC_PASS` (default: `admin`)
- Optional pass-throughs for Playwright scripts:
  - `HEADLESS`
  - `REPORT_NAME`
  - `REPORT_SUBHEADER`
  - `ARTIFACT_DIR`
- Override image if needed:
  - `PLAYWRIGHT_IMAGE=mcp/playwright:latest ./tests/run-playwright.sh 10`

Artifacts:
- Scenario `10`: `tests/ui-artifacts/10/`
- Scenario `20`: `tests/ui-artifacts/20/`
