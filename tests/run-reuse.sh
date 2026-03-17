#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

REUSE_IMAGE="${REUSE_IMAGE:-fsfe/reuse}"

docker run --rm \
  -v "${ROOT_DIR}:/data" \
  "${REUSE_IMAGE}" \
  lint "$@"
