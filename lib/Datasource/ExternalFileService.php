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

use OCP\ILogger;

class ExternalFileService
{
    private $logger;
    private $userId;

    public function __construct(
        $userId,
        ILogger $logger
    )
    {
        $this->userId = $userId;
        $this->logger = $logger;
    }

    /**
     * Get the content from an external url
     *
     * @NoAdminRequired
     * @param array $option
     * @return array
     */
    public function read($option)
    {
        //$this->logger->error('dataset path: ' . $datasetMetadata['link']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $option['link']);
        curl_setopt($ch, CURLOPT_REFERER, $option['link']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        $data = curl_exec($ch);
        curl_close($ch);

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