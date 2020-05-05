<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;

class DatasetController extends Controller
{
    private $logger;
    private $ShareController;
    private $StorageMapper;
    private $DatasetMapper;
    private $ThresholdMapper;
    private $DataloadMapper;
    private $ActivityManager;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        ShareController $ShareController,
        StorageMapper $StorageMapper,
        DatasetMapper $DatasetMapper,
        ThresholdMapper $ThresholdMapper,
        DataloadMapper $DataloadMapper,
        ActivityManager $ActivityManager
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ShareController = $ShareController;
        $this->StorageMapper = $StorageMapper;
        $this->DatasetMapper = $DatasetMapper;
        $this->ThresholdMapper = $ThresholdMapper;
        $this->DataloadMapper = $DataloadMapper;
        $this->ActivityManager = $ActivityManager;
    }

    /**
     * get all datasets
     *
     * @NoAdminRequired
     */
    public function index()
    {
        $ownDatasets = $this->DatasetMapper->getDatasets();
        $sharedDatasets = $this->ShareController->getSharedDatasets();
        $ownDatasets = array_merge($ownDatasets, $sharedDatasets);

        $dataloads = $this->DataloadMapper->getAllDataloadMetadata();
        foreach ($dataloads as $dataload) {
            $key = array_search($dataload['dataset'], array_column($ownDatasets, 'id'));
            //$this->logger->debug($key);
            if ($key !== '') {
                $ownDatasets[$key]['dataloads'] = $dataload['dataloads'];
                $ownDatasets[$key]['schedules'] = $dataload['schedules'];
            }
        }
        return new DataResponse($ownDatasets);
    }

    /**
     * get own dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return array
     */
    public function read(int $datasetId)
    {
        return $this->getOwnDataset($datasetId);
    }

    /**
     * get own dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param string|null $user_id
     * @return array
     */
    public function getOwnDataset(int $datasetId, string $user_id = null)
    {
        return $this->DatasetMapper->getOwnDataset($datasetId, $user_id);
    }

    /**
     * create new dataset
     *
     * @NoAdminRequired
     * @param string $file
     * @param string $link
     * @return int
     */
    public function create($file = '', $link = '')
    {
        //$this->logger->error('datasetcontroller 82: '.$file);
        $this->ActivityManager->triggerEvent(0, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_ADD);
        $datasetId = $this->DatasetMapper->createDataset();

        if ($file === 'DEMO') {
            $name = 'Demo Report';
            $subheader = 'CSV Demo Data from GitHub';
            $parent = 0;
            $type = DataSourceController::DATASET_TYPE_EXTERNAL_FILE;
            $link = 'https://raw.githubusercontent.com/Rello/analytics/master/sample_data/sample1.csv';
            $visualization = 'ct';
            $chart = 'line';
            $this->update($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, '', '', '', '');
        } elseif ($file !== '') {
            $name = explode('.', end(explode('/', $file)))[0];
            $subheader = $file;
            $parent = 0;
            $type = DataSourceController::DATASET_TYPE_INTERNAL_FILE;
            $link = $file;
            $visualization = 'table';
            $chart = 'line';
            $this->update($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, '', '', '', '');
        }
        return $datasetId;
    }

    /**
     * Delete Dataset and all depending objects
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return bool
     */
    public function delete(int $datasetId)
    {
        $this->ShareController->deleteShareByDataset($datasetId);
        $this->StorageMapper->deleteDataByDataset($datasetId);
        $this->DatasetMapper->deleteDataset($datasetId);
        $this->ThresholdMapper->deleteThresholdByDataset($datasetId);
        $this->DataloadMapper->deleteDataloadByDataset($datasetId);
        $this->ActivityManager->triggerEvent(0, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_DELETE);
        return true;
    }

    /**
     * get dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $name
     * @param $subheader
     * @param int $parent
     * @param int $type
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $chartoptions
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return bool
     */
    public function update(int $datasetId, $name, $subheader, int $parent, int $type, $link, $visualization, $chart, $chartoptions, $dimension1, $dimension2, $dimension3)
    {
        if ($type === DataSourceController::DATASET_TYPE_GROUP) {
            $parent = 0;
        }
        return $this->DatasetMapper->updateDataset($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, $chartoptions, $dimension1, $dimension2, $dimension3);
    }
}