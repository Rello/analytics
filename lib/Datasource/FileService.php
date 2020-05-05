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

namespace OCA\Analytics\Datasource;

use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ILogger;

class FileService
{
    private $logger;
    private $rootFolder;
    private $userId;
    private $l10n;

    public function __construct(
        $userId,
        IL10N $l10n,
        ILogger $logger,
        IRootFolder $rootFolder
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->rootFolder = $rootFolder;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param array $option
     * @return array|NotFoundResponse
     * @throws NotFoundException
     */
    public function read($option)
    {
        if (isset($option['path'])) {
            $file = $this->rootFolder->getUserFolder($this->userId)->get($option['path']);
        } else {
            //$this->logger->debug('FileService 53 file content:' . $option['user_id'] . $option['link']);
            $file = $this->rootFolder->getUserFolder($option['user_id'])->get($option['link']);
        }

        //$this->logger->debug('FileService 63 file content:'. $file->getContent());
        $data = $file->getContent();
        $delimiter = $this->detectDelimiter($data);
        $rows = str_getcsv($data, "\n");
        $data = array();
        $header = array();
        $headerrow = $errorMessage = 0;

        foreach ($rows as &$row) {
            $row = str_getcsv($row, $delimiter);
            if ($headerrow === 0) {
                $header = $row;
                $headerrow = 1;
            } else {
                array_push($data, $row);
            }
        }
        $result = [
            'header' => $header,
            'data' => $data,
            'error' => $errorMessage,
        ];
        return $result;
    }

    /**
     * template for options & settings
     *
     * @NoAdminRequired
     * @return array
     */
    public function getTemplate()
    {
        $template = array();
        array_push($template, ['id' => 'link', 'name' => 'Filelink', 'placeholder' => 'filelink']);
        return $template;
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

    private function floatvalue($val)
    {
        $val = str_replace(",", ".", $val);
        $val = preg_replace('/\.(?=.*\.)/', '', $val);
        $val = preg_replace('/[^0-9-.]+/', '', $val);
        if (is_numeric($val)) {
            return floatval($val);
        } else {
            return false;
        }
    }

}