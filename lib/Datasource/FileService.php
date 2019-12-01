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

namespace OCA\Analytics\Datasource;

use OCA\Analytics\Controller\DbController;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;

class FileService
{
    private $logger;
    private $DBController;
    private $rootFolder;
    private $userId;

    //private $StorageController;

    public function __construct(
        $userId,
        ILogger $logger,
        IRootFolder $rootFolder,
        DbController $DBController
        //StorageController $StorageController
    )
    {
        $this->userId = $userId;
        $this->logger = $logger;
        $this->DBController = $DBController;
        $this->rootFolder = $rootFolder;
        //$this->StorageController = $StorageController;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param $datasetMetadata
     * @param $path
     * @return array|NotFoundResponse
     * @throws NotFoundException
     */
    public function read($datasetMetadata = null, $path = null)
    {
        //$this->logger->error('FileService path: ' . $datasetMetadata['link']);
        if (empty($path)) {
            $file = $this->rootFolder->getUserFolder($datasetMetadata['user_id'])->get($datasetMetadata['link']);
        } else {
            $file = $this->rootFolder->getUserFolder($this->userId)->get($path);
        }

        $data = $file->getContent();
        $delimiter = $this->detectDelimiter($data);
        $rows = str_getcsv($data, "\n");
        $data = array();
        $header = array();
        $headerrow = 0;

        foreach ($rows as &$row) {
            $row = str_getcsv($row, $delimiter);
            if ($headerrow === 0) {
                $header['dimension1'] = $row[0];
                $header['dimension2'] = $row[1];
                $header['dimension3'] = $row[2];
                $headerrow = 1;
            } else {
                $row[2] = str_replace(',', '.', $row[2]);
                array_push($data, ['dimension1' => $row[0], 'dimension2' => $row[1], 'dimension3' => $row[2]]);
            }
        }
        $result = [
            'header' => $header,
            'data' => $data
        ];
        return $result;
    }

    private function detectDelimiter($data)
    {
        $delimiters = ["\t", ";", "|", ","];
        $data_2 = array();
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