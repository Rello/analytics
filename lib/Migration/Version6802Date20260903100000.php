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

class Version6802Date20260903100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('analytics_report');
		foreach (['chartoptions', 'dataoptions', 'filteroptions', 'tableoptions'] as $column) {
			$table->changeColumn($column, [
				'type' => 'text',
				'notnull' => false,
			]);
		}

		return $schema;
	}
}