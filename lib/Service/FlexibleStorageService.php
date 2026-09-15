<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Service;

use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\FlexibleStorageMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Exception\FlexibleStorageException;
use OCA\Analytics\Storage\DatasetStorageResolver;
use OCA\Analytics\Storage\FlexibleColumnResolver;
use OCA\Analytics\Storage\FlexibleValueNormalizer;
use Psr\Log\LoggerInterface;

class FlexibleStorageService {
	public function __construct(
		private DatasetMapper $datasetMapper,
		private FlexibleStorageMapper $storageMapper,
		private ReportMapper $reportMapper,
		private DatasetStorageResolver $storageResolver,
		private FlexibleValueNormalizer $normalizer,
		private ThresholdService $thresholdService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<array<string,mixed>> $columns
	 * @return array<string,mixed>
	 */
	public function createDataset(string $name, array $columns): array {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 64) {
			throw new FlexibleStorageException('invalid_dataset_name', 'Dataset names must contain between 1 and 64 characters.', ['field' => 'name']);
		}
		$columns = $this->normalizeColumns($columns);

		$this->storageMapper->beginTransaction();
		try {
			$datasetId = $this->datasetMapper->createFlexible($name);
			$created = [];
			foreach ($columns as $column) {
				$created[] = $this->storageMapper->createColumn($datasetId, $column);
			}
			$this->storageMapper->commit();
		} catch (\Throwable $e) {
			$this->storageMapper->rollBack();
			if ($e instanceof FlexibleStorageException) {
				throw $e;
			}
			throw new FlexibleStorageException('dataset_create_failed', 'The flexible dataset could not be created.', [], 500, $e);
		}

		$dataset = $this->datasetMapper->readOwn($datasetId);
		return $this->descriptor(is_array($dataset) ? $dataset : ['id' => $datasetId, 'name' => $name, 'schema_version' => 1], $created);
	}

	/** @return array<string,mixed> */
	public function getDescriptor(int $datasetId, bool $ownerOnly = false): array {
		$resolved = $this->requireFlexible($datasetId, $ownerOnly);
		return $this->descriptor($resolved['dataset'], $this->storageMapper->getColumns($datasetId));
	}

	/**
	 * @param list<array<string,mixed>> $columns
	 * @return array<string,mixed>
	 */
	public function updateSchema(int $datasetId, int $expectedSchemaVersion, array $columns, ?string $name = null): array {
		$resolved = $this->requireFlexible($datasetId, true);
		if ($name !== null && (trim($name) === '' || mb_strlen(trim($name)) > 64)) {
			throw new FlexibleStorageException('invalid_dataset_name', 'Dataset names must contain between 1 and 64 characters.', ['field' => 'name']);
		}
		$currentVersion = (int)($resolved['dataset']['schema_version'] ?? 1);
		if ($expectedSchemaVersion !== $currentVersion) {
			throw new FlexibleStorageException('stale_schema', 'The dataset schema changed after it was loaded.', [
				'expectedSchemaVersion' => $expectedSchemaVersion,
				'currentSchemaVersion' => $currentVersion,
			], 409);
		}

		$normalized = $this->normalizeColumns($columns);
		$current = $this->storageMapper->getColumns($datasetId);
		$currentById = array_column($current, null, 'id');
		$count = (int)$this->storageMapper->getRecordCount($datasetId)['count'];
		$seen = [];
		foreach ($normalized as $position => &$column) {
			$requestedRef = $columns[$position]['ref'] ?? null;
			if ($requestedRef === null) {
				if ($count > 0 && ($column['role'] !== 'measure' || $column['nullable'] !== true)) {
					throw new FlexibleStorageException('schema_change_not_allowed', 'Only nullable measure columns can be added to a populated dataset.', ['position' => $position], 409);
				}
				$column['id'] = null;
				continue;
			}
			$existing = (new FlexibleColumnResolver($current))->resolve((string)$requestedRef);
			$column['id'] = (int)$existing['id'];
			if (isset($seen[$column['id']])) {
				throw new FlexibleStorageException('duplicate_column_reference', 'Each existing column reference may appear only once.', ['column' => $existing['ref']]);
			}
			$seen[$column['id']] = true;
			if ($count > 0 && (
				$column['type'] !== $existing['type']
				|| $column['role'] !== $existing['role']
				|| $column['nullable'] !== $existing['nullable']
			)) {
				throw new FlexibleStorageException('schema_change_not_allowed', 'Column type, role, and nullability can change only while the dataset is empty.', ['column' => $existing['ref']], 409);
			}
		}
		unset($column);
		if ($count > 0 && count($seen) !== count($currentById)) {
			throw new FlexibleStorageException('schema_change_not_allowed', 'Columns cannot be removed from a populated dataset.', [], 409);
		}

		$this->storageMapper->beginTransaction();
		try {
			foreach ($normalized as $column) {
				if ($column['id'] === null) {
					$this->storageMapper->createColumn($datasetId, $column);
				} else {
					$this->storageMapper->updateColumn($datasetId, $column['id'], $column);
				}
			}
			foreach ($currentById as $columnId => $column) {
				if (!isset($seen[$columnId])) {
					$this->storageMapper->deleteColumn($datasetId, (int)$columnId);
				}
			}
			$newVersion = $currentVersion + 1;
			$this->datasetMapper->updateFlexibleSchema($datasetId, $newVersion, $name);
			$this->storageMapper->commit();
		} catch (\Throwable $e) {
			$this->storageMapper->rollBack();
			if ($e instanceof FlexibleStorageException) {
				throw $e;
			}
			throw new FlexibleStorageException('schema_update_failed', 'The dataset schema could not be updated.', [], 500, $e);
		}

		$this->reportMapper->increaseVersionByDataset($datasetId);
		return $this->getDescriptor($datasetId, true);
	}

	/**
	 * @param list<array<string,mixed>> $records
	 * @return array<string,mixed>
	 */
	public function upsertRecords(int $datasetId, int $schemaVersion, array $records): array {
		$this->requireFlexible($datasetId, true);
		return $this->writeRecords($datasetId, $schemaVersion, $records, false);
	}

	/**
	 * @param list<array<string,mixed>> $records
	 * @return array<string,mixed>
	 */
	public function replaceRecord(int $datasetId, int $recordId, int $schemaVersion, array $records): array {
		$this->requireFlexible($datasetId, true);
		if (count($records) !== 1 || !$this->storageMapper->recordBelongsToDataset($datasetId, $recordId)) {
			throw new FlexibleStorageException('record_not_found', 'The record does not exist or is not accessible.', ['recordId' => $recordId], 404);
		}
		$prepared = array_values($this->prepareRecords($datasetId, $schemaVersion, $records));
		$collision = $this->storageMapper->findRecordId($datasetId, $prepared[0]['dimensionKey']);
		if ($collision !== null && $collision !== $recordId) {
			throw new FlexibleStorageException('dimension_key_collision', 'Another record already uses this dimension combination.', ['recordId' => $recordId, 'conflictingRecordId' => $collision], 409);
		}

		$this->storageMapper->beginTransaction();
		try {
			$this->storageMapper->updateRecord($datasetId, $recordId, $prepared[0]['dimensionKey']);
			$this->writeValues($datasetId, $recordId, $prepared[0]['values']);
			$this->storageMapper->commit();
		} catch (\Throwable $e) {
			$this->storageMapper->rollBack();
			throw new FlexibleStorageException('record_write_failed', 'The record could not be updated.', ['recordId' => $recordId], 500, $e);
		}
		$this->reportMapper->increaseVersionByDataset($datasetId);
		$this->evaluateThresholdsAfterCommit($datasetId, [['values' => $prepared[0]['values'], 'insert' => false]]);
		return ['recordId' => $recordId, 'schemaVersion' => $schemaVersion, 'updated' => 1];
	}

	public function deleteRecord(int $datasetId, int $recordId): array {
		$this->requireFlexible($datasetId, true);
		if (!$this->storageMapper->recordBelongsToDataset($datasetId, $recordId)) {
			throw new FlexibleStorageException('record_not_found', 'The record does not exist or is not accessible.', ['recordId' => $recordId], 404);
		}
		$this->storageMapper->beginTransaction();
		try {
			$this->storageMapper->deleteRecord($datasetId, $recordId);
			$this->storageMapper->commit();
		} catch (\Throwable $e) {
			$this->storageMapper->rollBack();
			throw new FlexibleStorageException('record_delete_failed', 'The record could not be deleted.', ['recordId' => $recordId], 500, $e);
		}
		$this->reportMapper->increaseVersionByDataset($datasetId);
		return ['recordId' => $recordId, 'deleted' => 1];
	}

	/**
	 * @param array<string,mixed> $query
	 * @return array<string,mixed>
	 */
	public function query(int $datasetId, array $query, bool $ownerOnly = false): array {
		$resolved = $this->requireFlexible($datasetId, $ownerOnly);
		$columns = $this->storageMapper->getColumns($datasetId);
		$columnResolver = new FlexibleColumnResolver($columns);
		$aggregate = (bool)($query['aggregate'] ?? false);
		$projection = $this->resolveProjection($query, $columnResolver, $aggregate);
		$filters = $this->resolveFilters($query['filters'] ?? [], $columnResolver);
		$sort = $this->resolveSort($query['sort'] ?? [], $columnResolver, $projection);
		$timeAggregation = $this->resolveTimeAggregation($query['timeAggregation'] ?? null, $columnResolver, $projection, $aggregate);
		$topN = $this->resolveTopN($query['topN'] ?? null, $columnResolver, $projection, $aggregate);
		$hasPostProcessing = $timeAggregation !== null || $topN !== null;
		$limit = max(1, min(1000, (int)($query['limit'] ?? 100)));
		$offset = max(0, (int)($query['offset'] ?? 0));
		$rows = $this->storageMapper->query(
			$datasetId,
			$projection,
			$filters,
			$hasPostProcessing ? [] : $sort,
			$aggregate,
			$hasPostProcessing ? 0 : $limit,
			$hasPostProcessing ? 0 : $offset,
		);

		$data = [];
		$recordIds = [];
		foreach ($rows as $row) {
			if (isset($row['record_id'])) {
				$recordIds[] = (int)$row['record_id'];
			}
			$values = [];
			foreach ($projection as $item) {
				$column = $item['column'];
				$values[] = $this->apiValue($column, $row[$column['ref']] ?? null, $aggregate, $item['aggregation']);
			}
			$data[] = $values;
		}
		if ($timeAggregation !== null) {
			$data = $this->applyTimeAggregation($data, $projection, $timeAggregation);
		}
		if ($topN !== null) {
			$data = $this->applyTopN($data, $projection, $topN);
		}
		if ($hasPostProcessing) {
			$data = $this->sortRows($data, $projection, $sort);
			$total = count($data);
			$data = array_slice($data, $offset, $limit);
		} else {
			$total = count($data);
		}

		$projectedColumns = array_map(static fn (array $item): array => $item['column'], $projection);
		$dimensions = [];
		$keyFigures = [];
		foreach ($projectedColumns as $column) {
			if ($column['role'] === 'dimension') {
				$dimensions[$column['ref']] = $column['name'];
			} else {
				$keyFigures[] = $column['name'];
			}
		}

		$response = [
			'storageMode' => DatasetStorageResolver::FLEXIBLE_SHARED,
			'schemaVersion' => (int)$resolved['dataset']['schema_version'],
			'header' => array_column($projectedColumns, 'name'),
			'columnRefs' => array_column($projectedColumns, 'ref'),
			'columns' => $projectedColumns,
			'dimensions' => $dimensions,
			'keyFigures' => $keyFigures,
			'data' => $data,
			'error' => 0,
			'queryProcessing' => [
				'backendProcessed' => true,
				'aggregation' => $aggregate,
				'sorting' => $sort !== [],
				'pagination' => true,
				'limit' => $limit,
				'offset' => $offset,
				'returned' => count($data),
				'totalAfterProcessing' => $hasPostProcessing ? $total : null,
				'topN' => $topN !== null,
				'timeAggregation' => $timeAggregation !== null,
			],
		];
		if (!$aggregate) {
			$response['recordIds'] = $recordIds;
		}
		return $response;
	}

	/** @param array<string,mixed>|null $reportMetadata */
	public function queryForReport(int $datasetId, ?array $reportMetadata): array {
		$columns = $this->storageMapper->getColumns($datasetId);
		$query = [
			'aggregate' => true,
			'dimensions' => array_column(array_filter($columns, static fn (array $column): bool => $column['role'] === 'dimension'), 'ref'),
			'measures' => array_values(array_map(
				static fn (array $column): array => ['column' => $column['ref'], 'aggregation' => $column['defaultAggregation']],
				array_filter($columns, static fn (array $column): bool => $column['role'] === 'measure')
			)),
			'filters' => [],
			'limit' => 1000,
		];

		$filterOptions = is_array($reportMetadata) ? json_decode((string)($reportMetadata['filteroptions'] ?? ''), true) : null;
		if (is_array($filterOptions)) {
			if (($filterOptions['aggregate'] ?? true) === false) {
				$query['aggregate'] = false;
				$query['columns'] = array_merge(
					$query['dimensions'],
					array_map(static fn (array|string $measure): string => is_array($measure) ? (string)$measure['column'] : (string)$measure, $query['measures'])
				);
			}
			foreach (($filterOptions['filter'] ?? []) as $key => $filter) {
				if (!is_array($filter)) {
					continue;
				}
				$reference = (string)($filter['dimension'] ?? $key);
				if (preg_match('/^c_[1-9][0-9]*$/D', $reference)) {
					$query['filters'][] = ['column' => $reference, 'operator' => $filter['option'] ?? 'EQ', 'value' => $filter['value'] ?? null];
				}
			}
			foreach (array_keys($filterOptions['drilldown'] ?? []) as $reference) {
				$query['dimensions'] = array_values(array_filter($query['dimensions'], static fn (string $item): bool => $item !== $reference));
			}
			if (is_array($filterOptions['timeAggregation'] ?? null)) {
				$query['timeAggregation'] = $filterOptions['timeAggregation'];
			}
			if (is_array($filterOptions['topN'] ?? null)) {
				$query['topN'] = $filterOptions['topN'];
			}
			if (is_array($filterOptions['sort'] ?? null)) {
				$sortReference = (string)($filterOptions['sort']['column'] ?? $filterOptions['sort']['dimension'] ?? '');
				if (preg_match('/^c_[1-9][0-9]*$/D', $sortReference) === 1) {
					$query['sort'] = [[
						'column' => $sortReference,
						'direction' => strtoupper((string)($filterOptions['sort']['direction'] ?? 'ASC')),
					]];
				}
			}
		}

		return $this->query($datasetId, $query, false);
	}

	/**
	 * @param array<string,mixed>|string|null $mapping
	 * @param list<mixed> $header
	 * @param list<list<mixed>> $rows
	 * @return array<string,mixed>
	 */
	public function executeMappedLoad(int $datasetId, mixed $mapping, array $header, array $rows, bool $replace): array {
		$resolved = $this->requireFlexible($datasetId, false);
		$mapping = $this->decodeMapping($mapping);
		$records = $this->mapSourceRows($datasetId, $mapping, $header, $rows);
		return $this->writeRecords($datasetId, (int)$resolved['dataset']['schema_version'], $records, $replace);
	}

	/** @return array<string,mixed> */
	public function previewMapping(int $datasetId, mixed $mapping, array $header, array $rows): array {
		try {
			$mapping = $this->decodeMapping($mapping);
			$records = $this->mapSourceRows($datasetId, $mapping, $header, $rows);
			$resolved = $this->requireFlexible($datasetId, false);
			$this->prepareRecords($datasetId, (int)$resolved['dataset']['schema_version'], $records);
			$used = array_map(static fn (array $entry): int => (int)$entry['sourceIndex'], $mapping['columns']);
			return [
				'mappedFields' => $mapping['columns'],
				'ignoredSourceColumns' => array_values(array_filter(array_map(static fn ($name, int $index): array => ['sourceIndex' => $index, 'name' => $name], $header, array_keys($header)), static fn (array $entry): bool => !in_array($entry['sourceIndex'], $used, true))),
				'validationErrors' => [],
			];
		} catch (FlexibleStorageException $e) {
			return ['mappedFields' => [], 'ignoredSourceColumns' => [], 'validationErrors' => [$e->toResponse()['error']]];
		}
	}

	public function deleteDataset(int $datasetId): void {
		$this->storageMapper->deleteDataset($datasetId);
	}

	public function getRecordCount(int $datasetId): array {
		return $this->storageMapper->getRecordCount($datasetId);
	}

	public function deleteWithFilter(int $datasetId, array $options, bool $simulate = false): array|int {
		$this->requireFlexible($datasetId, false);
		$columns = $this->storageMapper->getColumns($datasetId);
		$filters = [];
		foreach (($options['filter'] ?? []) as $key => $filter) {
			if (!is_array($filter)) {
				continue;
			}
			$reference = (string)($filter['dimension'] ?? $key);
			$filters[] = [
				'column' => $reference,
				'operator' => $filter['option'] ?? 'EQ',
				'value' => $filter['value'] ?? null,
			];
		}
		$recordIds = [];
		$offset = 0;
		do {
			$response = $this->query($datasetId, [
				'aggregate' => false,
				'columns' => [$columns[0]['ref']],
				'filters' => $filters,
				'limit' => 1000,
				'offset' => $offset,
			], false);
			$recordIds = array_merge($recordIds, $response['recordIds']);
			$offset += count($response['recordIds']);
		} while (count($response['recordIds']) === 1000);

		if ($simulate) {
			return ['count' => count($recordIds)];
		}
		$this->storageMapper->beginTransaction();
		try {
			foreach ($recordIds as $recordId) {
				$this->storageMapper->deleteRecord($datasetId, (int)$recordId);
			}
			$this->storageMapper->commit();
		} catch (\Throwable $e) {
			$this->storageMapper->rollBack();
			throw new FlexibleStorageException('record_delete_failed', 'No records were deleted because the filtered deletion failed.', [], 500, $e);
		}
		if ($recordIds !== []) {
			$this->reportMapper->increaseVersionByDataset($datasetId);
		}
		return count($recordIds);
	}

	public function getLastUpdate(int $datasetId): int {
		return $this->storageMapper->getLastUpdate($datasetId);
	}

	/** @return array{columns:list<array<string,mixed>>,records:list<array<string,mixed>>} */
	public function exportDataset(int $datasetId): array {
		$columns = $this->storageMapper->getColumns($datasetId);
		$records = [];
		$offset = 0;
		do {
			$response = $this->query($datasetId, ['aggregate' => false, 'columns' => array_column($columns, 'ref'), 'limit' => 1000, 'offset' => $offset], false);
			foreach ($response['data'] as $index => $row) {
				$records[] = ['recordId' => $response['recordIds'][$index], 'values' => array_combine($response['columnRefs'], $row)];
			}
			$returned = count($response['data']);
			$offset += $returned;
		} while ($returned === 1000);
		return ['columns' => $columns, 'records' => $records];
	}

	/**
	 * @param list<array<string,mixed>> $records
	 * @return array<string,mixed>
	 */
	private function writeRecords(int $datasetId, int $schemaVersion, array $records, bool $replace): array {
		$prepared = $this->prepareRecords($datasetId, $schemaVersion, $records);
		$inserted = 0;
		$updated = 0;
		$deleted = 0;
		$thresholdEvents = [];
		$this->storageMapper->beginTransaction();
		try {
			if ($replace) {
				$deleted = $this->storageMapper->deleteRecords($datasetId);
			}
			foreach ($prepared as $record) {
				$recordId = $this->storageMapper->findRecordId($datasetId, $record['dimensionKey']);
				$isInsert = $recordId === null;
				if ($recordId === null) {
					$recordId = $this->storageMapper->createRecord($datasetId, $record['dimensionKey']);
					$inserted++;
				} else {
					$this->storageMapper->updateRecord($datasetId, $recordId, $record['dimensionKey']);
					$updated++;
				}
				$this->writeValues($datasetId, $recordId, $record['values']);
				$thresholdEvents[] = ['values' => $record['values'], 'insert' => $isInsert];
			}
			$this->storageMapper->commit();
		} catch (\Throwable $e) {
			$this->storageMapper->rollBack();
			if ($e instanceof FlexibleStorageException) {
				throw $e;
			}
			throw new FlexibleStorageException('record_write_failed', 'No records were changed because the submitted load could not be stored.', [], 500, $e);
		}
		if ($inserted > 0 || $updated > 0 || $deleted > 0) {
			$this->reportMapper->increaseVersionByDataset($datasetId);
		}
		$this->evaluateThresholdsAfterCommit($datasetId, $thresholdEvents);
		return ['schemaVersion' => $schemaVersion, 'insert' => $inserted, 'update' => $updated, 'delete' => $deleted, 'error' => 0];
	}

	/** @param list<array{values:array<int,array{canonical:mixed,text_value:?string,decimal_value:?string,datetime_value:?string}|null>,insert:bool}> $events */
	private function evaluateThresholdsAfterCommit(int $datasetId, array $events): void {
		if ($events === []) {
			return;
		}
		$records = [];
		foreach ($events as $event) {
			$referencedValues = [];
			foreach ($event['values'] as $columnId => $value) {
				$referencedValues['c_' . $columnId] = $value['canonical'] ?? null;
			}
			$records[] = ['values' => $referencedValues, 'insert' => $event['insert']];
		}
		try {
			$this->thresholdService->validateFlexibleRecords($datasetId, $records);
		} catch (\Throwable $e) {
			$this->logger->warning('Flexible dataset threshold evaluation failed after the record commit', [
				'datasetId' => $datasetId,
				'exception' => $e,
			]);
		}
	}

	/**
	 * @param list<array<string,mixed>> $records
	 * @return array<string,array{dimensionKey:string,values:array<int,mixed>}>
	 */
	private function prepareRecords(int $datasetId, int $schemaVersion, array $records): array {
		$resolved = $this->requireFlexible($datasetId, false);
		$currentVersion = (int)$resolved['dataset']['schema_version'];
		if ($schemaVersion !== $currentVersion) {
			throw new FlexibleStorageException('stale_schema', 'The submitted records target an outdated schema.', ['expectedSchemaVersion' => $schemaVersion, 'currentSchemaVersion' => $currentVersion], 409);
		}
		$columns = $this->storageMapper->getColumns($datasetId);
		$columnResolver = new FlexibleColumnResolver($columns);
		$dimensions = array_values(array_filter($columns, static fn (array $column): bool => $column['role'] === 'dimension'));
		if ($dimensions === []) {
			throw new FlexibleStorageException('missing_dimension', 'Flexible datasets require at least one dimension column.');
		}

		$prepared = [];
		foreach ($records as $recordIndex => $record) {
			$input = $record['values'] ?? null;
			if (!is_array($input)) {
				throw new FlexibleStorageException('invalid_record', 'Each record must contain a values object.', ['record' => $recordIndex]);
			}
			foreach (array_keys($input) as $reference) {
				$columnResolver->resolve((string)$reference);
			}
			$values = [];
			foreach ($columns as $column) {
				$value = array_key_exists($column['ref'], $input) ? $input[$column['ref']] : null;
				$values[(int)$column['id']] = $this->normalizer->normalizeValue($column, $value, $recordIndex);
			}
			$key = $this->normalizer->dimensionKey($dimensions, $values);
			$prepared[$key] = ['dimensionKey' => $key, 'values' => $values];
		}
		return $prepared;
	}

	/** @param array<int,mixed> $values */
	private function writeValues(int $datasetId, int $recordId, array $values): void {
		foreach ($values as $columnId => $value) {
			$this->storageMapper->writeValue($datasetId, $recordId, (int)$columnId, $value);
		}
	}

	/**
	 * @param array<string,mixed> $query
	 * @return list<array{column:array<string,mixed>,aggregation:string}>
	 */
	private function resolveProjection(array $query, FlexibleColumnResolver $resolver, bool $aggregate): array {
		$projection = [];
		if (!$aggregate) {
			$references = $query['columns'] ?? array_column($resolver->all(), 'ref');
			if (!is_array($references) || $references === []) {
				throw new FlexibleStorageException('invalid_projection', 'At least one column must be selected.', ['field' => 'columns']);
			}
			foreach ($references as $reference) {
				$projection[] = ['column' => $resolver->resolve((string)$reference), 'aggregation' => ''];
			}
			return $projection;
		}

		foreach (($query['dimensions'] ?? []) as $reference) {
			$column = $resolver->resolve((string)$reference);
			if ($column['role'] !== 'dimension') {
				throw new FlexibleStorageException('invalid_projection', 'Grouped dimensions must reference dimension columns.', ['column' => $column['ref']]);
			}
			$projection[] = ['column' => $column, 'aggregation' => ''];
		}
		foreach (($query['measures'] ?? []) as $measure) {
			$reference = is_array($measure) ? ($measure['column'] ?? '') : $measure;
			$column = $resolver->resolve((string)$reference);
			if ($column['role'] !== 'measure') {
				throw new FlexibleStorageException('invalid_projection', 'Aggregated measures must reference measure columns.', ['column' => $column['ref']]);
			}
			$aggregation = strtolower((string)(is_array($measure) ? ($measure['aggregation'] ?? $column['defaultAggregation']) : $column['defaultAggregation']));
			if (!in_array($aggregation, FlexibleValueNormalizer::AGGREGATIONS, true)) {
				throw new FlexibleStorageException('invalid_aggregation', 'Unsupported measure aggregation.', ['column' => $column['ref'], 'aggregation' => $aggregation]);
			}
			if (in_array($aggregation, ['sum', 'avg'], true) && !in_array($column['type'], ['decimal', 'boolean'], true)) {
				throw new FlexibleStorageException('invalid_aggregation', 'SUM and AVG require a numeric measure.', ['column' => $column['ref'], 'aggregation' => $aggregation]);
			}
			$projection[] = ['column' => $column, 'aggregation' => $aggregation];
		}
		if ($projection === []) {
			throw new FlexibleStorageException('invalid_projection', 'At least one dimension or measure must be selected.');
		}
		return $projection;
	}

	/** @return list<array{column:array<string,mixed>,operator:string,value:mixed}> */
	private function resolveFilters(mixed $filters, FlexibleColumnResolver $resolver): array {
		if (!is_array($filters)) {
			throw new FlexibleStorageException('invalid_filter', 'Filters must be an array.');
		}
		$result = [];
		foreach ($filters as $index => $filter) {
			if (!is_array($filter)) {
				throw new FlexibleStorageException('invalid_filter', 'Each filter must be an object.', ['filter' => $index]);
			}
			$column = $resolver->resolve((string)($filter['column'] ?? ''));
			$operator = strtoupper((string)($filter['operator'] ?? 'EQ'));
			if (!in_array($operator, ['EQ', 'GT', 'LT', 'IN', 'LIKE', 'NOTLIKE', 'BETWEEN'], true)) {
				throw new FlexibleStorageException('invalid_filter', 'Unsupported filter operator.', ['filter' => $index, 'operator' => $operator]);
			}
			$value = $filter['value'] ?? null;
			if (in_array($operator, ['LIKE', 'NOTLIKE'], true)) {
				if ($column['type'] !== 'text' || !is_string($value)) {
					throw new FlexibleStorageException('invalid_filter', 'LIKE filters require a text column and string value.', ['filter' => $index]);
				}
			} elseif ($operator === 'IN') {
				if (is_string($value)) {
					preg_match_all("/'(?:[^'\\\\]|\\\\.)*'|[^,;]+/", $value, $matches);
					$value = array_map(static fn (string $item): string => trim($item, " '\t\n\r\0\x0B"), $matches[0]);
				}
				if (!is_array($value) || $value === []) {
					throw new FlexibleStorageException('invalid_filter', 'IN filters require a non-empty array.', ['filter' => $index]);
				}
				$value = array_map(fn (mixed $item): mixed => $this->filterValue($column, $item, $index), $value);
			} elseif ($operator === 'BETWEEN') {
				if (!is_array($value) || count($value) !== 2) {
					throw new FlexibleStorageException('invalid_filter', 'BETWEEN filters require exactly two values.', ['filter' => $index]);
				}
				$value = [$this->filterValue($column, $value[0], $index), $this->filterValue($column, $value[1], $index)];
			} elseif ($value !== null) {
				$value = $this->filterValue($column, $value, $index);
			}
			$result[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
		}
		return $result;
	}

	private function filterValue(array $column, mixed $value, int $index): mixed {
		$normalized = $this->normalizer->normalizeValue($column + ['nullable' => true], $value, $index);
		return $normalized === null ? null : $normalized[FlexibleColumnResolver::valueField($column)];
	}

	/** @param list<array{column:array<string,mixed>,aggregation:string}> $projection */
	private function resolveSort(mixed $sort, FlexibleColumnResolver $resolver, array $projection): array {
		if (!is_array($sort)) {
			throw new FlexibleStorageException('invalid_sort', 'Sort options must be an array.');
		}
		$selected = array_column(array_column($projection, 'column'), 'ref');
		$result = [];
		foreach ($sort as $index => $item) {
			if (!is_array($item)) {
				throw new FlexibleStorageException('invalid_sort', 'Each sort option must be an object.', ['sort' => $index]);
			}
			$column = $resolver->resolve((string)($item['column'] ?? ''));
			if (!in_array($column['ref'], $selected, true)) {
				throw new FlexibleStorageException('invalid_sort', 'Sorting requires the column to be projected.', ['column' => $column['ref']]);
			}
			$direction = strtoupper((string)($item['direction'] ?? 'ASC'));
			if (!in_array($direction, ['ASC', 'DESC'], true)) {
				throw new FlexibleStorageException('invalid_sort', 'Sort direction must be ASC or DESC.', ['sort' => $index]);
			}
			$result[] = ['column' => $column, 'direction' => $direction];
		}
		return $result;
	}

	/** @param list<array{column:array<string,mixed>,aggregation:string}> $projection */
	private function resolveTimeAggregation(mixed $option, FlexibleColumnResolver $resolver, array $projection, bool $aggregate): ?array {
		if ($option === null || $option === []) {
			return null;
		}
		if (!$aggregate || !is_array($option)) {
			throw new FlexibleStorageException('invalid_time_aggregation', 'Time aggregation requires an aggregated query.');
		}
		$column = $resolver->resolve((string)($option['column'] ?? $option['dimension'] ?? ''));
		$selected = array_column(array_column($projection, 'column'), 'ref');
		if ($column['role'] !== 'dimension' || !in_array($column['type'], ['date', 'datetime'], true) || !in_array($column['ref'], $selected, true)) {
			throw new FlexibleStorageException('invalid_time_aggregation', 'Time aggregation requires a projected date or datetime dimension.', ['column' => $column['ref']]);
		}
		$hasMeasure = false;
		foreach ($projection as $item) {
			$hasMeasure = $hasMeasure || $item['column']['role'] === 'measure';
			if ($item['column']['role'] === 'measure' && !$this->hasNumericAggregateResult($item)) {
				throw new FlexibleStorageException('invalid_time_aggregation', 'Time aggregation requires numeric measure results.', ['column' => $item['column']['ref']]);
			}
		}
		if (!$hasMeasure) {
			throw new FlexibleStorageException('invalid_time_aggregation', 'Time aggregation requires at least one projected measure.');
		}
		$grouping = strtolower((string)($option['grouping'] ?? ''));
		$mode = strtolower((string)($option['mode'] ?? 'summation'));
		if (!in_array($grouping, ['day', 'week', 'month', 'year'], true) || !in_array($mode, ['summation', 'average'], true)) {
			throw new FlexibleStorageException('invalid_time_aggregation', 'Unsupported time grouping or aggregation mode.', ['grouping' => $grouping, 'mode' => $mode]);
		}
		return ['column' => $column, 'grouping' => $grouping, 'mode' => $mode];
	}

	/** @param list<array{column:array<string,mixed>,aggregation:string}> $projection */
	private function resolveTopN(mixed $option, FlexibleColumnResolver $resolver, array $projection, bool $aggregate): ?array {
		if ($option === null || $option === [] || (is_array($option) && ($option['type'] ?? 'none') === 'none')) {
			return null;
		}
		if (!$aggregate || !is_array($option)) {
			throw new FlexibleStorageException('invalid_top_n', 'Top-N requires an aggregated query.');
		}
		$dimension = $resolver->resolve((string)($option['dimension'] ?? ''));
		$measure = $resolver->resolve((string)($option['measure'] ?? ''));
		$selected = array_column(array_column($projection, 'column'), 'ref');
		$projectionByRef = [];
		foreach ($projection as $item) {
			$projectionByRef[$item['column']['ref']] = $item;
		}
		$type = strtolower((string)($option['type'] ?? ''));
		$number = (int)($option['number'] ?? 0);
		if (
			$dimension['role'] !== 'dimension'
			|| $measure['role'] !== 'measure'
			|| !in_array($dimension['ref'], $selected, true)
			|| !in_array($measure['ref'], $selected, true)
			|| !in_array($type, ['top', 'flop'], true)
			|| $number < 1
			|| $number > 1000
		) {
			throw new FlexibleStorageException('invalid_top_n', 'Top-N requires projected dimension and measure references, top/flop type, and a number from 1 to 1000.');
		}
		if (!$this->hasNumericAggregateResult($projectionByRef[$measure['ref']])) {
			throw new FlexibleStorageException('invalid_top_n', 'The Top-N ranking measure must produce a numeric result.', ['column' => $measure['ref']]);
		}
		if (($option['others'] ?? false) === true) {
			foreach ($projection as $item) {
				if ($item['column']['role'] === 'measure' && !$this->hasNumericAggregateResult($item)) {
					throw new FlexibleStorageException('invalid_top_n', 'Grouping remaining Top-N rows requires numeric measure results.', ['column' => $item['column']['ref']]);
				}
			}
		}
		return [
			'dimension' => $dimension,
			'measure' => $measure,
			'type' => $type,
			'number' => $number,
			'others' => ($option['others'] ?? false) === true,
		];
	}

	/** @param array{column:array<string,mixed>,aggregation:string} $item */
	private function hasNumericAggregateResult(array $item): bool {
		return in_array($item['column']['type'], ['decimal', 'boolean'], true)
			|| in_array($item['aggregation'], ['count', 'count_distinct'], true);
	}

	/**
	 * @param list<list<mixed>> $data
	 * @param list<array{column:array<string,mixed>,aggregation:string}> $projection
	 * @return list<list<mixed>>
	 */
	private function applyTimeAggregation(array $data, array $projection, array $option): array {
		$columnRefs = array_column(array_column($projection, 'column'), 'ref');
		$dimensionIndex = array_search($option['column']['ref'], $columnRefs, true);
		$measureIndexes = [];
		foreach ($projection as $index => $item) {
			if ($item['column']['role'] === 'measure') {
				$measureIndexes[] = $index;
			}
		}
		if ($dimensionIndex === false || $measureIndexes === []) {
			return $data;
		}

		$groups = [];
		foreach ($data as $row) {
			$row[$dimensionIndex] = $this->timeBucket((string)$row[$dimensionIndex], $option['column']['type'], $option['grouping']);
			$keyParts = $row;
			foreach ($measureIndexes as $index) {
				unset($keyParts[$index]);
			}
			$key = json_encode(array_values($keyParts), JSON_THROW_ON_ERROR);
			if (!isset($groups[$key])) {
				$groups[$key] = ['row' => $row, 'count' => 0];
				foreach ($measureIndexes as $index) {
					$groups[$key]['row'][$index] = '0';
				}
			}
			$groups[$key]['count']++;
			foreach ($measureIndexes as $index) {
				$groups[$key]['row'][$index] = $this->decimalAdd($groups[$key]['row'][$index], (string)($row[$index] ?? '0'));
			}
		}

		$result = [];
		foreach ($groups as $group) {
			$row = $group['row'];
			if ($option['mode'] === 'average') {
				foreach ($measureIndexes as $index) {
					$row[$index] = $this->decimalDivide($row[$index], $group['count']);
				}
			}
			$result[] = array_values($row);
		}
		return $result;
	}

	private function timeBucket(string $value, string $type, string $grouping): string {
		try {
			$date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
		} catch (\Throwable $e) {
			throw new FlexibleStorageException('invalid_time_value', 'A stored date value cannot be aggregated.', ['value' => $value], 500, $e);
		}
		$date = match ($grouping) {
			'day' => $date->setTime(0, 0),
			'week' => $date->modify('monday this week')->setTime(0, 0),
			'month' => $date->modify('first day of this month')->setTime(0, 0),
			'year' => $date->setDate((int)$date->format('Y'), 1, 1)->setTime(0, 0),
		};
		return $type === 'date' ? $date->format('Y-m-d') : $date->format('Y-m-d\TH:i:s\Z');
	}

	/**
	 * @param list<list<mixed>> $data
	 * @param list<array{column:array<string,mixed>,aggregation:string}> $projection
	 * @return list<list<mixed>>
	 */
	private function applyTopN(array $data, array $projection, array $option): array {
		$columnRefs = array_column(array_column($projection, 'column'), 'ref');
		$dimensionIndex = array_search($option['dimension']['ref'], $columnRefs, true);
		$measureIndex = array_search($option['measure']['ref'], $columnRefs, true);
		if ($dimensionIndex === false || $measureIndex === false) {
			return $data;
		}

		$totals = [];
		$order = [];
		foreach ($data as $row) {
			$key = json_encode($row[$dimensionIndex], JSON_THROW_ON_ERROR);
			if (!isset($totals[$key])) {
				$totals[$key] = '0';
				$order[$key] = count($order);
			}
			$totals[$key] = $this->decimalAdd($totals[$key], (string)($row[$measureIndex] ?? '0'));
		}
		uksort($totals, function (string $left, string $right) use ($totals, $order, $option): int {
			$comparison = $this->decimalCompare($totals[$left], $totals[$right]);
			if ($comparison === 0) {
				return $order[$left] <=> $order[$right];
			}
			return $option['type'] === 'top' ? -$comparison : $comparison;
		});
		$keep = array_fill_keys(array_slice(array_keys($totals), 0, $option['number']), true);
		$result = [];
		$others = [];
		$measureIndexes = [];
		foreach ($projection as $index => $item) {
			if ($item['column']['role'] === 'measure') {
				$measureIndexes[] = $index;
			}
		}
		foreach ($data as $row) {
			$key = json_encode($row[$dimensionIndex], JSON_THROW_ON_ERROR);
			if (isset($keep[$key])) {
				$result[] = $row;
				continue;
			}
			if (!$option['others']) {
				continue;
			}
			$row[$dimensionIndex] = 'others';
			$keyParts = $row;
			foreach ($measureIndexes as $index) {
				unset($keyParts[$index]);
			}
			$othersKey = json_encode(array_values($keyParts), JSON_THROW_ON_ERROR);
			if (!isset($others[$othersKey])) {
				$others[$othersKey] = $row;
				continue;
			}
			foreach ($measureIndexes as $index) {
				$others[$othersKey][$index] = $this->decimalAdd((string)$others[$othersKey][$index], (string)($row[$index] ?? '0'));
			}
		}
		return array_merge($result, array_values($others));
	}

	/**
	 * @param list<list<mixed>> $data
	 * @param list<array{column:array<string,mixed>,aggregation:string}> $projection
	 * @param list<array{column:array<string,mixed>,direction:string}> $sort
	 * @return list<list<mixed>>
	 */
	private function sortRows(array $data, array $projection, array $sort): array {
		if ($sort === []) {
			return $data;
		}
		$indexes = array_flip(array_column(array_column($projection, 'column'), 'ref'));
		$decorated = array_map(static fn (array $row, int $index): array => ['row' => $row, 'index' => $index], $data, array_keys($data));
		usort($decorated, function (array $left, array $right) use ($sort, $indexes): int {
			foreach ($sort as $item) {
				$index = $indexes[$item['column']['ref']];
				$a = $left['row'][$index] ?? null;
				$b = $right['row'][$index] ?? null;
				if ($a === $b) {
					continue;
				}
				if ($a === null || $b === null) {
					$comparison = $a === null ? -1 : 1;
				} elseif ($item['column']['role'] === 'measure' || in_array($item['column']['type'], ['decimal', 'boolean'], true)) {
					$comparison = $this->decimalCompare((string)$a, (string)$b);
				} else {
					$comparison = strcmp((string)$a, (string)$b);
				}
				if ($comparison !== 0) {
					return $item['direction'] === 'DESC' ? -$comparison : $comparison;
				}
			}
			return $left['index'] <=> $right['index'];
		});
		return array_column($decorated, 'row');
	}

	private function decimalAdd(string $left, string $right): string {
		[$leftSign, $leftDigits, $leftScale] = $this->decimalParts($left);
		[$rightSign, $rightDigits, $rightScale] = $this->decimalParts($right);
		$scale = max($leftScale, $rightScale);
		$leftDigits .= str_repeat('0', $scale - $leftScale);
		$rightDigits .= str_repeat('0', $scale - $rightScale);
		if ($leftSign === $rightSign) {
			$digits = $this->unsignedAdd($leftDigits, $rightDigits);
			$sign = $leftSign;
		} else {
			$comparison = $this->unsignedCompare($leftDigits, $rightDigits);
			if ($comparison === 0) {
				return '0';
			}
			[$larger, $smaller, $sign] = $comparison > 0
				? [$leftDigits, $rightDigits, $leftSign]
				: [$rightDigits, $leftDigits, $rightSign];
			$digits = $this->unsignedSubtract($larger, $smaller);
		}
		return $this->formatDecimal($sign, $digits, $scale);
	}

	private function decimalCompare(string $left, string $right): int {
		[$leftSign, $leftDigits, $leftScale] = $this->decimalParts($left);
		[$rightSign, $rightDigits, $rightScale] = $this->decimalParts($right);
		if ($leftSign !== $rightSign) {
			return $leftSign <=> $rightSign;
		}
		$scale = max($leftScale, $rightScale);
		$comparison = $this->unsignedCompare($leftDigits . str_repeat('0', $scale - $leftScale), $rightDigits . str_repeat('0', $scale - $rightScale));
		return $leftSign < 0 ? -$comparison : $comparison;
	}

	private function decimalDivide(string $value, int $divisor): string {
		[$sign, $digits, $scale] = $this->decimalParts($value);
		$targetScale = 10;
		if ($scale < $targetScale) {
			$digits .= str_repeat('0', $targetScale - $scale);
			$scale = $targetScale;
		}
		$quotient = '';
		$remainder = 0;
		foreach (str_split($digits) as $digit) {
			$current = ($remainder * 10) + (int)$digit;
			$quotient .= (string)intdiv($current, $divisor);
			$remainder = $current % $divisor;
		}
		$quotient = ltrim($quotient, '0') ?: '0';
		if ($remainder * 2 >= $divisor) {
			$quotient = $this->unsignedAdd($quotient, '1');
		}
		return $this->formatDecimal($sign, $quotient, $scale);
	}

	/** @return array{int,string,int} */
	private function decimalParts(string $value): array {
		$value = trim($value);
		if (preg_match('/^([+-]?)([0-9]+)(?:\.([0-9]+))?$/D', $value, $match) !== 1) {
			throw new FlexibleStorageException('invalid_numeric_result', 'A database aggregate returned a non-decimal value.', ['value' => $value], 500);
		}
		$digits = ltrim($match[2] . ($match[3] ?? ''), '0') ?: '0';
		$sign = ($match[1] ?? '') === '-' && $digits !== '0' ? -1 : 1;
		return [$sign, $digits, strlen($match[3] ?? '')];
	}

	private function unsignedCompare(string $left, string $right): int {
		$left = ltrim($left, '0') ?: '0';
		$right = ltrim($right, '0') ?: '0';
		return strlen($left) === strlen($right) ? strcmp($left, $right) <=> 0 : strlen($left) <=> strlen($right);
	}

	private function unsignedAdd(string $left, string $right): string {
		$length = max(strlen($left), strlen($right));
		$left = str_pad($left, $length, '0', STR_PAD_LEFT);
		$right = str_pad($right, $length, '0', STR_PAD_LEFT);
		$result = '';
		$carry = 0;
		for ($index = $length - 1; $index >= 0; $index--) {
			$sum = (int)$left[$index] + (int)$right[$index] + $carry;
			$result = (string)($sum % 10) . $result;
			$carry = intdiv($sum, 10);
		}
		return ($carry > 0 ? (string)$carry : '') . $result;
	}

	private function unsignedSubtract(string $left, string $right): string {
		$right = str_pad($right, strlen($left), '0', STR_PAD_LEFT);
		$result = '';
		$borrow = 0;
		for ($index = strlen($left) - 1; $index >= 0; $index--) {
			$digit = (int)$left[$index] - (int)$right[$index] - $borrow;
			if ($digit < 0) {
				$digit += 10;
				$borrow = 1;
			} else {
				$borrow = 0;
			}
			$result = (string)$digit . $result;
		}
		return ltrim($result, '0') ?: '0';
	}

	private function formatDecimal(int $sign, string $digits, int $scale): string {
		$digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
		if ($scale > 0) {
			$value = substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);
			$value = rtrim(rtrim($value, '0'), '.');
		} else {
			$value = $digits;
		}
		$value = ltrim($value, '0');
		if ($value === '' || str_starts_with($value, '.')) {
			$value = '0' . $value;
		}
		return $sign < 0 && $value !== '0' ? '-' . $value : $value;
	}

	private function apiValue(array $column, mixed $value, bool $aggregate, string $aggregation): mixed {
		if ($value === null) {
			return null;
		}
		if ($aggregate && $column['role'] === 'measure') {
			return (string)$value;
		}
		return match ($column['type']) {
			'decimal' => (string)$value,
			'boolean' => (string)$value === '1',
			'date' => substr((string)$value, 0, 10),
			'datetime' => str_replace(' ', 'T', substr((string)$value, 0, 19)) . 'Z',
			default => (string)$value,
		};
	}

	/** @return list<array<string,mixed>> */
	private function normalizeColumns(array $columns): array {
		if ($columns === []) {
			throw new FlexibleStorageException('missing_columns', 'At least one column definition is required.', ['field' => 'columns']);
		}
		$normalized = [];
		$names = [];
		$dimensionCount = 0;
		foreach (array_values($columns) as $position => $column) {
			if (!is_array($column)) {
				throw new FlexibleStorageException('invalid_column', 'Each column definition must be an object.', ['position' => $position]);
			}
			$item = $this->normalizer->normalizeColumn($column, $position);
			$key = mb_strtolower($item['name']);
			if (isset($names[$key])) {
				throw new FlexibleStorageException('duplicate_column_name', 'Column names must be unique within a dataset.', ['position' => $position, 'name' => $item['name']]);
			}
			$names[$key] = true;
			$dimensionCount += $item['role'] === 'dimension' ? 1 : 0;
			$normalized[] = $item;
		}
		if ($dimensionCount === 0) {
			throw new FlexibleStorageException('missing_dimension', 'Flexible datasets require at least one dimension column.');
		}
		return $normalized;
	}

	/** @return array<string,mixed> */
	private function requireFlexible(int $datasetId, bool $ownerOnly): array {
		$resolved = $this->storageResolver->resolve($datasetId, $ownerOnly);
		if ($resolved['mode'] !== DatasetStorageResolver::FLEXIBLE_SHARED) {
			throw new FlexibleStorageException('wrong_storage_mode', 'This operation is available only for flexible datasets.', ['datasetId' => $datasetId, 'storageMode' => $resolved['mode']], 409);
		}
		return $resolved;
	}

	/** @return array<string,mixed> */
	private function descriptor(array $dataset, array $columns): array {
		return [
			'id' => (int)$dataset['id'],
			'name' => (string)$dataset['name'],
			'subheader' => $dataset['subheader'] ?? null,
			'type' => isset($dataset['type']) ? (int)$dataset['type'] : 2,
			'parent' => isset($dataset['parent']) ? (int)$dataset['parent'] : 0,
			'aiIndex' => isset($dataset['ai_index']) ? (int)$dataset['ai_index'] : 0,
			'storageMode' => DatasetStorageResolver::FLEXIBLE_SHARED,
			'schemaVersion' => (int)($dataset['schema_version'] ?? 1),
			'columns' => array_values($columns),
		];
	}

	/** @return array<string,mixed> */
	private function decodeMapping(mixed $mapping): array {
		if (is_string($mapping)) {
			$mapping = json_decode($mapping, true);
		}
		if (!is_array($mapping) || (int)($mapping['schemaVersion'] ?? 0) !== 1 || !is_array($mapping['sourceHeader'] ?? null) || !is_array($mapping['columns'] ?? null)) {
			throw new FlexibleStorageException('invalid_storage_mapping', 'A version 1 source-to-dataset mapping is required.');
		}
		return $mapping;
	}

	/** @return list<array{values:array<string,mixed>}> */
	private function mapSourceRows(int $datasetId, array $mapping, array $header, array $rows): array {
		$header = array_map('strval', $header);
		if (array_values($mapping['sourceHeader']) !== array_values($header)) {
			throw new FlexibleStorageException('source_header_changed', 'The effective source header changed and the load must be remapped.', ['expected' => $mapping['sourceHeader'], 'actual' => $header], 409);
		}
		$columns = $this->storageMapper->getColumns($datasetId);
		$resolver = new FlexibleColumnResolver($columns);
		$targets = [];
		foreach ($mapping['columns'] as $index => $entry) {
			if (!is_array($entry) || !isset($entry['column'], $entry['sourceIndex'])) {
				throw new FlexibleStorageException('invalid_storage_mapping', 'Each mapping entry requires a column and sourceIndex.', ['mapping' => $index]);
			}
			$column = $resolver->resolve((string)$entry['column']);
			$sourceIndex = (int)$entry['sourceIndex'];
			if ($sourceIndex < 0 || $sourceIndex >= count($header)) {
				throw new FlexibleStorageException('invalid_storage_mapping', 'A mapped source index is outside the effective source header.', ['mapping' => $index, 'sourceIndex' => $sourceIndex]);
			}
			if (isset($targets[$column['ref']])) {
				throw new FlexibleStorageException('invalid_storage_mapping', 'A dataset column can be mapped only once.', ['column' => $column['ref']]);
			}
			$targets[$column['ref']] = $sourceIndex;
		}
		foreach ($columns as $column) {
			if (!$column['nullable'] && !isset($targets[$column['ref']])) {
				throw new FlexibleStorageException('invalid_storage_mapping', 'Every required dataset column must be mapped.', ['column' => $column['ref']]);
			}
		}

		$records = [];
		foreach ($rows as $rowIndex => $row) {
			if (!is_array($row)) {
				throw new FlexibleStorageException('invalid_source_row', 'Source rows must be arrays.', ['record' => $rowIndex]);
			}
			$values = [];
			foreach ($targets as $reference => $sourceIndex) {
				$values[$reference] = array_key_exists($sourceIndex, $row) ? $row[$sourceIndex] : null;
			}
			$records[] = ['values' => $values];
		}
		return $records;
	}
}
