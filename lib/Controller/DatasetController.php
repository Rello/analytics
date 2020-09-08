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
use OCP\ITagManager;

class DatasetController extends Controller
{
    private $logger;
    private $tagManager;
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
        ITagManager $tagManager,
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
        $this->tagManager = $tagManager;
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
        $favorites = $this->tagManager->load('analytics')->getFavorites();

        $dataloads = $this->DataloadMapper->getAllDataloadMetadata();
        foreach ($dataloads as $dataload) {
            $key = array_search($dataload['dataset'], array_column($ownDatasets, 'id'));
            //$this->logger->debug($key);
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
            $hasTag = 0;
            if (is_array($favorites) and in_array($ownDataset['id'], $favorites)) {
                $hasTag = 1;
            }
            $ownDataset['favorite'] = $hasTag;
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
     * search for datasets
     *
     * @NoAdminRequired
     * @param string $searchString
     * @return array
     */
    public function search(string $searchString)
    {
        return $this->DatasetMapper->search($searchString);
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
     * get own datasets which are marked as favorites
     *
     * @NoAdminRequired
     * @return array|bool
     */
    public function getOwnFavoriteDatasets()
    {
        return $this->tagManager->load('analytics')->getFavorites();
    }

    /**
     * set/remove the favorite flag for a report
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param string $favorite
     * @return bool
     */
    public function setFavorite(int $datasetId, string $favorite)
    {
        if ($favorite === 'true') {
            $return = $this->tagManager->load('analytics')->addToFavorites($datasetId);
        } else {
            $return = $this->tagManager->load('analytics')->removeFromFavorites($datasetId);
        }
        return $return;
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
        $this->setFavorite($datasetId, 'true');

        if ($file === 'DEMO') {
            $name = 'Demo Report';
            $subheader = 'CSV Demo Data from GitHub';
            $parent = 0;
            $type = DataSourceController::DATASET_TYPE_EXTERNAL_FILE;
            $link = 'https://raw.githubusercontent.com/Rello/analytics/master/sample_data/sample1.csv';
            $visualization = 'ct';
            $chart = 'line';
            $this->update($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, '', '');
        } elseif ($file !== '') {
            $name = explode('.', end(explode('/', $file)))[0];
            $subheader = $file;
            $parent = 0;
            $type = DataSourceController::DATASET_TYPE_INTERNAL_FILE;
            $link = $file;
            $visualization = 'table';
            $chart = 'line';
            $this->update($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, '', '');
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
     * @param $dataoptions
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return bool
     */
    public function update(int $datasetId, $name, $subheader, int $parent, int $type, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1 = null, $dimension2 = null, $value = null)
    {
        if ($type === DataSourceController::DATASET_TYPE_GROUP) {
            $parent = 0;
        }
        return $this->DatasetMapper->updateDataset($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value);
    }

    /**
     * get dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return bool
     */
    public function updateOptions(int $datasetId, $chartoptions, $dataoptions, $filteroptions)
    {
        return $this->DatasetMapper->updateDatasetOptions($datasetId, $chartoptions, $dataoptions, $filteroptions);
    }
}