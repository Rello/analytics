<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
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
     * @return DataResponse
     */
    public function index()
    {
        $ownDatasets = $this->DatasetMapper->index();

        // get dataload indicators for icons shown in the advanced screen
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

        return new DataResponse($ownDatasets);
    }

    /**
     * get own dataset details
     *
     * @param int $datasetId
     * @param string|null $user_id
     * @return array|bool
     * @throws \OCP\DB\Exception
     */
    public function read(int $datasetId, string $user_id = null): array
    {
        $ownDataset = $this->DatasetMapper->read($datasetId, $user_id);
        if ($ownDataset) {
            $ownDataset['permissions'] = \OCP\Constants::PERMISSION_UPDATE;
        }
        return $ownDataset;
    }

    /**
     * get dataset status
     *
     * @param int $datasetId
     * @return array|bool
     * @throws \OCP\DB\Exception
     */
    public function status(int $datasetId): array
    {
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
     * @throws \OCP\DB\Exception
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
     * @param null $dimension1
     * @param null $dimension2
     * @param null $value
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function update(int $datasetId, $name,$dimension1 = null, $dimension2 = null, $value = null)
    {
        return $this->DatasetMapper->update($datasetId, $name, $dimension1, $dimension2, $value);
    }

    /**
     * Export Dataset
     *
     * @param int $datasetId
     * @return DataDownloadResponse
     * @throws \OCP\DB\Exception
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
     * @throws \OCP\DB\Exception
     */
    public function delete(int $datasetId)
    {
        $this->DatasetMapper->delete($datasetId);
        $this->DataloadMapper->deleteDataloadByDataset($datasetId);
        $this->StorageMapper->deleteByDataset($datasetId);
        return true;
    }
}