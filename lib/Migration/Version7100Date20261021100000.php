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
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7100Date20261021100000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->insert('analytics_whats_new')->values([
			'version' => $query->createNamedParameter('7.1.0'),
			'data' => $query->createNamedParameter('{"changelogURL":"https:\/\/github.com\/rello\/analytics\/blob\/master\/CHANGELOG.md","whatsNew":{
"en":{"regular":["Choose JSON datasource fields visually with a browsable hierarchy and selection preview.","Preview live datasource data in the report wizard and sidebar before saving.","Access reports and datasets through new OCS API endpoints.","Simpler folder navigation and fixes for shared panorama report tiles."],"admin":["New Features apply to users"]},
"de":{"regular":["Felder von JSON-Datenquellen visuell in einer durchsuchbaren Hierarchie mit Auswahlvorschau auswählen.","Live-Daten aus Datenquellen vor dem Speichern im Berichtsassistenten und in der Seitenleiste anzeigen.","Über neue OCS-API-Endpunkte auf Berichte und Datensätze zugreifen.","Einfachere Ordnernavigation und Korrekturen für Berichtskacheln in geteilten Panoramen."],"admin":["Nur User Features"]}
}}'),
		])->executeStatement();
	}
}
