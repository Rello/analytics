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

use OCA\Analytics\Controller\DbController;
use OCP\ILogger;

class DatasetService
{
    private $logger;
    private $DBController;
    private $activityManager;

    public function __construct(
        ILogger $logger,
        DbController $DBController
    )
    {
        $this->logger = $logger;
        $this->DBController = $DBController;
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
     * @return array
     */
    public function create()
    {
        return $this->DBController->createDataset();
    }

    /**
     * Get content of linked CSV file
     * @param $id
     * @return array
     */
    public function delete($id)
    {
        return $this->DBController->deleteDataset($id);
    }

    /**
     * Get content of linked CSV file
     * @param int $datasetId
     * @param $name
     * @param $parent
     * @param $type
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return array
     */
    public function update(int $datasetId, $name, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3)
    {
        return $this->DBController->updateDataset($datasetId, $name, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3);
    }
}