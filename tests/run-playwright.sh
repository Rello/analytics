#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcp/playwright:latest}"
BASE_URL="${BASE_URL:-http://host.docker.internal:8032/apps/analytics/}"
NC_USER="${NC_USER:-admin}"
NC_PASS="${NC_PASS:-admin}"

SCENARIO="${1:-10}"

case "${SCENARIO}" in
  10|smoke|navigation)
    SCRIPT_PATH="tests/ui_playwright_10_smoke_navigation.js"
    ;;
  20|report|create)
    SCRIPT_PATH="tests/ui_playwright_20_report_create.js"
    ;;
  30|chart|modal)
    SCRIPT_PATH="tests/ui_playwright_30_chart_options_modal.js"
    ;;
  check)
    SCRIPT_PATH="tests/ui_playwright_check.js"
    ;;
  report-create)
    SCRIPT_PATH="tests/ui_playwright_report_create.js"
    ;;
  *.js)
    SCRIPT_PATH="${SCENARIO}"
    ;;
  *)
    echo "Unknown scenario: ${SCENARIO}" >&2
    echo "Use: 10|smoke|navigation|20|report|create|30|chart|modal|check|report-create|<script.js>" >&2
    exit 2
    ;;
esac

if [[ ! -f "${ROOT_DIR}/${SCRIPT_PATH}" ]]; then
  echo "Script not found: ${SCRIPT_PATH}" >&2
  exit 2
fi

DOCKER_ARGS=(
  --rm
  --entrypoint node
  -v "${ROOT_DIR}:/work"
  -v "${ROOT_DIR}/.playwright-browsers:/ms-playwright"
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
if [[ -n "${ARTIFACT_DIR:-}" ]]; then
  DOCKER_ARGS+=(-e "ARTIFACT_DIR=${ARTIFACT_DIR}")
fi

docker run "${DOCKER_ARGS[@]}" "${PLAYWRIGHT_IMAGE}" "${SCRIPT_PATH}"
