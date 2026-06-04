#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

PHPUNIT_IMAGE="${PHPUNIT_IMAGE:-php-with-phpunit:latest}"
PHPUNIT_VERSION="${PHPUNIT_VERSION:-9.6.22}"

docker build \
  --build-arg "PHPUNIT_VERSION=${PHPUNIT_VERSION}" \
  -t "${PHPUNIT_IMAGE}" \
  -f "${SCRIPT_DIR}/Dockerfile" \
  "${ROOT_DIR}"
