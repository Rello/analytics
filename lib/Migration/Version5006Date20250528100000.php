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
 * sudo -u www-data php occ migrations:execute analytics 5006Date20250528100000
 */
class Version5006Date20250528100000 extends SimpleMigrationStep {
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
				'version' => $query->createNamedParameter('5.6.2'),
				'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Thresholds were redesigned", "Existing thresholds are impacted", "Check the changelog", "Sorting & editing is now available"],"admin":["New Features apply to users"]},
"de":{"regular":["Schwellenwerte wurden überarbeitet", "Bestehende Settings betroffen", "Bitte Changelog prüfen", "Sortieren & Bearbeiten ist jetzt möglich"],"admin":["Nur User Features"]}
}}'),
			])->executeStatement();
	}
}