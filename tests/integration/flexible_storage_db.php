<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use OCA\Analytics\Db\FlexibleStorageMapper;
use OCA\Analytics\Storage\FlexibleValueNormalizer;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

$nextcloudRoot = getenv('NEXTCLOUD_ROOT');
if (!is_string($nextcloudRoot) || $nextcloudRoot === '') {
	$nextcloudRoot = dirname(__DIR__, 4);
}
require_once $nextcloudRoot . '/lib/base.php';

$db = \OC::$server->get(IDBConnection::class);
$mapper = new FlexibleStorageMapper($db);
$normalizer = new FlexibleValueNormalizer();
$datasetId = 0;

$countRows = static function (IDBConnection $connection, string $table, string $column = '', ?int $value = null): int {
	$query = $connection->getQueryBuilder();
	$query->selectAlias($query->func()->count('*'), 'count')->from($table);
	if ($column !== '' && $value !== null) {
		$query->where($query->expr()->eq($column, $query->createNamedParameter($value, IQueryBuilder::PARAM_INT)));
	}
	return (int)$query->executeQuery()->fetchOne();
};

$factsBefore = $countRows($db, 'analytics_facts');

try {
	$query = $db->getQueryBuilder();
	$query->insert('analytics_dataset')->values([
		'user_id' => $query->createNamedParameter('admin'),
		'name' => $query->createNamedParameter('Flexible DB integration test'),
		'dimension1' => $query->createNamedParameter(''),
		'dimension2' => $query->createNamedParameter(''),
		'value' => $query->createNamedParameter(''),
		'type' => $query->createNamedParameter(2, IQueryBuilder::PARAM_INT),
		'ai_index' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
		'storage_mode' => $query->createNamedParameter('flexible_shared'),
		'schema_version' => $query->createNamedParameter(1, IQueryBuilder::PARAM_INT),
	]);
	$query->executeStatement();
	$datasetId = (int)$query->getLastInsertId();

	$columns = [];
	foreach ([
		['name' => 'Date', 'type' => 'date', 'role' => 'dimension', 'position' => 0, 'nullable' => false, 'defaultAggregation' => null],
		['name' => 'Region', 'type' => 'text', 'role' => 'dimension', 'position' => 1, 'nullable' => false, 'defaultAggregation' => null],
		['name' => 'Revenue', 'type' => 'decimal', 'role' => 'measure', 'position' => 2, 'nullable' => false, 'defaultAggregation' => 'sum'],
		['name' => 'Cost', 'type' => 'decimal', 'role' => 'measure', 'position' => 3, 'nullable' => false, 'defaultAggregation' => 'sum'],
	] as $definition) {
		$columns[] = $mapper->createColumn($datasetId, $definition);
	}

	foreach ([
		['2026-09-01', 'Germany', '10', '3'],
		['2026-09-02', 'Germany', '2', '2'],
	] as $rowIndex => $row) {
		$values = [];
		foreach ($columns as $columnIndex => $column) {
			$values[(int)$column['id']] = $normalizer->normalizeValue($column, $row[$columnIndex], $rowIndex);
		}
		$recordId = $mapper->createRecord($datasetId, $normalizer->dimensionKey(array_slice($columns, 0, 2), $values));
		foreach ($values as $columnId => $value) {
			$mapper->writeValue($datasetId, $recordId, $columnId, $value);
		}
	}

	$projection = [
		['column' => $columns[1], 'aggregation' => ''],
		['column' => $columns[2], 'aggregation' => 'sum'],
		['column' => $columns[3], 'aggregation' => 'sum'],
	];
	$rows = $mapper->query($datasetId, $projection, [], [], true, 100, 0);
	if (count($rows) !== 1 || (string)$rows[0][$columns[1]['ref']] !== 'Germany') {
		throw new RuntimeException('Flexible grouped query returned unexpected dimensions');
	}
	$canonical = static function (mixed $value): string {
		$value = (string)$value;
		return str_contains($value, '.') ? (rtrim(rtrim($value, '0'), '.') ?: '0') : $value;
	};
	if ($canonical($rows[0][$columns[2]['ref']]) !== '12' || $canonical($rows[0][$columns[3]['ref']]) !== '5') {
		throw new RuntimeException('Flexible multi-measure aggregates were multiplied or changed precision');
	}
	foreach ([
		'avg' => '6',
		'min' => '2',
		'max' => '10',
		'count' => '2',
		'count_distinct' => '2',
	] as $aggregation => $expected) {
		$aggregateRows = $mapper->query($datasetId, [
			['column' => $columns[2], 'aggregation' => $aggregation],
		], [], [], true, 100, 0);
		if (count($aggregateRows) !== 1 || $canonical($aggregateRows[0][$columns[2]['ref']]) !== $expected) {
			throw new RuntimeException('Flexible ' . $aggregation . ' query returned an unexpected result');
		}
	}
	if ($countRows($db, 'analytics_flex_records', 'dataset_id', $datasetId) !== 2 || $countRows($db, 'analytics_flex_values', 'dataset_id', $datasetId) !== 8) {
		throw new RuntimeException('Flexible records were not stored exclusively in the shared tables');
	}
	if ($countRows($db, 'analytics_facts') !== $factsBefore) {
		throw new RuntimeException('Flexible writes changed legacy analytics_facts rows');
	}

	echo "Flexible storage database integration check passed\n";
} finally {
	if ($datasetId > 0) {
		$mapper->deleteDataset($datasetId);
		$query = $db->getQueryBuilder();
		$query->delete('analytics_dataset')
			->where($query->expr()->eq('id', $query->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
