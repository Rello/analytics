<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCP\AppFramework\OCS\OCSNotFoundException;
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

	public function __construct(
		string $appName,
		IRequest $request,
		LoggerInterface $logger,
		ReportService $reportService,
		private ShareService $shareService,
		private OutputController $outputController,
		private ApiDataController $apiDataController
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

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/reports')]
	public function index(): DataResponse {
		$reports = array_values(array_filter(
			$this->reportService->index(),
			static fn (array $report): bool => (int)$report['type'] !== ReportService::REPORT_TYPE_GROUP
		));
		return new DataResponse(array_map(
			static fn (array $report): array => array_intersect_key($report, array_flip(['id', 'name', 'dataset'])),
			$reports
		));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/reports/{reportId}')]
	public function read(int $reportId): DataResponse {
		return new DataResponse($this->publicMetadata($this->accessibleReport($reportId)));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/reports/{reportId}/data')]
	public function data(int $reportId): DataResponse {
		// Use the viewer pipeline with saved options only, without request overrides
		// or conditional caching: every successful response contains the report data.
		$result = $this->outputController->getData($this->accessibleReport($reportId));
		unset($result['cache']);
		if (isset($result['options'])) {
			$result['options'] = $this->publicMetadata($result['options']);
		}
		return new DataResponse($result);
	}

	/** List owned datasets with their column labels. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/datasets')]
	public function datasets(): DataResponse {
		return new DataResponse(array_map(
			static fn (array $dataset): array => array_intersect_key($dataset, array_flip([
				'id', 'name', 'dimension1', 'dimension2', 'value',
			])),
			$this->apiDataController->datasetIndexV3()
		));
	}

	/** Add or update rows using the existing V4 validation and write path. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/datasets/{datasetId}/data/add')]
	public function addData(int $datasetId): DataResponse {
		return $this->apiDataController->addDataV4($datasetId);
	}

	/** Delete rows using the existing V4 structured-filter write path. */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/datasets/{datasetId}/data/delete')]
	public function deleteData(int $datasetId): DataResponse {
		return $this->apiDataController->deleteDataV4($datasetId);
	}

	private function accessibleReport(int $reportId): array {
		$report = $this->reportService->read($reportId);
		if (empty($report)) {
			$report = $this->shareService->getSharedReport($reportId);
		}
		if (empty($report) || (int)$report['type'] === ReportService::REPORT_TYPE_GROUP) {
			throw new OCSNotFoundException('Report not found');
		}
		return $report;
	}

	private function publicMetadata(array $report): array {
		return array_intersect_key($report, array_flip([
			'id', 'name', 'subheader', 'type', 'parent', 'dataset', 'version',
			'visualization', 'chart', 'chartoptions', 'dataoptions', 'tableoptions',
			'filteroptions', 'refresh', 'permissions', 'isShare',
			'storageMode', 'schemaVersion', 'columns', 'dimension1', 'dimension2', 'value',
		]));
	}
}
