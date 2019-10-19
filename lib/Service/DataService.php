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
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return JSONResponse
     */
    public function read(int $datasetId, $objectDrilldown, $dateDrilldown, $userId = null)
    {
        $header = array();
        if ($objectDrilldown === 'true') array_push($header, 'Objekt');
        if ($dateDrilldown === 'true') array_push($header, 'Date');
        array_push($header, 'Value');
        $data = $this->DBController->getData($datasetId, $objectDrilldown, $dateDrilldown, $userId);

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
    public function update($datasetId, $object, $value, $date)
    {
        $insert = 0;
        $update = 0;
        $action = $this->DBController->createData($datasetId, $object, $date, $value);
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