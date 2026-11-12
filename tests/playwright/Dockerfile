# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later

FROM node:24-bookworm-slim

ARG PLAYWRIGHT_VERSION=1.58.2

ENV NODE_PATH=/app/node_modules \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

WORKDIR /app

# Include full Chromium and its headless shell so headed debugging remains available.
RUN npm install --omit=dev --no-save "playwright@${PLAYWRIGHT_VERSION}" \
  && npx playwright install --with-deps chromium \
  && npm cache clean --force \
  && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*

WORKDIR /work

ENTRYPOINT ["node"]
