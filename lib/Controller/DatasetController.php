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
use OCA\Analytics\Service\FlexibleStorageService;
use OCA\Analytics\Exception\FlexibleStorageException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

class DatasetController extends Controller {
	private $logger;
	private $DatasetService;
	private $ReportService;
	private FlexibleStorageService $FlexibleStorageService;

	public function __construct(
		$appName,
		IRequest $request,
		LoggerInterface $logger,
		DatasetService $DatasetService,
		ReportService $ReportService,
		FlexibleStorageService $FlexibleStorageService,
	) {
		parent::__construct($appName, $request);
		$this->logger = $logger;
		$this->DatasetService = $DatasetService;
		$this->ReportService = $ReportService;
		$this->FlexibleStorageService = $FlexibleStorageService;
	}

	/**
	 * get all datasets
	 *
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function index() {
		return new DataResponse($this->DatasetService->index());
	}

	/**
	 * create new dataset
	 *
	 * @param $name
	 * @param $dimension1
	 * @param $dimension2
	 * @param $value
	 * @return int
	 * @throws \OCP\DB\Exception
	 */
	#[NoAdminRequired]
	public function create($name, $dimension1, $dimension2, $value) {
		return $this->DatasetService->create($name, $dimension1, $dimension2, $value);
	}

	/** @param list<array<string,mixed>> $columns */
	#[NoAdminRequired]
	public function createFlexible(string $name, array $columns): DataResponse {
		try {
			return new DataResponse($this->DatasetService->createFlexible($name, $columns), Http::STATUS_CREATED);
		} catch (FlexibleStorageException $e) {
			return $this->flexibleError($e);
		}
	}

	/**
	 * get own dataset details
	 *
	 * @param int $datasetId
	 * @return array|bool
	 */
	#[NoAdminRequired]
	public function read(int $datasetId) {
		return $this->DatasetService->readOwn($datasetId);
	}

	/**
	 * Delete Dataset and all depending objects
	 *
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \OCP\DB\Exception
	 */
	#[NoAdminRequired]
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
	#[NoAdminRequired]
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

	/** @param list<array<string,mixed>> $columns */
	#[NoAdminRequired]
	public function updateSchema(int $datasetId, int $expectedSchemaVersion, array $columns, ?string $name = null): DataResponse {
		try {
			return new DataResponse($this->DatasetService->updateFlexibleSchema($datasetId, $expectedSchemaVersion, $columns, $name));
		} catch (FlexibleStorageException $e) {
			return $this->flexibleError($e);
		}
	}

	/** @param list<array<string,mixed>> $records */
	#[NoAdminRequired]
	public function upsertRecords(int $datasetId, int $schemaVersion, array $records): DataResponse {
		try {
			$result = $this->FlexibleStorageService->upsertRecords($datasetId, $schemaVersion, $records);
			$this->DatasetService->provider($datasetId);
			return new DataResponse($result);
		} catch (FlexibleStorageException $e) {
			return $this->flexibleError($e);
		}
	}

	/** @param array<string,mixed> $values */
	#[NoAdminRequired]
	public function updateRecord(int $datasetId, int $recordId, int $schemaVersion, array $values): DataResponse {
		try {
			$result = $this->FlexibleStorageService->replaceRecord($datasetId, $recordId, $schemaVersion, [['values' => $values]]);
			$this->DatasetService->provider($datasetId);
			return new DataResponse($result);
		} catch (FlexibleStorageException $e) {
			return $this->flexibleError($e);
		}
	}

	#[NoAdminRequired]
	public function deleteRecord(int $datasetId, int $recordId): DataResponse {
		try {
			$result = $this->FlexibleStorageService->deleteRecord($datasetId, $recordId);
			$this->DatasetService->provider($datasetId);
			return new DataResponse($result);
		} catch (FlexibleStorageException $e) {
			return $this->flexibleError($e);
		}
	}

	#[NoAdminRequired]
	public function query(int $datasetId): DataResponse {
		try {
			$query = $this->request->getParams();
			unset($query['datasetId']);
			return new DataResponse($this->FlexibleStorageService->query($datasetId, $query, true));
		} catch (FlexibleStorageException $e) {
			return $this->flexibleError($e);
		}
	}

        /**
         * create dataset group
         *
         * @param int $parent
         * @return int
         */
        #[NoAdminRequired]
        public function createGroup(int $parent) {
                return $this->DatasetService->createGroup($parent);
        }

        /**
         * update dataset group assignment
         *
         * @param int $datasetId
         * @param int $groupId
         * @return bool
         */
        #[NoAdminRequired]
        public function updateGroup(int $datasetId, int $groupId) {
                return $this->DatasetService->updateGroup($datasetId, $groupId);
        }

        /**
         * rename dataset
         *
         * @param int $datasetId
         * @param string $name
         * @return bool
         */
        #[NoAdminRequired]
        public function rename(int $datasetId, string $name) {
                return $this->DatasetService->rename($datasetId, $name);
        }

	/**
	 * get status of the dataset
	 *
	 * @param int $datasetId
	 * @throws \OCP\DB\Exception
	 */
	#[NoAdminRequired]
	public function status(int $datasetId) {
		$status = $this->DatasetService->status($datasetId);
		return new DataResponse($status, $status === [] ? 404 : 200);
	}

	/**
	 * Update the context chat provider
	 *
	 * @param int $datasetId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function provider(int $datasetId) {
		if ($this->DatasetService->isOwn($datasetId)) {
			$this->DatasetService->provider($datasetId);
			return new DataResponse('true');
		} else {
			return new DataResponse('false');
		}
	}

	private function flexibleError(FlexibleStorageException $exception): DataResponse {
		return new DataResponse($exception->toResponse(), $exception->getHttpStatus());
	}
}
