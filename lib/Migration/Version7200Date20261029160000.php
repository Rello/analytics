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

class Version7200Date20261029160000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->insert('analytics_whats_new')->values([
			'version' => $query->createNamedParameter('7.2.0'),
			'data' => $query->createNamedParameter('{"changelogURL":"https://github.com/rello/analytics/blob/master/CHANGELOG.md","whatsNew":{"en":{"regular":["Filter panoramas directly from the header with compact, collapsible configuration.","Download charts as editable SVG files.","Use scoring and weighting in calculated measures, exclude lists of values, and group data by calendar quarter.","Navigate panorama pages with horizontal scrolling and enjoy simpler editing and refined charts."],"admin":["Share creation, revocation, and changes to permissions, passwords, and embedding domains are recorded in the Nextcloud admin audit log."]},"de":{"regular":["Panoramen direkt im Kopfbereich filtern und Variablen in kompakten, einklappbaren Bereichen konfigurieren.","Diagramme als bearbeitbare SVG-Dateien herunterladen.","Bewertungen und Gewichtungen in berechneten Kennzahlen verwenden, Wertelisten ausschließen und Daten nach Kalenderquartalen gruppieren.","Panoramaseiten durch horizontales Scrollen wechseln und von einfacherer Bearbeitung und überarbeiteten Diagrammen profitieren."],"admin":["Erstellung und Widerruf von Freigaben sowie Änderungen an Berechtigungen, Passwörtern und Einbettungsdomains werden im Nextcloud-Admin-Auditprotokoll erfasst."]}}}'),
		])->executeStatement();
	}
}
