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
 * sudo -u www-data php occ migrations:execute analytics 6000Date20250917100000
 */
class Version6000Date20250917100000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$query = $this->connection->getQueryBuilder();
		$query->insert('analytics_whats_new')->values([
				'version' => $query->createNamedParameter('6.0.0'),
				'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Cached reports with stored data", "Save reports before exiting", "Various performance improvements"],"admin":["New Features apply to users"]},
"de":{"regular":["Cache von Berichten mit gespeicherten Daten ", "Berichte vor dem Verlassen speichern", "Verschiedene Leistungsverbesserungen"],"admin":["Nur User Features"]}
}}'),
			])->executeStatement();
	}
}