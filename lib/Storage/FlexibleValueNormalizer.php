<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Storage;

use OCA\Analytics\Exception\FlexibleStorageException;

class FlexibleValueNormalizer {
	public const TYPES = ['text', 'decimal', 'date', 'datetime', 'boolean'];
	public const ROLES = ['dimension', 'measure'];
	public const AGGREGATIONS = ['sum', 'avg', 'min', 'max', 'count', 'count_distinct'];

	/**
	 * @param array<string,mixed> $column
	 * @return array<string,mixed>
	 */
	public function normalizeColumn(array $column, int $position): array {
		$name = trim((string)($column['name'] ?? ''));
		$type = strtolower((string)($column['type'] ?? ''));
		$role = strtolower((string)($column['role'] ?? ''));
		$nullable = $column['nullable'] ?? true;
		$aggregation = $column['defaultAggregation'] ?? null;
		$aggregation = $aggregation === null || $aggregation === '' ? null : strtolower((string)$aggregation);

		if ($name === '' || mb_strlen($name) > 255) {
			throw new FlexibleStorageException('invalid_column_name', 'Column names must contain between 1 and 255 characters.', ['position' => $position, 'field' => 'name']);
		}
		if (!in_array($type, self::TYPES, true)) {
			throw new FlexibleStorageException('invalid_column_type', 'Unsupported logical column type.', ['position' => $position, 'field' => 'type', 'value' => $type]);
		}
		if (!in_array($role, self::ROLES, true)) {
			throw new FlexibleStorageException('invalid_column_role', 'A column must be a dimension or measure.', ['position' => $position, 'field' => 'role', 'value' => $role]);
		}
		if (!is_bool($nullable)) {
			throw new FlexibleStorageException('invalid_nullable_flag', 'The nullable property must be boolean.', ['position' => $position, 'field' => 'nullable']);
		}
		if ($aggregation !== null && !in_array($aggregation, self::AGGREGATIONS, true)) {
			throw new FlexibleStorageException('invalid_aggregation', 'Unsupported default aggregation.', ['position' => $position, 'field' => 'defaultAggregation', 'value' => $aggregation]);
		}
		if ($role === 'dimension' && $aggregation !== null) {
			throw new FlexibleStorageException('invalid_aggregation', 'Dimension columns cannot define a default aggregation.', ['position' => $position, 'field' => 'defaultAggregation']);
		}
		if ($role === 'measure' && $aggregation === null) {
			$aggregation = in_array($type, ['decimal', 'boolean'], true) ? 'sum' : 'count';
		}

		return [
			'name' => $name,
			'type' => $type,
			'role' => $role,
			'position' => $position,
			'nullable' => $nullable,
			'defaultAggregation' => $aggregation,
		];
	}

	/**
	 * @param array<string,mixed> $column
	 * @return array{canonical:mixed,text_value:?string,decimal_value:?string,datetime_value:?string}|null
	 */
	public function normalizeValue(array $column, mixed $value, int $recordIndex): ?array {
		if ($value === null) {
			if (!(bool)$column['nullable']) {
				throw new FlexibleStorageException('missing_required_value', 'A required value is missing.', [
					'record' => $recordIndex,
					'column' => $column['ref'],
				]);
			}
			return null;
		}

		return match ($column['type']) {
			'text' => $this->normalizeText($column, $value, $recordIndex),
			'decimal' => $this->normalizeDecimal($column, $value, $recordIndex),
			'boolean' => $this->normalizeBoolean($column, $value, $recordIndex),
			'date' => $this->normalizeDate($column, $value, $recordIndex),
			'datetime' => $this->normalizeDateTime($column, $value, $recordIndex),
			default => throw new FlexibleStorageException('invalid_column_type', 'Unsupported logical column type.', ['column' => $column['ref']]),
		};
	}

	/**
	 * @param list<array<string,mixed>> $dimensionColumns
	 * @param array<int,array{canonical:mixed,text_value:?string,decimal_value:?string,datetime_value:?string}|null> $values
	 */
	public function dimensionKey(array $dimensionColumns, array $values): string {
		usort($dimensionColumns, static fn (array $left, array $right): int => (int)$left['id'] <=> (int)$right['id']);
		$identity = [];
		foreach ($dimensionColumns as $column) {
			$normalized = $values[(int)$column['id']] ?? null;
			$identity[] = [(int)$column['id'], $column['type'], $normalized['canonical'] ?? null];
		}
		return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
	}

	/** @return array{canonical:string,text_value:string,decimal_value:null,datetime_value:null} */
	private function normalizeText(array $column, mixed $value, int $recordIndex): array {
		if (!is_scalar($value)) {
			$this->invalidValue($column, $recordIndex, 'Text values must be scalar.');
		}
		$text = (string)$value;
		return ['canonical' => $text, 'text_value' => $text, 'decimal_value' => null, 'datetime_value' => null];
	}

	/** @return array{canonical:string,text_value:null,decimal_value:string,datetime_value:null} */
	private function normalizeDecimal(array $column, mixed $value, int $recordIndex): array {
		if (is_float($value) || (!is_string($value) && !is_int($value))) {
			$this->invalidValue($column, $recordIndex, 'Decimal values must be strings or integers.');
		}
		$decimal = trim((string)$value);
		if (!preg_match('/^[+-]?(?:\d+)(?:\.\d+)?$/D', $decimal)) {
			$this->invalidValue($column, $recordIndex, 'Invalid decimal value.');
		}

		$negative = str_starts_with($decimal, '-');
		$unsigned = ltrim($decimal, '+-');
		[$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
		$integer = ltrim($integer, '0');
		$integer = $integer === '' ? '0' : $integer;
		if (strlen($integer) > 20) {
			$this->invalidValue($column, $recordIndex, 'Decimal value exceeds DECIMAL(30,10).');
		}
		if (strlen($fraction) > 10 && trim(substr($fraction, 10), '0') !== '') {
			$this->invalidValue($column, $recordIndex, 'Decimal value has more than 10 non-zero fractional digits.');
		}
		$fraction = substr($fraction, 0, 10);
		$fraction = rtrim($fraction, '0');
		$canonical = ($negative && ($integer !== '0' || $fraction !== '') ? '-' : '') . $integer;
		if ($fraction !== '') {
			$canonical .= '.' . $fraction;
		}
		return ['canonical' => $canonical, 'text_value' => null, 'decimal_value' => $canonical, 'datetime_value' => null];
	}

	/** @return array{canonical:string,text_value:null,decimal_value:string,datetime_value:null} */
	private function normalizeBoolean(array $column, mixed $value, int $recordIndex): array {
		$normalized = match (true) {
			$value === true, $value === 1, $value === '1', $value === 'true' => '1',
			$value === false, $value === 0, $value === '0', $value === 'false' => '0',
			default => null,
		};
		if ($normalized === null) {
			$this->invalidValue($column, $recordIndex, 'Boolean values must be true, false, 1, or 0.');
		}
		return ['canonical' => $normalized, 'text_value' => null, 'decimal_value' => $normalized, 'datetime_value' => null];
	}

	/** @return array{canonical:string,text_value:null,decimal_value:null,datetime_value:string} */
	private function normalizeDate(array $column, mixed $value, int $recordIndex): array {
		if (!is_string($value)) {
			$this->invalidValue($column, $recordIndex, 'Dates must use YYYY-MM-DD.');
		}
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
		$errors = \DateTimeImmutable::getLastErrors();
		if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
			$this->invalidValue($column, $recordIndex, 'Dates must use a valid YYYY-MM-DD value.');
		}
		return ['canonical' => $value, 'text_value' => null, 'decimal_value' => null, 'datetime_value' => $value . ' 00:00:00'];
	}

	/** @return array{canonical:string,text_value:null,decimal_value:null,datetime_value:string} */
	private function normalizeDateTime(array $column, mixed $value, int $recordIndex): array {
		if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
			$this->invalidValue($column, $recordIndex, 'Datetimes must use ISO-8601 with seconds and an explicit timezone.');
		}
		try {
			$date = new \DateTimeImmutable($value);
		} catch (\Throwable) {
			$this->invalidValue($column, $recordIndex, 'Invalid datetime value.');
		}
		$errors = \DateTimeImmutable::getLastErrors();
		if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
			$this->invalidValue($column, $recordIndex, 'Invalid datetime value.');
		}
		$utc = $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		return ['canonical' => $utc . 'Z', 'text_value' => null, 'decimal_value' => null, 'datetime_value' => $utc];
	}

	private function invalidValue(array $column, int $recordIndex, string $message): never {
		throw new FlexibleStorageException('invalid_value', $message, [
			'record' => $recordIndex,
			'column' => $column['ref'],
			'type' => $column['type'],
		]);
	}
}
