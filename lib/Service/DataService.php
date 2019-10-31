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

class DataService
{
    private $logger;
    private $DatasetService;
    private $DBController;

    public function __construct(
        ILogger $logger,
        DatasetService $DatasetService,
        DbController $DBController
    )
    {
        $this->logger = $logger;
        $this->DatasetService = $DatasetService;
        $this->DBController = $DBController;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param $datasetMetadata
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return array
     */
    public function read($datasetMetadata, $objectDrilldown, $dateDrilldown)
    {
        $header = array();
        if ($objectDrilldown === 'true') $header['dimension1'] = $datasetMetadata['dimension1'];
        if ($dateDrilldown === 'true') $header['dimension2'] = $datasetMetadata['dimension2'];
        $header['dimension3'] = $datasetMetadata['dimension3'];

        $data = $this->DBController->getData($datasetMetadata['id'], $objectDrilldown, $dateDrilldown);

        $result = empty($data) ? [
            'status' => 'nodata'
        ] : [
            'header' => $header,
            'data' => $data
        ];
        return $result;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $object
     * @param $value
     * @param $date
     * @return array
     */
    public function update($datasetId, $dimension1, $dimension2, $dimension3)
    {
        $insert = 0;
        $update = 0;
        $action = $this->DBController->createData($datasetId, $dimension1, $dimension2, $dimension3);
        if ($action === 'insert') $insert++;
        elseif ($action === 'update') $update++;

        $result = [
            'insert' => $insert,
            'update' => $update
        ];
        return $result;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $import
     * @return array
     */
    public function import($datasetId, $import)
    {
        $insert = 0;
        $update = 0;
        $delimiter = $this->detectDelimiter($import);
        $rows = str_getcsv($import, "\n");

        foreach ($rows as &$row) {
            $row = str_getcsv($row, $delimiter);
            $action = $this->DBController->createData($datasetId, $row[0], $row[1], $row[2]);
            if ($action === 'insert') $insert++;
            elseif ($action === 'update') $update++;
        }

        $result = [
            'insert' => $insert,
            'update' => $update,
            'delimiter' => $delimiter
        ];
        return $result;
    }


    private function detectDelimiter($data)
    {
        $delimiters = ["\t", ";", "|", ","];
        $data_1 = null;
        $data_2 = null;
        $delimiter = $delimiters[0];
        foreach ($delimiters as $d) {
            $firstRow = str_getcsv($data, "\n")[0];
            $data_1 = str_getcsv($firstRow, $d);
            if (sizeof($data_1) > sizeof($data_2)) {
                $delimiter = $d;
                $data_2 = $data_1;
            }
        }

        return $delimiter;
    }

}