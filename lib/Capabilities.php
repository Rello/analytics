<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics;

use OCP\Capabilities\ICapability;
use OCA\Analytics\AppInfo\Application;
use OCP\IL10N;

class Capabilities implements ICapability {

	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}

	/**
	 * Expose the endpoint to create a report from a csv file
	 */
	public function getCapabilities() {
		return [
			'declarativeui' => [
				Application::APP_ID => [
					'context-menu' => [
						[
							'name' => $this->l10n->t('Visualize data in Analytics'),
							'url' => '/ocs/v2.php/apps/analytics/createFromDataFile?fileId={fileId}',
							'method' => 'POST',
							'mimetype_filters' => 'text/csv',
							'bodyParams' => [],
							'icon' => '/apps/analytics/img/app.svg'
						],
					],
				],
			],
		];
	}
}