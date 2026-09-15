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

/**
 * Auto-generated migration step: Please modify to your needs!
 * sudo -u www-data php occ migrations:execute analytics 6801Date20260827100000
 */
class Version6801Date20260827100000 extends SimpleMigrationStep {
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
				'version' => $query->createNamedParameter('6.8.1'),
				'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Visualizations, dashboard widgets, and panorama picture selection now work on Nextcloud 35.", "Nextcloud Tables and their views are now grouped in the data source selector.", "Internal IP addresses can now be used for external data sources when allowed by the Nextcloud server."],"admin":["New Features apply to users"]},
"de":{"regular":["Visualisierungen, Dashboard-Widgets und die Bildauswahl in Panoramen funktionieren wieder unter Nextcloud 35.", "Nextcloud-Tabellen und ihre Ansichten werden jetzt gruppiert in der Datenquellenauswahl angezeigt.", "Interne IP-Adressen können jetzt für externe Datenquellen verwendet werden, wenn dies vom Nextcloud-Server erlaubt ist."],"admin":["Nur User Features"]}
}}'),
			])->executeStatement();
	}
}
