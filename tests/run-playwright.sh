#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-analytics-playwright:local}"
BASE_URL="${BASE_URL:-http://host.docker.internal:8032/apps/analytics/}"
NC_USER="${NC_USER:-admin}"
NC_PASS="${NC_PASS:-admin}"
REPORT_NAME="${REPORT_NAME:-Playwright Regression}"

SCENARIO="${1:-full}"
START_AT="${2:-}"
FULL_RUN=0

case "${SCENARIO}" in
  full|regression|full-1x-2x)
    SCRIPT_PATH="tests/playwright/full_regression.js"
    FULL_RUN=1
    ;;
  10|smoke|navigation)
    SCRIPT_PATH="tests/playwright/10_navigation.js"
    ;;
  11|report|create|report-create)
    SCRIPT_PATH="tests/playwright/11_report.js"
    ;;
  12|group-create|groupcreate)
    SCRIPT_PATH="tests/playwright/12_group.js"
    ;;
  14|sidebar-data|data)
    SCRIPT_PATH="tests/playwright/14_add_del_data.js"
    ;;
  16|sidebar-options|report-options)
    SCRIPT_PATH="tests/playwright/16_report_options.js"
    ;;
  21|21-filter|filter)
    SCRIPT_PATH="tests/playwright/21_filter.js"
    ;;
  22|drilldown)
    SCRIPT_PATH="tests/playwright/22_drilldown.js"
    ;;
  23|sort)
    SCRIPT_PATH="tests/playwright/23_sort.js"
    ;;
  25|table-options)
    SCRIPT_PATH="tests/playwright/25_options_table.js"
    ;;
  26|chart-options)
    SCRIPT_PATH="tests/playwright/26_options_chart.js"
    ;;
  27|refresh|auto-refresh)
    SCRIPT_PATH="tests/playwright/27_options_refresh.js"
    ;;
  28|translate)
    SCRIPT_PATH="tests/playwright/28_options_translate.js"
    ;;
  29|top-n|group-top-n)
    SCRIPT_PATH="tests/playwright/29_group_top_n.js"
    ;;
  41|datasource-git|git)
    SCRIPT_PATH="tests/playwright/41_datasource_git.js"
    ;;
  42|datasource-json|json)
    SCRIPT_PATH="tests/playwright/42_datasource_json.js"
    ;;
  43|datasource-csv|csv)
    SCRIPT_PATH="tests/playwright/43_datasource_csv.js"
    ;;
  44|automation-dataload|column-picker|local-csv)
    SCRIPT_PATH="tests/playwright/44_automation_dataload_csv_column_picker.js"
    ;;
  45|automation-deletion|deletion-automation)
    SCRIPT_PATH="tests/playwright/45_automation_deletion.js"
    ;;
  50|share|navigation-share)
    SCRIPT_PATH="tests/playwright/50_navigation_share.js"
    ;;
  51|favorites|navigation-favorites)
    SCRIPT_PATH="tests/playwright/51_navigation_favorites.js"
    ;;
  91|91-delete|report-delete|delete)
    SCRIPT_PATH="tests/playwright/91_report_delete.js"
    ;;
  92|92-delete|group-delete|groupdelete)
    SCRIPT_PATH="tests/playwright/92_group_delete.js"
    ;;
  30|chart|modal)
    SCRIPT_PATH="tests/playwright/30_chart_options_modal.js"
    ;;
  31|thresholds|options-thresholds)
    SCRIPT_PATH="tests/playwright/31_options_thresholds.js"
    ;;
  *.js)
    SCRIPT_PATH="${SCENARIO}"
    ;;
  *)
    echo "Unknown scenario: ${SCENARIO}" >&2
    echo "Use: full|regression|10|smoke|navigation|11|report|create|report-create|12|group-create|14|sidebar-data|16|sidebar-options|21|filter|22|drilldown|23|sort|25|table-options|26|chart-options|27|refresh|28|translate|29|top-n|30|chart|modal|31|thresholds|options-thresholds|41|datasource-git|42|datasource-json|43|datasource-csv|44|automation-dataload|45|automation-deletion|50|share|navigation-share|51|favorites|navigation-favorites|91|report-delete|92|group-delete|<script.js>" >&2
    exit 2
    ;;
esac

if [[ ! -f "${ROOT_DIR}/${SCRIPT_PATH}" ]]; then
  echo "Script not found: ${SCRIPT_PATH}" >&2
  exit 2
fi

if ! docker image inspect "${PLAYWRIGHT_IMAGE}" >/dev/null 2>&1; then
  echo "Playwright image ${PLAYWRIGHT_IMAGE} is missing. Building a local self-contained runtime..." >&2
  "${SCRIPT_DIR}/playwright/build-playwright-image.sh" "${PLAYWRIGHT_IMAGE}"
fi

DOCKER_ARGS=(
  --rm
  --init
  --ipc=host
  --entrypoint node
  -v "${ROOT_DIR}:/work"
  -w /work
  -e NODE_PATH=/app/node_modules
  -e "BASE_URL=${BASE_URL}"
  -e "NC_USER=${NC_USER}"
  -e "NC_PASS=${NC_PASS}"
)

if [[ -n "${HEADLESS:-}" ]]; then
  DOCKER_ARGS+=(-e "HEADLESS=${HEADLESS}")
fi
if [[ -n "${REPORT_NAME:-}" ]]; then
  DOCKER_ARGS+=(-e "REPORT_NAME=${REPORT_NAME}")
fi
if [[ -n "${REPORT_SUBHEADER:-}" ]]; then
  DOCKER_ARGS+=(-e "REPORT_SUBHEADER=${REPORT_SUBHEADER}")
fi
if [[ -n "${START_AT}" ]]; then
  DOCKER_ARGS+=(-e "PLAYWRIGHT_START=${START_AT}")
fi
if [[ -n "${ARTIFACT_DIR:-}" ]]; then
  DOCKER_ARGS+=(-e "ARTIFACT_DIR=${ARTIFACT_DIR}")
elif [[ "${FULL_RUN}" -eq 1 ]]; then
  RUN_TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
  HOST_ARTIFACT_DIR="${ROOT_DIR}/tests/ui-artifacts/${RUN_TIMESTAMP}"
  mkdir -p "${HOST_ARTIFACT_DIR}"
  DOCKER_ARGS+=(-e "ARTIFACT_DIR=/work/tests/ui-artifacts/${RUN_TIMESTAMP}")
fi

docker run "${DOCKER_ARGS[@]}" "${PLAYWRIGHT_IMAGE}" "${SCRIPT_PATH}"
