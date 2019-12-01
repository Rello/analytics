<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Activity\ActivityManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;

class DatasetController extends Controller
{
    private $logger;
    private $ShareController;
    private $DBController;
    private $ActivityManager;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        ShareController $ShareController,
        DbController $DBController,
        ActivityManager $ActivityManager
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ShareController = $ShareController;
        $this->DBController = $DBController;
        $this->ActivityManager = $ActivityManager;
    }

    /**
     * get all datasets
     *
     * @NoAdminRequired
     */
    public function index()
    {
        $ownDatasets = $this->DBController->getDatasets();
        $sharedDatasets = $this->ShareController->getSharedDatasets();
        $ownDatasets = array_merge($ownDatasets, $sharedDatasets);
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
        return $this->DBController->getOwnDataset($datasetId);
    }

    /**
     * get own dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return array
     */
    public function getOwnDataset(int $datasetId)
    {
        return $this->read($datasetId);
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
        $datasetId = $this->DBController->createDataset();

        if ($file === 'DEMO') {
            $name = 'Demo Report';
            $subheader = 'CSV Demo Data from GitHub';
            $parent = 0;
            $type = DataSourceController::DATASET_TYPE_EXTERNAL_FILE;
            $link = 'https://raw.githubusercontent.com/Rello/analytics/master/sample_data/sample1.csv';
            $visualization = 'table';
            $chart = 'line';
            $this->update($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, '', '', '');
        } elseif ($file !== '') {
            $name = explode('.', end(explode('/', $file)))[0];
            $subheader = $file;
            $parent = 0;
            $type = DataSourceController::DATASET_TYPE_INTERNAL_FILE;
            $visualization = 'ct';
            $chart = 'line';
            $this->update($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, '', '', '');
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
        $this->DBController->deleteDataByDataset($datasetId);
        $this->DBController->deleteDataset($datasetId);
        $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_DELETE);
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
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return bool
     */
    public function update(int $datasetId, $name, $subheader, int $parent, int $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3)
    {
        if ($type === DataSourceController::DATASET_TYPE_GROUP) {
            $parent = 0;
        }
        return $this->DBController->updateDataset($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3);
    }
}