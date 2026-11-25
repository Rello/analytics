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
| GET | `/reports` | Accessible reports with `id`, `name`, `dataset`, `parent`, `type`; groups optional |
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
| GET | `/datasets` | Owned datasets: `id`, `name`, `dimension1`, `dimension2`, `value`, `parent`, `type` |
| POST | `/datasets/{datasetId}/data/add` | Existing V4 add/update result |
| POST | `/datasets/{datasetId}/data/delete` | Existing V4 deletion result, including `delete` count |

The OCS controller delegates these operations to the existing external API handlers.
The dataset list exposes `id`, `name`, `dimension1`, `dimension2`, `value`, `parent`, and `type`;
internal scheduling and storage fields are omitted. The legacy V3
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

## Native configuration operations

The additive configuration API uses the same `/api/v1` namespace and ordinary
Nextcloud authentication. Handlers adapt native JSON to the existing services;
those services retain their ownership, folder, schema and storage rules.

| Method | Path | Result / permission |
| --- | --- | --- |
| GET | `/datasources` | Existing enabled provider catalogue: `datasources`, `options`, `reportTemplates` |
| GET | `/reports/{reportId}/configuration` | Owner-only editable native configuration |
| POST | `/reports` | Create one report using the existing report creation service |
| PUT | `/reports/{reportId}` | Update owned report metadata, source settings and options |
| GET | `/datasets/{datasetId}` | Owned dataset metadata, storage mode and schema |
| POST | `/datasets` | Create one classic or flexible dataset |
| PUT | `/datasets/{datasetId}` | Update classic metadata or flexible name/schema |
| GET | `/datasets/{datasetId}/dataloads` | Owned dataset's dataload configurations |
| GET | `/dataloads/{dataloadId}` | Owned dataload configuration |
| POST | `/dataloads` | Configure one dataload; does not run it |
| PUT | `/dataloads/{dataloadId}` | Update owned dataload settings |
| GET | `/panoramas` | Accessible Panorama summaries, excluding folders |
| GET | `/panoramas/{panoramaId}` | Accessible Panorama with native `pages` and `filters` |
| POST | `/panoramas` | Create one Panorama |
| PUT | `/panoramas/{panoramaId}` | Update owned Panorama |
| POST | `/datasets/{datasetId}/records` | Existing flexible record upsert |
| PUT | `/datasets/{datasetId}/records/{recordId}` | Existing flexible record replacement |
| DELETE | `/datasets/{datasetId}/records/{recordId}` | Existing flexible record deletion |
| POST | `/datasets/{datasetId}/query` | Existing owner-authorized flexible query |

Creation returns HTTP 201; reads/updates return 200. Report/Panorama configuration
responses include `id` and a relative `url`; dataset/dataload responses include `id`.
Report configuration reads differ from existing shared metadata reads: they return
native option objects/arrays rather than JSON strings and do not expand text variables.
The existing report metadata/data routes and classic V4 data error semantics above
remain unchanged. Shared report read access never grants source configuration access,
dataset ownership or write access. Panorama reads/writes use `PanoramaService`;
report and picture access during viewing follows the existing viewer pipeline.

### Update, clearing and failure semantics

`PUT` is a partial update: omitted fields retain their saved values. Supplied
`chartoptions`, `dataoptions`, `filteroptions`, and `tableoptions` replace their
whole saved counterparts; they are not recursively merged. `null` clears a saved
report option, `{}` saves an empty option object (`[]` is also accepted; for
`dataoptions`, an empty array stays an array). Other JSON-compatible Chart.js settings
remain intact. Unsupported fields are rejected rather than silently ignored.

Source settings use `link` on reports and `option` on dataloads, both flat
objects of scalar provider settings (strings are the native persisted form). Native
UI source values are HTML-escaped internally and decoded at this API boundary.
Read responses expose only known non-credential fields; authentication, tokens,
custom headers, request bodies, unknown provider fields and credential-bearing
URLs (userinfo, query or fragment) are write-only. Ordinary remote URLs remain visible. Consequently a response can omit a URL
or path that the client supplied. Omitted write-only settings survive replacement
of a source object. Supplied keys override those preserved settings; a key value
of `null` clears that key to an empty string. `link:null` / `option:null`
clears the entire source object, including credentials. `{}` clears only visible
settings and preserves write-only settings. Report `type` and `dataset` remain
unchanged on updates, matching the existing report update service.
Never echo source credentials into report titles, subtitles, labels or native
visualization options: those fields are ordinary visible content.

Panorama `pages` and `filters` are complete replacement arrays; `[]` clears them.
`filters:null` also clears filters; `pages:null` is invalid. Omitted filters are
passed as null to the existing service so it preserves them. Supplied filters use
`PanoramaService`'s existing normalization and mapping validation. A dataload's `storageMapping` replaces its complete mapping;
`null` or `{}` clears it. Its `dataset` and `datasource` are immutable; configuration
updates do not move a load between providers/datasets. Report `type` and `dataset`
are also immutable after creation; live report creation requires `dataset:0`.

Missing, inaccessible and folder IDs yield HTTP 404; invalid configuration yields
400 with `ocs.data.error.code="INVALID_CONFIGURATION"` and `message`. Flexible
operations retain their documented codes/statuses (including schema conflicts,
HTTP 409). Authentication failures are 401. Check both the HTTP status and OCS
metadata; classic row writes additionally require `ocs.data.success=true`, and
report data responses can contain a datasource `error`. Existing server request
limits and flexible record/query limits apply. The adapter adds no separate
payload-size, Panorama page-count or text-length policy.

Each report, dataload or Panorama configuration write commits its own database
transaction. Flexible dataset/schema/record writes use the existing service's
transactions. There is no transaction across successive calls: a later failed
Panorama call leaves earlier successful reports usable. There are no new idempotency
keys or optimistic revisions. Inspect accessible objects after an ambiguous creation
timeout before retrying; names are not unique IDs. Concurrent configuration writes
use read/modify/write and may overwrite each other; read immediately before editing.
Only flexible schema/record operations retain their existing version checks.

### Native fields

Reports accept string `name`, nullable `subheader`, `parent` (owned report folder ID, default 0), `type` (default 2),
`dataset`, `link`, `visualization` (`chart`, `table`, `ct`; default `ct`),
`chart` (default `column`; supported values are empty, `column`,
`columnSt`, `columnSt100`, `line`, `datetime`, `area`, `doughnut`, `funnel`), `refresh` (nonnegative seconds, default 0),
`dimension1`, `dimension2`, `value` (column labels), and the four native option
fields above. Stored report creation with `dataset:0` uses the existing service's
implicit dataset creation. A nonzero dataset must be owned. The existing mapper
retains responsibility for database name truncation. Unsupported chart values are rejected with HTTP 400 before any write.
Use `column` instead of `bar`; for a solid pie use `chart:"doughnut"` with
`chartoptions:{"cutout":0}` instead of `chart:"pie"`. Provider IDs/options come from
`/datasources`, including providers registered by installed apps and excluding
administratively disabled providers. This catalogue reuses provider templates; it
adds no provider integration, file browser or cross-app discovery infrastructure.

Classic datasets accept `name`, `dimension1`, `dimension2`, `value`, and nullable
`subheader`. `storageMode` defaults to `legacy` and cannot be
changed after creation. Flexible creation uses `storageMode:"flexible_shared"`
and `columns`; updates require `expectedSchemaVersion` and optionally `name` and a
complete `columns` definition. Omitted columns retain the schema. Existing column
references (`c_<id>`) are stable across renames/reordering; use `ref` on existing
columns. Flexible records/query use the request/response and validation contracts
in [the flexible storage API](flexible-storage-api-v1.md), with the OCS prefix and
plural `/datasets` paths shown above. Classic row add/delete endpoints do not
accept flexible datasets. AI-index settings are preserved, not exposed as writes.

Dataload creation requires owned `dataset` and enabled `datasource`; optional fields
are `name`, `option`, `schedule` and `storageMapping`. Existing schedules are `""`
(disabled), `h` (hourly), `d` (daily), `e` (end of day), `b` (start of day); null
also disables scheduling. Exposing these fields adds no execution/scheduler API.
Live source configuration belongs to reports; loading into stored datasets belongs
to dataloads. Saving a load neither fetches nor populates data. Configuration writes do not
probe a source or confirm its credentials/content; provider execution and external
HTTP restrictions remain in the existing viewer/dataload pipeline.

Panoramas accept `name`, owned folder `parent` (default 0), `pages` and `filters`.
Pages are passed to `PanoramaService` without an additional tile validator,
permission policy or index renumbering. Native pages use `name`, `layoutId`, `reports`
and `page`. The existing service accepts layout IDs 0–6 and removes saved layout HTML.
The existing renderer's slot counts are
0 (blank, 0), 1 (2-1, 3), 2 (1-2, 3), 3 (2-2, 4), 4 (4-2, 6), 5 (1-1, 2), 6 (1, 1).
Tiles are `{type:0,value:<report ID>}`, `{type:1,value:<text>}`, or
`{type:2,value:<accessible PNG/JPEG file ID>}`. `null` reserves an empty slot.
Report tiles may have `options:{legend:false}`; other native fields pass through
to the existing service unchanged.
Filters use the existing native structure: `id`, `label`, boolean `enabled`, optional
boolean `showInHeader`, `operator` (`EQ`, `GT`, `LT`, `LIKE`, `NOTLIKE`, `IN`, `NOTIN`),
optional string `defaultValue`, and `mappings:[{reportId,dimension,dimensionLabel}]`.
Mappings must reference report tiles; an enabled dimension can belong to only one
filter. Labels and stable dimension references must match the report's data.

### Chart rendering contract

The bundled renderer uses Chart.js **4.4.9**. It combines themed defaults, custom
`chartoptions` and finally Analytics' GUI-derived patch from `__analytics_gui` and
series settings. The GUI namespace is removed before passing options to Chart.js;
`analyticsModel` and `scales.secondary` are managed paths. GUI model selection and
secondary-axis visibility take precedence. Doughnut rendering additionally hides
axes and supplies its own datalabel display/colour/formatter; funnel removes scales.
`dataoptions` accepts native per-series settings, including arrays, and is merged
with the computed series. `filteroptions` controls server filtering/transformation;
`tableoptions` controls table display. Preserve these independent objects when
editing only chart styling. JSON cannot express function-valued callbacks or plugin
implementations; callback source strings are not evaluated by this API. For example,
`chartoptions.elements.line.tension:0.37` is accepted and rendered after reload even
though it has no dedicated Analytics UI control.

### Sequential example

The following uses existing providers and real returned IDs. Use an app password;
`jq` extracts each result. The two classic rows are a snapshot, not a live connection.

```sh
API='https://cloud.example/ocs/v2.php/apps/analytics/api/v1'
ocs() { curl --fail-with-body --silent --user "$NC_USER:$NC_APP_PASSWORD" \
  -H 'OCS-APIRequest: true' -H 'Accept: application/json' \
  -H 'Content-Type: application/json' "$@"; }
DATASET=$(ocs -d '{"name":"Revenue","dimension1":"Region","dimension2":"Month","value":"Revenue"}' "$API/datasets" | jq -er '.ocs.data.id')
ocs -d '{"data":[{"dimension1":"North","dimension2":"2026-11","value":"42"}]}' "$API/datasets/$DATASET/data/add"
REPORT1=$(ocs -d "{\"name\":\"Revenue chart\",\"dataset\":$DATASET,\"chart\":\"line\",\"chartoptions\":{\"elements\":{\"line\":{\"tension\":0.37}}}}" "$API/reports" | jq -er '.ocs.data.id')
REPORT2=$(ocs -d "{\"name\":\"Revenue table\",\"dataset\":$DATASET,\"visualization\":\"table\"}" "$API/reports" | jq -er '.ocs.data.id')
ocs -X PUT -d '{"chartoptions":{"elements":{"line":{"tension":0.2}}}}' "$API/reports/$REPORT1"
ocs "$API/datasources"
# Save supported local CSV source settings; this does not execute the load.
ocs -d "{\"name\":\"CSV load\",\"dataset\":$DATASET,\"datasource\":1,\"option\":{\"link\":\"/Revenue.csv\",\"hasHeader\":\"true\"}}" "$API/dataloads"
# A live report reads an existing supported provider when viewed.
ocs -d '{"name":"Live CSV","type":1,"dataset":0,"link":{"link":"/Revenue.csv","hasHeader":"true"}}' "$API/reports"
PANORAMA=$(ocs -d "{\"name\":\"Revenue overview\",\"pages\":[{\"name\":\"Overview\",\"layoutId\":5,\"reports\":[{\"type\":0,\"value\":$REPORT1},{\"type\":0,\"value\":$REPORT2}]}]}" "$API/panoramas" | jq -er '.ocs.data.id')
ocs -X PUT -d "{\"pages\":[{\"name\":\"Reordered\",\"layoutId\":5,\"reports\":[{\"type\":0,\"value\":$REPORT2},{\"type\":0,\"value\":$REPORT1}]}]}" "$API/panoramas/$PANORAMA"
```

The automated development scenario `tests/playwright/ocs_configuration.js` exercises
these operations, flexible records/schema conflicts, explicit clearing, secret
preservation, later-call failure and rendering/reload. Ordinary user authentication
and permission checks are in `tests/integration/ocs-configuration-permissions.php`.

### Navigation groups

GET `/reports?includeGroups=true` and `/datasets?includeGroups=true` include
permission-filtered groups at every depth. Without the option groups are omitted.
`type:0` identifies a group; `parent` is its direct parent ID (0/null is root).
Follow parent IDs recursively to find descendants, with a visited-ID set. Never
send group IDs to data/configuration endpoints. Dataset and report group IDs belong
to separate namespaces. Duplicate names require resolving the full ancestry or
asking the user; do not pick the first match. Shared report access does not reveal
inaccessible ancestors or grant ownership of underlying datasets. A missing parent
in the returned list must not be treated as a discoverable private group.
