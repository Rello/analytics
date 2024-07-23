<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
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
 * sudo -u www-data php occ migrations:execute analytics 4015Date20240714200000
 */
class Version4015Date20240714200000 extends SimpleMigrationStep
{
    public function __construct()
    {
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('analytics_dataset');
        if (!$table->hasColumn('ai_index')) {
            $table->addColumn('ai_index', 'integer', [
                'notnull' => false
            ]);
        }
		if ($table->hasColumn('chartoptions')) {
			$table->dropColumn('chartoptions');
		}
		if ($table->hasColumn('dataoptions')) {
			$table->dropColumn('dataoptions');
		}
		if ($table->hasColumn('filteroptions')) {
			$table->dropColumn('filteroptions');
		}
		if ($table->hasColumn('refresh')) {
			$table->dropColumn('refresh');
		}
		if ($table->hasColumn('link')) {
			$table->dropColumn('link');
		}
		if ($table->hasColumn('visualization')) {
			$table->dropColumn('visualization');
		}
		if ($table->hasColumn('chart')) {
			$table->dropColumn('chart');
		}

		$table = $schema->getTable('analytics_share');
		if (!$table->hasColumn('panorama')) {
			$table->addColumn('panorama', 'integer', [
				'notnull' => false,
			]);
		}

		return $schema;
    }
}