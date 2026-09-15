<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Storage;

use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\FlexibleStorageMapper;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Exception\FlexibleStorageException;

class DatasetStorageResolver {
	public const LEGACY = 'legacy';
	public const FLEXIBLE_SHARED = 'flexible_shared';

	public function __construct(
		private DatasetMapper $datasetMapper,
		private StorageMapper $legacyMapper,
		private FlexibleStorageMapper $flexibleMapper,
	) {
	}

	/** @return array{dataset:array<string,mixed>,mode:string,mapper:StorageMapper|FlexibleStorageMapper,tables:array{columns:?string,records:string,values:?string}} */
	public function resolve(int $datasetId, bool $ownerOnly = false): array {
		$dataset = $ownerOnly ? $this->datasetMapper->readOwn($datasetId) : $this->datasetMapper->read($datasetId);
		if (!is_array($dataset) || $dataset === []) {
			throw new FlexibleStorageException('dataset_not_found', 'The dataset does not exist or is not accessible.', ['datasetId' => $datasetId], 404);
		}

		$mode = (string)($dataset['storage_mode'] ?? self::LEGACY);
		[$mapper, $tables] = match ($mode) {
			self::LEGACY => [$this->legacyMapper, ['columns' => null, 'records' => 'analytics_facts', 'values' => null]],
			self::FLEXIBLE_SHARED => [$this->flexibleMapper, ['columns' => 'analytics_flex_columns', 'records' => 'analytics_flex_records', 'values' => 'analytics_flex_values']],
			default => throw new FlexibleStorageException('unsupported_storage_mode', 'The dataset uses an unsupported storage mode.', ['datasetId' => $datasetId, 'storageMode' => $mode], 409),
		};

		return ['dataset' => $dataset, 'mode' => $mode, 'mapper' => $mapper, 'tables' => $tables];
	}
}
