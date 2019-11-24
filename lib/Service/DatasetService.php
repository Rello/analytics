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

namespace OCA\Analytics\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Controller\DatasetController;
use OCA\Analytics\Controller\DbController;
use OCP\ILogger;

class DatasetService
{
    private $logger;
    private $DBController;
    private $ActivityManager;

    public function __construct(
        ILogger $logger,
        DbController $DBController,
        ActivityManager $ActivityManager
    )
    {
        $this->logger = $logger;
        $this->DBController = $DBController;
        $this->ActivityManager = $ActivityManager;
    }

    /**
     * Get content of linked CSV file
     */
    public function index()
    {
        return $this->DBController->getDatasets();
    }

    /**
     * read own dataset
     * @param $id
     * @return array
     */
    public function getOwnDataset($id)
    {
        return $this->DBController->getOwnDataset($id);
    }

    /**
     * create new dataset
     * @return bool
     */
    public function create()
    {
        $this->ActivityManager->triggerEvent(0, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_ADD);
        return $this->DBController->createDataset();
    }

    /**
     * Get content of linked CSV file
     * @param int $id
     * @return bool
     */
    public function delete(int $id)
    {
        $this->ActivityManager->triggerEvent($id, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_DELETE);
        return $this->DBController->deleteDataset($id);
    }

    /**
     * Get content of linked CSV file
     * @param int $datasetId
     * @param $name
     * @param $subheader
     * @param $parent
     * @param $type
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return bool
     */
    public function update(int $datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3)
    {
        if ($type === DatasetController::DATASET_TYPE_GROUP) {
            $parent = 0;
        }
        return $this->DBController->updateDataset($datasetId, $name, $subheader, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3);
    }
}