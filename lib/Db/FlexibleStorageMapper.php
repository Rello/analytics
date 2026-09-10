<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Db;

use OCA\Analytics\Storage\FlexibleColumnResolver;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FlexibleStorageMapper {
	public const COLUMN_TABLE = 'analytics_flex_columns';
	public const RECORD_TABLE = 'analytics_flex_records';
	public const VALUE_TABLE = 'analytics_flex_values';

	private int $parameterCounter = 0;

	public function __construct(private IDBConnection $db) {
	}

	public function beginTransaction(): void {
		$this->db->beginTransaction();
	}

	public function commit(): void {
		$this->db->commit();
	}

	public function rollBack(): void {
		$this->db->rollBack();
	}

	/** @return list<array<string,mixed>> */
	public function getColumns(int $datasetId): array {
		$sql = $this->db->getQueryBuilder();
		$sql->select('*')
			->from(self::COLUMN_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->orderBy('display_position', 'ASC')
			->addOrderBy('id', 'ASC');
		$result = $sql->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map([$this, 'columnDescriptor'], is_array($rows) ? $rows : []);
	}

	/** @param array<string,mixed> $column */
	public function createColumn(int $datasetId, array $column): array {
		$sql = $this->db->getQueryBuilder();
		$sql->insert(self::COLUMN_TABLE)->values([
			'dataset_id' => $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT),
			'name' => $sql->createNamedParameter($column['name']),
			'logical_type' => $sql->createNamedParameter($column['type']),
			'column_role' => $sql->createNamedParameter($column['role']),
			'display_position' => $sql->createNamedParameter($column['position'], IQueryBuilder::PARAM_INT),
			'nullable_flag' => $sql->createNamedParameter($column['nullable'] ? 1 : 0, IQueryBuilder::PARAM_INT),
			'default_aggregation' => $sql->createNamedParameter($column['defaultAggregation']),
		]);
		$sql->executeStatement();
		$column['id'] = (int)$sql->getLastInsertId();
		$column['ref'] = 'c_' . $column['id'];
		return $column;
	}

	/** @param array<string,mixed> $column */
	public function updateColumn(int $datasetId, int $columnId, array $column): void {
		$sql = $this->db->getQueryBuilder();
		$sql->update(self::COLUMN_TABLE)
			->set('name', $sql->createNamedParameter($column['name']))
			->set('logical_type', $sql->createNamedParameter($column['type']))
			->set('column_role', $sql->createNamedParameter($column['role']))
			->set('display_position', $sql->createNamedParameter($column['position'], IQueryBuilder::PARAM_INT))
			->set('nullable_flag', $sql->createNamedParameter($column['nullable'] ? 1 : 0, IQueryBuilder::PARAM_INT))
			->set('default_aggregation', $sql->createNamedParameter($column['defaultAggregation']))
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)));
		$sql->executeStatement();
	}

	public function deleteColumn(int $datasetId, int $columnId): void {
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::VALUE_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('column_id', $sql->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)))
			->executeStatement();

		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::COLUMN_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function findRecordId(int $datasetId, string $dimensionKey): ?int {
		$sql = $this->db->getQueryBuilder();
		$sql->select('id')
			->from(self::RECORD_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('dimension_key', $sql->createNamedParameter($dimensionKey)));
		$id = $sql->executeQuery()->fetchOne();
		return $id === false ? null : (int)$id;
	}

	public function recordBelongsToDataset(int $datasetId, int $recordId): bool {
		$sql = $this->db->getQueryBuilder();
		$sql->select('id')
			->from(self::RECORD_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($recordId, IQueryBuilder::PARAM_INT)));
		return $sql->executeQuery()->fetchOne() !== false;
	}

	public function createRecord(int $datasetId, string $dimensionKey): int {
		$sql = $this->db->getQueryBuilder();
		$sql->insert(self::RECORD_TABLE)->values([
			'dataset_id' => $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT),
			'dimension_key' => $sql->createNamedParameter($dimensionKey),
			'updated_at' => $sql->createNamedParameter(gmdate('Y-m-d H:i:s')),
		]);
		$sql->executeStatement();
		return (int)$sql->getLastInsertId();
	}

	public function updateRecord(int $datasetId, int $recordId, string $dimensionKey): void {
		$sql = $this->db->getQueryBuilder();
		$sql->update(self::RECORD_TABLE)
			->set('dimension_key', $sql->createNamedParameter($dimensionKey))
			->set('updated_at', $sql->createNamedParameter(gmdate('Y-m-d H:i:s')))
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($recordId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @param array{canonical:mixed,text_value:?string,decimal_value:?string,datetime_value:?string}|null $value */
	public function writeValue(int $datasetId, int $recordId, int $columnId, ?array $value): void {
		$sql = $this->db->getQueryBuilder();
		$sql->select('id')
			->from(self::VALUE_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('record_id', $sql->createNamedParameter($recordId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('column_id', $sql->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)));
		$valueId = $sql->executeQuery()->fetchOne();

		if ($value === null) {
			if ($valueId !== false) {
				$this->deleteValue((int)$valueId);
			}
			return;
		}

		if ($valueId === false) {
			$sql = $this->db->getQueryBuilder();
			$sql->insert(self::VALUE_TABLE)->values([
				'dataset_id' => $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT),
				'record_id' => $sql->createNamedParameter($recordId, IQueryBuilder::PARAM_INT),
				'column_id' => $sql->createNamedParameter($columnId, IQueryBuilder::PARAM_INT),
				'text_value' => $sql->createNamedParameter($value['text_value']),
				'decimal_value' => $sql->createNamedParameter($value['decimal_value']),
				'datetime_value' => $sql->createNamedParameter($value['datetime_value']),
			]);
			$sql->executeStatement();
			return;
		}

		$sql = $this->db->getQueryBuilder();
		$sql->update(self::VALUE_TABLE)
			->set('text_value', $sql->createNamedParameter($value['text_value']))
			->set('decimal_value', $sql->createNamedParameter($value['decimal_value']))
			->set('datetime_value', $sql->createNamedParameter($value['datetime_value']))
			->where($sql->expr()->eq('id', $sql->createNamedParameter((int)$valueId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function deleteRecord(int $datasetId, int $recordId): void {
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::VALUE_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('record_id', $sql->createNamedParameter($recordId, IQueryBuilder::PARAM_INT)))
			->executeStatement();

		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::RECORD_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($recordId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function deleteRecords(int $datasetId): int {
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::VALUE_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->executeStatement();

		$sql = $this->db->getQueryBuilder();
		return $sql->delete(self::RECORD_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function deleteDataset(int $datasetId): void {
		$this->deleteRecords($datasetId);
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::COLUMN_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function getRecordCount(int $datasetId): array {
		$sql = $this->db->getQueryBuilder();
		$sql->selectAlias($sql->func()->count('*'), 'count')
			->from(self::RECORD_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)));
		return ['count' => (int)$sql->executeQuery()->fetchOne()];
	}

	public function getLastUpdate(int $datasetId): int {
		$sql = $this->db->getQueryBuilder();
		$sql->selectAlias($sql->func()->max('updated_at'), 'updated_at')
			->from(self::RECORD_TABLE)
			->where($sql->expr()->eq('dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)));
		$value = $sql->executeQuery()->fetchOne();
		return $value === false || $value === null ? 0 : (int)strtotime((string)$value . ' UTC');
	}

	/**
	 * @param list<array<string,mixed>> $projection
	 * @param list<array{column:array<string,mixed>,operator:string,value:mixed}> $filters
	 * @param list<array{column:array<string,mixed>,direction:string}> $sort
	 * @return list<array<string,mixed>>
	 */
	public function query(int $datasetId, array $projection, array $filters, array $sort, bool $aggregate, int $limit, int $offset): array {
		$this->parameterCounter = 0;
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::RECORD_TABLE, 'r')
			->where($sql->expr()->eq('r.dataset_id', $sql->createNamedParameter($datasetId, IQueryBuilder::PARAM_INT)));

		$required = [];
		foreach ($projection as $item) {
			$required[(int)$item['column']['id']] = $item['column'];
		}
		foreach ($filters as $filter) {
			$required[(int)$filter['column']['id']] = $filter['column'];
		}
		foreach ($sort as $item) {
			$required[(int)$item['column']['id']] = $item['column'];
		}
		foreach ($required as $column) {
			$alias = $this->valueAlias($column);
			$sql->leftJoin('r', self::VALUE_TABLE, $alias, $sql->expr()->andX(
				$sql->expr()->eq($alias . '.dataset_id', 'r.dataset_id'),
				$sql->expr()->eq($alias . '.record_id', 'r.id'),
				$sql->expr()->eq($alias . '.column_id', $sql->createNamedParameter((int)$column['id'], IQueryBuilder::PARAM_INT))
			));
		}

		if (!$aggregate) {
			$sql->selectAlias('r.id', 'record_id');
		}
		$dimensionSort = [];
		foreach ($projection as $item) {
			$column = $item['column'];
			$field = $this->valueAlias($column) . '.' . FlexibleColumnResolver::valueField($column);
			$outputAlias = 'c_' . (int)$column['id'];
			if ($aggregate && $column['role'] === 'measure') {
				$aggregation = $item['aggregation'];
				$expression = match ($aggregation) {
					'sum' => $sql->func()->sum($field),
					'min' => $sql->func()->min($field),
					'max' => $sql->func()->max($field),
					'count' => $sql->func()->count($field),
					'avg' => $sql->createFunction('AVG(' . $field . ')'),
					'count_distinct' => $sql->createFunction('COUNT(DISTINCT ' . $field . ')'),
					default => throw new \InvalidArgumentException('Unsupported aggregation'),
				};
				$sql->selectAlias($expression, $outputAlias);
			} else {
				$sql->selectAlias($field, $outputAlias);
				if ($aggregate) {
					$sql->addGroupBy($field);
					$dimensionSort[] = $outputAlias;
				}
			}
		}

		$this->applyFilters($sql, $filters);
		foreach ($sort as $item) {
			$column = $item['column'];
			$outputAlias = 'c_' . (int)$column['id'];
			$sql->addOrderBy($outputAlias, $item['direction']);
		}
		if ($sort === []) {
			if ($aggregate) {
				foreach ($dimensionSort as $alias) {
					$sql->addOrderBy($alias, 'ASC');
				}
			} else {
				$sql->addOrderBy('r.id', 'ASC');
			}
		}
		if ($limit > 0) {
			$sql->setMaxResults($limit);
		}
		$sql->setFirstResult($offset);

		$result = $sql->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return is_array($rows) ? $rows : [];
	}

	/** @param list<array{column:array<string,mixed>,operator:string,value:mixed}> $filters */
	private function applyFilters(IQueryBuilder $sql, array $filters): void {
		$groups = [];
		foreach ($filters as $filter) {
			$groups[(int)$filter['column']['id']][] = $filter;
		}
		foreach ($groups as $columnFilters) {
			$positive = [];
			$negative = [];
			foreach ($columnFilters as $filter) {
				$column = $filter['column'];
				$field = $this->valueAlias($column) . '.' . FlexibleColumnResolver::valueField($column);
				$expression = $this->filterExpression($sql, $field, $filter['operator'], $filter['value']);
				if ($filter['operator'] === 'NOTLIKE') {
					$negative[] = $expression;
				} else {
					$positive[] = $expression;
				}
			}
			if ($positive !== []) {
				$sql->andWhere(count($positive) === 1 ? $positive[0] : $sql->expr()->orX(...$positive));
			}
			foreach ($negative as $expression) {
				$sql->andWhere($expression);
			}
		}
	}

	private function filterExpression(IQueryBuilder $sql, string $field, string $operator, mixed $value): mixed {
		if ($operator === 'EQ' && $value === null) {
			return $sql->expr()->isNull($field);
		}
		if ($operator === 'IN') {
			$name = 'flexIn' . (++$this->parameterCounter);
			$sql->setParameter($name, $value, IQueryBuilder::PARAM_STR_ARRAY);
			return $sql->expr()->in($field, $sql->createParameter($name));
		}
		if ($operator === 'BETWEEN') {
			return $sql->expr()->andX(
				$sql->expr()->gte($field, $sql->createNamedParameter($value[0])),
				$sql->expr()->lte($field, $sql->createNamedParameter($value[1]))
			);
		}
		if ($operator === 'LIKE' || $operator === 'NOTLIKE') {
			$pattern = $this->db->escapeLikeParameter((string)$value);
			$pattern = strpbrk((string)$value, '*?') === false
				? '%' . $pattern . '%'
				: str_replace(['*', '?'], ['%', '_'], $pattern);
			$expression = $sql->expr()->like($field, $sql->createNamedParameter($pattern));
			return $operator === 'NOTLIKE' ? 'NOT (' . $expression . ')' : $expression;
		}
		return match ($operator) {
			'EQ' => $sql->expr()->eq($field, $sql->createNamedParameter($value)),
			'GT' => $sql->expr()->gt($field, $sql->createNamedParameter($value)),
			'LT' => $sql->expr()->lt($field, $sql->createNamedParameter($value)),
			default => throw new \InvalidArgumentException('Unsupported filter operator'),
		};
	}

	private function valueAlias(array $column): string {
		return 'v' . (int)$column['id'];
	}

	private function deleteValue(int $valueId): void {
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::VALUE_TABLE)
			->where($sql->expr()->eq('id', $sql->createNamedParameter($valueId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @param array<string,mixed> $row */
	private function columnDescriptor(array $row): array {
		return [
			'id' => (int)$row['id'],
			'ref' => 'c_' . (int)$row['id'],
			'name' => (string)$row['name'],
			'type' => (string)$row['logical_type'],
			'role' => (string)$row['column_role'],
			'position' => (int)$row['display_position'],
			'nullable' => (bool)$row['nullable_flag'],
			'defaultAggregation' => $row['default_aggregation'],
		];
	}
}
