<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7001Date20261012095000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// External sources do not use internal datasets. Keep the datasets themselves intact.
		// Use the persisted type value rather than a runtime class in this migration.
		$query = $this->connection->getQueryBuilder();
		$query->update('analytics_report')
			->set('dataset', $query->createNamedParameter(0))
			->where($query->expr()->neq('type', $query->createNamedParameter(2)))
			->andWhere($query->expr()->neq('dataset', $query->createNamedParameter(0)))
			->executeStatement();
	}
}
