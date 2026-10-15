<!--
SPDX-FileCopyrightText: 2026 Marcel Scherello
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# OCS reports API v1

All paths below are relative to `/ocs/v2.php/apps/analytics/api/v1`.
Authenticate as a Nextcloud user (for example with HTTP Basic authentication and
an app password), send `OCS-APIRequest: true`, and request JSON with
`Accept: application/json`. Responses use the standard `ocs.meta` / `ocs.data`
envelope. Existing `/apps/analytics/api/1.0` through `4.0` APIs are unchanged.

| Method | Path | Result in `ocs.data` |
| --- | --- | --- |
| GET | `/reports` | Array of accessible report summaries, excluding folders |
| GET | `/reports/{reportId}` | Report metadata and saved visualization/filter options |
| GET | `/reports/{reportId}/data` | Viewer data payload including rows, headers and report options |

Access includes owned reports and reports shared with the user, their groups,
or through shared report folders, following the report navigation access rules.
Public-link possession and panorama-only access do not grant standalone report
access through these endpoints. Missing, inaccessible, and folder IDs return
an OCS not-found error (HTTP 404 on OCS v2).

Metadata excludes datasource connection settings, owner IDs and share secrets.
The data endpoint uses the same backend pipeline as the report viewer, including
saved filters, dynamic filter variables, sorting and transformations for internal
and external datasources. It accepts no temporary filter or visualization
overrides. It returns the viewer's backend data, not rendered chart images or
client-side table formatting/pivot output. Datasource failures retain the viewer
payload's `error` field. Conditional caching is not enabled for this endpoint.

Example:

```sh
curl --user 'USER:APP_PASSWORD' \
  --header 'OCS-APIRequest: true' \
  --header 'Accept: application/json' \
  'https://cloud.example/ocs/v2.php/apps/analytics/api/v1/reports/42/data'
```

The machine-readable contract is maintained in the repository root `openapi.json`.
This repository currently has no configured OpenAPI extractor; keep the specification
in sync when changing the controller.
