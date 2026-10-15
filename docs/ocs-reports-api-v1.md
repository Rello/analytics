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

## Dataset operations

| Method | Path | Result in `ocs.data` |
| --- | --- | --- |
| GET | `/datasets` | Owned datasets: `id`, `name`, `dimension1`, `dimension2`, `value` |
| POST | `/datasets/{datasetId}/data/add` | Existing V4 add/update result |
| POST | `/datasets/{datasetId}/data/delete` | Existing V4 deletion result, including `delete` count |

The OCS controller delegates these operations to the existing external API handlers.
The dataset list exposes only `id`, `name`, `dimension1`, `dimension2`, and `value`;
internal navigation, scheduling, and storage fields are omitted. The legacy V3
dataset response is unchanged.
Dataset writes require ownership and legacy storage; shared report access does not
allow dataset writes. Flexible datasets continue to use the stable-column record API.
The URL dataset ID takes precedence over any body parameter.

Add/update body (technical keys or configured dataset column names):

```json
{"data":[{"dimension1":"2026-10-15","dimension2":"Sales","value":"42"}]}
```

Delete body (a nonempty filter is required):

```json
{"filter":{"dimension1":{"option":"EQ","value":"2026-10-15"}}}
```

Deletion supports `dimension1`, `dimension2`, or their configured names, and the
V4 operators `EQ`, `GT`, `LT`, `IN`, `LIKE`, `NOTLIKE`, and `BETWEEN`, including
dynamic date variables. These endpoints retain V4 application error semantics:
HTTP 200 with `ocs.data.success=false` and `ocs.data.error.code/message`.
Codes are 9001 (unknown), 9002 (invalid input), 9003 (missing or unauthorized
dataset), and 9004 (unsupported storage mode). Check `success`, not just the OCS
metadata. Successful writes return `success=true` and a message; deletion also
returns the deleted row count. Authentication failures remain HTTP 401.
