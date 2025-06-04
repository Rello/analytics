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
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add sequence column to analytics_threshold
 * sudo -u www-data php occ migrations:execute analytics 5007Date20250601100000
 */
class Version5007Date20250601100000 extends SimpleMigrationStep {
    public function __construct(
        private IDBConnection $connection,
    ) {
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('analytics_threshold');
        if (!$table->hasColumn('sequence')) {
            $table->addColumn('sequence', 'integer', [
                'notnull' => false,
            ]);
        }

        return $schema;
    }
}
