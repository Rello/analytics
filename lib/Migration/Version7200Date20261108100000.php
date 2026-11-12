<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7200Date20261108100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$share = $schema->getTable('analytics_share');
		if ($share->hasColumn('parent')) {
			// Legacy column absent from fresh installations and unused by current sharing.
			$share->dropColumn('parent');
		}

		$schema->getTable('analytics_dataload')->getColumn('option')
			->setType(Type::getType(Types::TEXT));

		return $schema;
	}
}
