<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DatasetController extends Controller {
	private $logger;
	private $DatasetService;
	private $ReportService;

	public function __construct(
		$appName,
		IRequest $request,
		LoggerInterface $logger,
		DatasetService $DatasetService,
		ReportService $ReportService,
	) {
		parent::__construct($appName, $request);
		$this->logger = $logger;
		$this->DatasetService = $DatasetService;
		$this->ReportService = $ReportService;
	}

	/**
	 * get all datasets
	 *
	 * @NoAdminRequired
	 * @return DataResponse
	 */
	public function index() {
		return new DataResponse($this->DatasetService->index());
	}

	/**
	 * create new dataset
	 *
	 * @NoAdminRequired
	 * @param $name
	 * @param $dimension1
	 * @param $dimension2
	 * @param $value
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	public function create($name, $dimension1, $dimension2, $value) {
		return $this->DatasetService->create($name, $dimension1, $dimension2, $value);
	}

	/**
	 * get own dataset details
	 *
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return array|bool
	 */
	public function read(int $datasetId) {
		return $this->DatasetService->readOwn($datasetId);
	}

	/**
	 * Delete Dataset and all depending objects
	 *
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \OCP\DB\Exception
	 */
	public function delete(int $datasetId) {
		if ($this->DatasetService->isOwn($datasetId)) {
			$reports = $this->ReportService->reportsForDataset($datasetId);
			foreach ($reports as $report) {
				$this->ReportService->delete((int)$report['id']);
			}
			$this->DatasetService->delete($datasetId);
			return new DataResponse('true');
		} else {
			return new DataResponse('false');
		}
	}

	/**
	 * get dataset details
	 *
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @param $name
	 * @param null $subheader
	 * @param null $dimension1
	 * @param null $dimension2
	 * @param null $value
	 * @param null $aiIndex
	 * @return bool
	 * @throws Exception
	 */
	public function update(
		int $datasetId,
			$name,
			$subheader = null,
			$dimension1 = null,
			$dimension2 = null,
			$value = null,
			$aiIndex = null
	) {
		return $this->DatasetService->update($datasetId, $name, $subheader, $dimension1, $dimension2, $value, $aiIndex);
	}

	/**
	 * get status of the dataset
	 *
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @throws \OCP\DB\Exception
	 */
	public function status(int $datasetId) {
		return $this->DatasetService->status($datasetId);
	}

	/**
	 * Update the context chat provider
	 *
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 */
	public function provider(int $datasetId) {
		if ($this->DatasetService->isOwn($datasetId)) {
			$this->DatasetService->provider($datasetId);
			return new DataResponse('true');
		} else {
			return new DataResponse('false');
		}
	}
}