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

class Version7000Date20260923100000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->insert('analytics_whats_new')->values([
			'version' => $query->createNamedParameter('7.0.1'),
			'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Smart Picker can show live report charts and tables.","New interactive chart and table configuration with live-preview.","Spreadsheet: Select a worksheet from a dropdown.","UI and UX improvements"],"admin":["New Features apply to users"]},
"de":{"regular":["Der Smart Picker kann Live-Diagramme und -Tabellen aus Berichten anzeigen.","Neue interaktive Diagramm- und Tabellenkonfiguration mit Live-Vorschau.","Tabellenkalkulation: Ein Arbeitsblatt aus einem Dropdown auswählen.","Verbesserungen an Benutzeroberfläche und Benutzererlebnis"],"admin":["Nur User Features"]}
}}'),
		])->executeStatement();
	}
}
