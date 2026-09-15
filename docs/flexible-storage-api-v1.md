<!--
SPDX-FileCopyrightText: 2026 Marcel Scherello
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Flexible shared dataset API contract v1

This contract is relative to `/apps/analytics`. It is additive: datasets without
`storageMode: "flexible_shared"` remain `legacy` and keep the existing fixed-field
APIs and `analytics_facts` storage. Column references have the form `c_<server id>`
and remain stable across renames and display reordering.

## Dataset and schema endpoints

`POST /dataset/flexible` creates the dataset and its complete schema atomically.

```json
{
  "name": "Sales",
  "columns": [
    {"name":"Date","type":"date","role":"dimension","nullable":false},
    {"name":"Region","type":"text","role":"dimension","nullable":false},
    {"name":"Revenue","type":"decimal","role":"measure","nullable":true,"defaultAggregation":"sum"}
  ]
}
```

The `201` response and `GET /dataset/{id}` descriptor contain `id`, `name`,
`storageMode`, `schemaVersion`, and ordered `columns`. Each column contains `id`,
`ref`, `name`, `type`, `role`, `position`, `nullable`, and
`defaultAggregation`. `GET /dataset` adds `storageMode` and `schemaVersion` to
every summary without removing the legacy fields.

`PUT /dataset/{id}/schema` accepts `expectedSchemaVersion`, optional `name`, and
the complete ordered `columns` array. Existing definitions carry their `ref`;
new definitions omit it. Renames, reorderings, and aggregation defaults are
allowed on populated datasets. Type, role, nullability, and removals require an
empty dataset; a populated dataset may add only nullable measures. The response
is the new descriptor with an incremented schema version.

## Record endpoints

`POST /dataset/{id}/records` accepts complete records. Missing nullable values
become null and have no `analytics_flex_values` row. Empty text, decimal zero, and
boolean false remain stored values.

```json
{
  "schemaVersion": 1,
  "records": [
    {"values":{"c_101":"2026-09-01","c_102":"Germany","c_103":"120.00"}}
  ]
}
```

All dimension columns, ordered by numeric stable ID, form the record identity.
Duplicate identities in one request use the final valid occurrence. Existing
identities update; new identities insert. The response reports `insert`,
`update`, `delete`, `error`, and `schemaVersion`.

`PUT /dataset/{id}/records/{recordId}` accepts `schemaVersion` and one complete
`values` object. It retains the record ID and returns `409` if its new dimension
identity belongs to another record. `DELETE /dataset/{id}/records/{recordId}`
deletes the record and all its values.

Decimals are JSON strings or integers and are never converted through a PHP
float. `DECIMAL(30,10)` permits at most 20 integer digits and rejects non-zero
fractional digits beyond scale 10. Dates use `YYYY-MM-DD`; datetimes accept
ISO-8601 and are returned in UTC. Booleans accept JSON booleans, `0`/`1`, or the
strings `"false"`/`"true"` and use physical decimal `0`/`1` storage.

## Query endpoint and report mapping

`POST /dataset/{id}/query` executes owner-authorized previews. A raw query uses:

```json
{"aggregate":false,"columns":["c_101","c_102","c_103"],"limit":100,"offset":0}
```

A grouped query uses:

```json
{
  "aggregate": true,
  "dimensions": ["c_102"],
  "measures": [
    {"column":"c_103","aggregation":"sum"},
    {"column":"c_104","aggregation":"avg"}
  ],
  "filters": [{"column":"c_102","operator":"EQ","value":"Germany"}],
  "sort": [{"column":"c_103","direction":"DESC"}],
	"timeAggregation": {"column":"c_101","grouping":"month","mode":"summation"},
	"topN": {"dimension":"c_102","measure":"c_103","type":"top","number":10,"others":true},
  "limit": 50,
  "offset": 0
}
```

Supported aggregations are `sum`, `avg`, `min`, `max`, `count`, and
`count_distinct`. Supported filter operators are `EQ`, `GT`, `LT`, `IN`,
`LIKE`, `NOTLIKE`, and `BETWEEN`. Filters on one column are OR-combined; columns
are AND-combined. Sort columns must be projected. The maximum page size is 1000.
Time aggregation accepts projected `date`/`datetime` dimensions, groups weeks
from Monday in UTC, and applies `summation` or `average` to every returned
measure. Top-N requires explicit projected dimension and measure references;
`type` is `top` or `flop`. Both transformations run before stable sorting and
pagination.

Flexible internal reports always start with all dimensions and measures in
schema order. Table, chart, filter, drilldown, sorting, Top-N, and time
aggregation options may then adapt that report without overloading its header
metadata. Existing `filteroptions` use references as the filter key or
`dimension` value. Authenticated, shared, public, Panorama, Dashboard, and API
report output all pass through the same storage service.

Chart configuration is intentionally not tied to this storage mode. The shared
chart dialog offers the same row, column, and timestamp modes plus optional
column mapping for every report source. It uses stable column references when a
response supplies them and response indexes otherwise.

Responses preserve the familiar envelope and add metadata:

```json
{
  "storageMode":"flexible_shared",
  "schemaVersion":1,
  "header":["Region","Revenue","Cost"],
  "columnRefs":["c_102","c_103","c_104"],
  "dimensions":{"c_102":"Region"},
  "keyFigures":["Revenue","Cost"],
  "data":[["Germany","120.0000000000","80.0000000000"]],
  "queryProcessing":{"backendProcessed":true,"aggregation":true,"sorting":false,"topN":false,"timeAggregation":false,"pagination":true},
  "error":0
}
```

Empty results retain headers, definitions, and `data: []`. The processing marker
prevents a consumer from repeating backend sorting or aggregation.

Flexible threshold creation extends the existing `POST /threshold` payload
with required `sourceColumnRef: "c_<id>"`. The server validates that the
reference belongs to the report dataset. The legacy numeric `dimension` field
is retained for legacy reports and is not reinterpreted as a flexible column.

## Source mappings and loads

The `storageMapping` field is separate from datasource `option` on dataload read,
update, simulate, and execute APIs:

```json
{
  "schemaVersion":1,
  "sourceHeader":["Date","Region","Revenue","Cost","Comment","Unused"],
  "columns":[
    {"column":"c_101","sourceIndex":0},
    {"column":"c_102","sourceIndex":1},
    {"column":"c_103","sourceIndex":2},
    {"column":"c_104","sourceIndex":3},
    {"column":"c_105","sourceIndex":4}
  ]
}
```

Execution validates the exact effective header returned after datasource
transformations, target ownership, indexes, required mappings, and every typed
value before mutation. Unmapped source columns are ignored. Replacement loads
delete and insert inside the same transaction, so a failure preserves the last
complete dataset. Simulation adds `storageMapping` and `mappingPreview` with
`mappedFields`, `ignoredSourceColumns`, and `validationErrors`.

Clipboard imports add explicit `header`, `delimiter`, and `storageMapping`;
file imports add `storageMapping`. Scheduled and manual execution use the saved
mapping and do not change datasource-provider interfaces.

## Errors and lifecycle

Errors use an HTTP status plus a stable payload:

```json
{"error":{"code":"stale_schema","message":"The submitted records target an outdated schema.","details":{"expectedSchemaVersion":1,"currentSchemaVersion":2}}}
```

Validation errors use `400`, inaccessible objects `404`, and stale schema,
dimension collision, wrong/unknown storage modes, or changed source headers
`409`. Fixed-field write APIs reject flexible targets. Dataset status, deletion,
context indexing, report output, report export, and user migration dispatch by
storage mode. `flexible_dedicated` and request-supplied table names are rejected.

The canonical fixture is
[`tests/fixtures/flexible-sales-v1.json`](../tests/fixtures/flexible-sales-v1.json).
Its `mappingTemplate` uses `columnName` only as a fixture placeholder; replace
each name with the matching `ref` from the dataset creation response before
submitting the mapping.

## Database verification boundary

The existing Nextcloud compatibility workflow now runs
`tests/integration/flexible_storage_db.php` after installing and enabling the
app on SQLite, MariaDB, and PostgreSQL. The check verifies the additive schema,
exclusive shared-table writes, legacy-fact isolation, and two simultaneous
measure aggregates without join multiplication. Database engines may return
different textual scale for `DECIMAL` aggregates; the API deliberately exposes
canonical decimal strings instead of promising byte-identical driver output.
No cross-database performance equivalence is claimed; workload benchmarks are
required before selecting a future dedicated-storage threshold.
