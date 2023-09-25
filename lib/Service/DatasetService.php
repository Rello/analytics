<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
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

class DatasetService
{
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
    )
    {
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
    public function index()
    {
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
    public function readOwn(int $datasetId)
    {
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
    public function read(int $datasetId)
    {
        $ownDataset = $this->DatasetMapper->read($datasetId);
        return $ownDataset;
    }

    /**
     * check if own report
     *
     * @param int $reportId
     * @return bool
     */
    public function isOwn(int $datasetId)
    {
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
    public function status(int $datasetId): array
    {
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
    public function create($name, $dimension1, $dimension2, $value)
    {
        //$this->ActivityManager->triggerEvent(0, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_ADD);
        return $this->DatasetMapper->create($name, $dimension1, $dimension2, $value);
    }

    /**
     * get dataset details
     *
     * @param int $datasetId
     * @param $name
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return bool
     * @throws Exception
     */
    public function update(int $datasetId, $name, $dimension1 = null, $dimension2 = null, $value = null)
    {
        return $this->DatasetMapper->update($datasetId, $name, $dimension1, $dimension2, $value);
    }

    /**
     * Export Dataset
     *
     * @param int $datasetId
     * @return DataDownloadResponse
     * @throws Exception
     */
    public function export(int $datasetId)
    {
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
     * Delete Dataset and all depending objects
     *
     * @param int $datasetId
     * @return bool
     * @throws Exception
     */
    public function delete(int $datasetId)
    {
        $this->DatasetMapper->delete($datasetId);
        $this->DataloadMapper->deleteByDataset($datasetId);
        $this->StorageMapper->deleteByDataset($datasetId);
        return true;
    }

    /**
     * get dataset by user
     *
     * @param string $userId
     * @return bool
     * @throws Exception
     */
    public function deleteByUser(string $userId)
    {
        $datasets = $this->DatasetMapper->indexByUser($userId);
        foreach ($datasets as $dataset) {
            $this->DatasetMapper->delete($dataset['id']);
            $this->DataloadMapper->deleteByDataset($dataset['id']);
            $this->StorageMapper->deleteByDataset($dataset['id']);
        }
        return true;
    }

}