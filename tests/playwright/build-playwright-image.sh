#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

PLAYWRIGHT_IMAGE="${1:-${PLAYWRIGHT_IMAGE:-analytics-playwright:local}}"
PLAYWRIGHT_VERSION="${PLAYWRIGHT_VERSION:-1.58.2}"

docker build \
  --build-arg "PLAYWRIGHT_VERSION=${PLAYWRIGHT_VERSION}" \
  -f "${ROOT_DIR}/tests/playwright/Dockerfile" \
  -t "${PLAYWRIGHT_IMAGE}" \
  "${ROOT_DIR}"
