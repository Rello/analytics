<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Service;

use OCA\data\Controller\DbController;
use OCP\ILogger;


class DatasetService
{
    private $logger;
    private $DBController;

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
        $data = $this->DBController->getDatasets();
        $result = empty($data) ? [
            'nodata'
        ] : [
            $data
        ];
        return $result;
    }

    /**
     * Get content of linked CSV file
     * @param $id
     * @return array
     */
    public function read($id)
    {
        $data = $this->DBController->getDataset($id);
        $result = empty($data) ? [
            'nodata'
        ] : [
            $data
        ];
        return $result;
    }

    /**
     * create new dataset
     * @return array
     */
    public function create()
    {
        $data = $this->DBController->createDataset();
        $result = empty($data) ? [
            'nodata'
        ] : [
            $data
        ];
        return $result;
    }

    /**
     * Get content of linked CSV file
     * @param $id
     * @return array
     */
    public function delete($id)
    {
        $data = $this->DBController->deleteDataset($id);
        $result = empty($data) ? [
            'nodata'
        ] : [
            $data
        ];
        return $result;
    }

    /**
     * Get content of linked CSV file
     * @param int $datasetId
     * @param $name
     * @param $type
     * @param $link
     * @param $visualization
     * @param $chart
     * @return array
     */
    public function update(int $datasetId, $name, $type, $link, $visualization, $chart)
    {
        $data = $this->DBController->updateDataset($datasetId, $name, $type, $link, $visualization, $chart);
        $result = empty($data) ? [
            'nodata'
        ] : [
            $data
        ];
        return $result;
    }

}