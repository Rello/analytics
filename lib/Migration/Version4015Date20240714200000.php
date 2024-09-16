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
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php occ migrations:execute analytics 4015Date20240714200000
 */
class Version4015Date20240714200000 extends SimpleMigrationStep
{
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
		if (!$table->hasColumn('item_type')) {
			$table->addColumn('item_type', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}
		if (!$table->hasColumn('item_source')) {
			$table->addColumn('item_source', 'integer', [
				'notnull' => false,
			]);
		}
		return $schema;
    }

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
	{

		$update = $this->connection->getQueryBuilder();
		$update->update('analytics_share')
			   ->set('item_type', $update->createParameter('item_type'))
			   ->set('item_source', $update->createParameter('item_source'))
			   ->where($update->expr()->eq('id', $update->createParameter('id')));

		$query = $this->connection->getQueryBuilder();
		$query->select(['id', 'report'])
			  ->from('analytics_share');
		$result = $query->executeQuery() ;

		while ($row = $result->fetch()) {
			$update
				->setParameter('item_type', 'report')
				->setParameter('item_source', $row['report'])
				->setParameter('id', $row['id'])
			;
			$update->executeStatement();
		}
	}
}