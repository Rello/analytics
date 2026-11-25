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

class Version7300Date20261125100000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$notes = [
			'changelogURL' => 'https://github.com/Rello/analytics/blob/7.3.0/CHANGELOG.md',
			'whatsNew' => [
				'en' => [
					'regular' => [
						'Import related datasets, reports, folders, and Panoramas together in one JSON file. The new Business performance demo uses this format.',
						'View datasource previews and data load simulations as tables. Expand the raw data to inspect individual values.',
						'Navigate report controls with the keyboard and read chart-only reports as tables with a screen reader.',
						'Panorama tiles adapt to smaller windows and preserve currency, percentage, and decimal formatting in KPIs.',
					],
					'admin' => ['New Features apply to users'],
				],
				'de' => [
					'regular' => [
						'Zusammengehörige Datensätze, Berichte, Ordner und Panoramen aus einer einzigen JSON-Datei importieren. Die neue Demo zur Geschäftsentwicklung nutzt dieses Format.',
						'Die Vorschau von Datenquellen und die Simulation von Ladevorgängen als Tabellen ansehen. Zum Prüfen einzelner Werte lassen sich die Rohdaten einblenden.',
						'Berichte mit der Tastatur bedienen und reine Diagrammberichte mit dem Screenreader als Tabellen lesen.',
						'Panoramakacheln passen sich an kleinere Fenster an. Währungs-, Prozent- und Dezimalformate bleiben bei Kennzahlen erhalten.',
					],
					'admin' => ['Nur User Features'],
				],
			],
		];

		$query = $this->connection->getQueryBuilder();
		$query->insert('analytics_whats_new')->values([
			'version' => $query->createNamedParameter('7.3.0'),
			'data' => $query->createNamedParameter(json_encode($notes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
		])->executeStatement();
	}
}
