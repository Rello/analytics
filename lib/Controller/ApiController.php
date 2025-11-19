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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCSController;
use Psr\Log\LoggerInterface;
use OCP\IRequest;

class ApiController extends OCSController {
	private ReportService $reportService;
	private $logger;

	public function __construct(string          $appName,
								IRequest        $request,
								LoggerInterface $logger,
								ReportService   $reportService
	) {
		parent::__construct($appName, $request);
		$this->reportService = $reportService;
		$this->logger = $logger;
	}

	/**
	 * Create an analytics report from an existing data file.
	 *
	 * @param int $fileId ID of the file to import
	 *
	 * @return DataResponse HTTP 200 with a link to the created report
	 *
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/createFromDataFile')]
	public function createFromDataFile($fileId): DataResponse {
		if ($fileId) {
			$reportId = $this->reportService->createFromDataFile($fileId);
			$url = '/apps/analytics/r/' . $reportId;
			return new DataResponse([
				'version' => '0.1',
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
		} else {
			return new DataResponse(['error' => 'fileId missing'], HTTP::STATUS_BAD_REQUEST);
		}
	}
}