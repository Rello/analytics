<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ReportService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class ApiController extends OCSController {
	private ReportService $reportService;

	public function __construct(string $appName, IRequest $request, ReportService $reportService) {
		parent::__construct($appName, $request);
		$this->reportService = $reportService;
	}

	/**
	 * Create an analytics report from an existing data file.
	 *
	 * @param int $fileId ID of the file to import
	 *
	 * @return JSONResponse HTTP 200 with a link to the created report
	 *
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/createFromDataFile')]
	public function createFromDataFile(int $fileId): JSONResponse {
		$reportId = $this->reportService->createFromDataFile($fileId);
		$url = '/apps/analytics/r/' . $reportId;
		return new JSONResponse([
			'version' => 0.1,
			'root' => [
				'orientation' => 'vertical',
				'rows' => [
					[
						'children' => [
							[
								'element' => 'URL',
								'text' => 'Analytics report created',
								'url' => $url,
							],
						],
					],
				],
			],
		]);
	}
}
