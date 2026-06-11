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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php occ migrations:execute analytics 6401Date20260610100000
 */
class Version6401Date20260610100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('analytics_facts')) {
			return null;
		}

		$table = $schema->getTable('analytics_facts');
		if (!$table->hasIndex('analytics_facts_ds_dim1_idx')) {
			$table->addIndex(['dataset', 'dimension1'], 'analytics_facts_ds_dim1_idx');
		}
		if (!$table->hasIndex('analytics_facts_ds_dim2_idx')) {
			$table->addIndex(['dataset', 'dimension2'], 'analytics_facts_ds_dim2_idx');
		}
		if (!$table->hasIndex('analytics_facts_ds_ts_idx')) {
			$table->addIndex(['dataset', 'timestamp'], 'analytics_facts_ds_ts_idx');
		}

		return $schema;
	}
}
