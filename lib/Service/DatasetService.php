<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\StorageMapper;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ITagManager;
use Psr\Log\LoggerInterface;

class DatasetService {
	private $userId;
	private $logger;
	private $tagManager;
	private $ShareService;
	private $StorageMapper;
	private $DatasetMapper;
	private $ThresholdService;
	private $DataloadMapper;
	private $ActivityManager;
	private $rootFolder;
	private $VariableService;
	private $ReportMapper;
	private $contextChatManager;

	public function __construct(
		$userId,
		LoggerInterface $logger,
		ITagManager $tagManager,
		ShareService $ShareService,
		StorageMapper $StorageMapper,
		DatasetMapper $DatasetMapper,
		ThresholdService $ThresholdService,
		DataloadMapper $DataloadMapper,
		ActivityManager $ActivityManager,
		IRootFolder $rootFolder,
		VariableService $VariableService,
		ReportMapper $ReportMapper
	) {
		$this->userId = $userId;
		$this->logger = $logger;
		$this->tagManager = $tagManager;
		$this->ShareService = $ShareService;
		$this->ThresholdService = $ThresholdService;
		$this->StorageMapper = $StorageMapper;
		$this->DatasetMapper = $DatasetMapper;
		$this->DataloadMapper = $DataloadMapper;
		$this->ActivityManager = $ActivityManager;
		$this->rootFolder = $rootFolder;
		$this->VariableService = $VariableService;
		$this->ReportMapper = $ReportMapper;
	}

	/**
	 * get all datasets
	 *
	 * @return array
	 */
	public function index() {
		$ownDatasets = $this->DatasetMapper->index();

		// get data load indicators for icons shown in the advanced screen
		$dataloads = $this->DataloadMapper->getAllDataloadMetadata();
		foreach ($dataloads as $dataload) {
			$key = array_search($dataload['dataset'], array_column($ownDatasets, 'id'));
			if ($key !== '') {
				if ($dataload['schedules'] !== '' and $dataload['schedules'] !== null) {
					$dataload['schedules'] = 1;
				} else {
					$dataload['schedules'] = 0;
				}
				$ownDatasets[$key]['dataloads'] = $dataload['dataloads'];
				$ownDatasets[$key]['schedules'] = $dataload['schedules'];
			}
		}

		foreach ($ownDatasets as &$ownDataset) {
			$ownDataset['type'] = DatasourceController::DATASET_TYPE_INTERNAL_DB;
			$ownDataset['item_type'] = ShareService::SHARE_ITEM_TYPE_DATASET;
			$ownDataset = $this->VariableService->replaceTextVariables($ownDataset);
		}

		return $ownDatasets;
	}

	/**
	 * get own dataset details; used in external access like dataset controller
	 *
	 * @param int $datasetId
	 * @return array|bool
	 * @throws Exception
	 */
	public function readOwn(int $datasetId) {
		$ownDataset = $this->DatasetMapper->readOwn($datasetId);
		if (!empty($ownDataset)) {
			$ownDataset['permissions'] = \OCP\Constants::PERMISSION_UPDATE;
			$ownDataset['dataset'] = $ownDataset['id'];
		}
		return $ownDataset;
	}

	/**
	 * get  dataset details
	 *
	 * @param int $datasetId
	 * @return array|bool
	 * @throws Exception
	 */
	public function read(int $datasetId) {
		return $this->DatasetMapper->read($datasetId);
	}

	/**
	 * check if own report
	 *
	 * @param int $reportId
	 * @return bool
	 */
	public function isOwn(int $datasetId) {
		$ownDataset = $this->DatasetMapper->readOwn($datasetId);
		if (!empty($ownDataset)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * get dataset status
	 *
	 * @param int $datasetId
	 * @return array|bool
	 * @throws Exception
	 */
	public function status(int $datasetId): array {
		$status = array();
		$status['reports'] = $this->ReportMapper->reportsForDataset($datasetId);
		$status['data'] = $this->StorageMapper->getRecordCount($datasetId);
		return $status;
	}

	/**
	 * create new dataset
	 *
	 * @param $name
	 * @param $dimension1
	 * @param $dimension2
	 * @param $value
	 * @return int
	 * @throws Exception
	 */
	public function create($name, $dimension1, $dimension2, $value) {
		$datasetId = $this->DatasetMapper->create($name, $dimension1, $dimension2, $value);
		$this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_ADD);
		return $datasetId;
	}

	/**
	 * get dataset details
	 *
	 * @param int $datasetId
	 * @param $name
	 * @param null $dimension1
	 * @param null $dimension2
	 * @param null $value
	 * @param null $subheader
	 * @param null $aiIndex
	 * @return bool
	 * @throws Exception
	 */
	public function update(int $datasetId, $name, $subheader, $dimension1, $dimension2, $value, $aiIndex) {
		$dbUpdate = $this->DatasetMapper->update($datasetId, $name, $subheader, $dimension1, $dimension2, $value, $aiIndex);

		if ($aiIndex === 1) {
			$this->provider($datasetId);
		} else {
			$this->providerRemove($datasetId);
		}
		return $dbUpdate;
	}

	/**
	 * Export Dataset
	 *
	 * @param int $datasetId
	 * @return DataDownloadResponse
	 * @throws Exception
	 */
	public function export(int $datasetId) {
		$result = array();
		$result['dataset'] = $this->DatasetMapper->read($datasetId);
		$result['dataload'] = $this->DataloadMapper->read($datasetId);
		$result['threshold'] = $this->ThresholdService->read($datasetId);
		$result['favorite'] = '';

		if ($result['dataset']['type'] === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
			$result['data'] = $this->StorageMapper->read($datasetId);
		}

		unset($result['dataset']['id'], $result['dataset']['user_id'], $result['dataset']['user_id'], $result['dataset']['parent']);
		$data = json_encode($result);
		return new DataDownloadResponse($data, $result['dataset']['name'] . '.export.txt', 'text/plain; charset=utf-8');
	}

	/**
	 * Update the context chat provider
	 *
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return bool
	 */
	public function provider(int $datasetId) {
		if (class_exists('OCA\ContextChat\Public\ContentManager')) {
			$this->contextChatManager = \OC::$server->query('OCA\Analytics\ContextChat\ContextChatManager');
			$this->contextChatManager->submitContent($datasetId);
		}
		return true;
	}

	/**
	 * Remove dataset from context chat
	 *
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return bool
	 */
	private function providerRemove(int $datasetId) {
		if (class_exists('OCA\ContextChat\Public\ContentManager')) {
			$this->contextChatManager = \OC::$server->query('OCA\Analytics\ContextChat\ContextChatManager');
			$this->contextChatManager->removeContentByDataset($datasetId);
		}
		return true;
	}

	/**
	 * Remove user from context chat
	 *
	 * @NoAdminRequired
	 * @param string $userId
	 * @return bool
	 */
	private function providerRemoveByUser(string $userId) {
		if (class_exists('OCA\ContextChat\Public\ContentManager')) {
			$this->contextChatManager = \OC::$server->query('OCA\Analytics\ContextChat\ContextChatManager');
			$this->contextChatManager->removeContentByUser($userId);
		}
		return true;
	}

	/**
	 * Delete Dataset and all depending objects
	 *
	 * @param int $datasetId
	 * @return bool
	 * @throws Exception
	 */
	public function delete(int $datasetId) {
		$this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_DELETE);
		$this->DatasetMapper->delete($datasetId);
		$this->DataloadMapper->deleteByDataset($datasetId);
		$this->StorageMapper->deleteByDataset($datasetId);
		$this->providerRemove($datasetId);
		return true;
	}

	/**
	 * get dataset by user
	 *
	 * @param string $userId
	 * @return bool
	 * @throws Exception
	 */
	public function deleteByUser(string $userId) {
		$datasets = $this->DatasetMapper->indexByUser($userId);
		foreach ($datasets as $dataset) {
			$this->DatasetMapper->delete($dataset['id']);
			$this->DataloadMapper->deleteByDataset($dataset['id']);
			$this->StorageMapper->deleteByDataset($dataset['id']);
		}
		$this->providerRemoveByUser($userId);
		return true;
	}

}