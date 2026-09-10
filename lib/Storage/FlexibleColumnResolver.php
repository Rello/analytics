<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Storage;

use OCA\Analytics\Exception\FlexibleStorageException;

class FlexibleColumnResolver {
	/** @param list<array<string,mixed>> $columns */
	public function __construct(private array $columns) {
	}

	/** @return array<string,mixed> */
	public function resolve(string $reference): array {
		if (!preg_match('/^c_([1-9][0-9]*)$/D', $reference, $matches)) {
			throw new FlexibleStorageException('invalid_column_reference', 'Invalid flexible column reference.', ['column' => $reference]);
		}
		$id = (int)$matches[1];
		foreach ($this->columns as $column) {
			if ((int)$column['id'] === $id) {
				return $column;
			}
		}
		throw new FlexibleStorageException('unknown_column', 'The referenced column does not belong to this dataset.', ['column' => $reference]);
	}

	/** @return list<array<string,mixed>> */
	public function all(): array {
		return $this->columns;
	}

	public static function valueField(array $column): string {
		return match ($column['type']) {
			'text' => 'text_value',
			'decimal', 'boolean' => 'decimal_value',
			'date', 'datetime' => 'datetime_value',
			default => throw new FlexibleStorageException('invalid_column_type', 'Unsupported logical column type.', ['column' => $column['ref']]),
		};
	}
}
