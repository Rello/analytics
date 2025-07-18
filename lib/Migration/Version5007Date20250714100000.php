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
 * sudo -u www-data php occ migrations:execute analytics 5007Date20250714100000
 */
class Version5007Date20250714100000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws \Doctrine\DBAL\Schema\SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('analytics_threshold');
		if (!$table->hasColumn('dimension')) {

			$table->addColumn('dimension', 'integer', [
				'notnull' => false
			]);
		}

		if (!$table->hasColumn('coloring')) {
			$table->addColumn('coloring', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}

		if (!$table->hasColumn('target')) {
			$table->addColumn('target', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}

		if (!$table->hasColumn('sequence')) {
			$table->addColumn('sequence', 'integer', [
				'notnull' => false,
			]);
		}

		if ($table->hasColumn('dimension2')) {
			$table->dropColumn('dimension2');
		}

		if ($table->hasColumn('dimension3')) {
			$table->dropColumn('dimension3');
		}

		if ($table->hasColumn('name')) {
			$table->dropColumn('name');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {

		$update = $this->connection->getQueryBuilder();
		$update->update('analytics_threshold')
			   ->set('dimension', $update->createParameter('dimension'))
			   ->set('coloring', $update->createParameter('coloring'))
			   ->set('target', $update->createParameter('target'))
			   ->where($update->expr()->eq('id', $update->createParameter('id')));

		$query = $this->connection->getQueryBuilder();
		$query->select(['id', 'value'])
			  ->from('analytics_threshold')
			  ->where($query->expr()->isNull('dimension'));
		$result = $query->executeQuery();

		while ($row = $result->fetch()) {
			$update->setParameter('dimension', 2)
				   ->setParameter('coloring', 'row')
				   ->setParameter('id', $row['id'])
				   ->setParameter('target', $row['value']);
			$update->executeStatement();
		}
	}
}