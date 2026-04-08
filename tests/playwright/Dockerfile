# SPDX-FileCopyrightText: 2026 Marcel Scherello
# SPDX-License-Identifier: AGPL-3.0-or-later

ARG PLAYWRIGHT_VERSION=1.58.2
FROM mcr.microsoft.com/playwright:v${PLAYWRIGHT_VERSION}-noble

ARG PLAYWRIGHT_VERSION=1.58.2

WORKDIR /app

RUN npm install --omit=dev --no-save "playwright@${PLAYWRIGHT_VERSION}" \
  && npm cache clean --force

WORKDIR /work

ENV NODE_PATH=/app/node_modules

ENTRYPOINT ["node"]
