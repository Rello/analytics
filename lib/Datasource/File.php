<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */

namespace OCA\Analytics\Datasource;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ILogger;

class File implements IDatasource
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
     * @return string Display Name of the datasource
     */
    public function getName(): string
    {
        return $this->l10n->t('Local file');
    }

    /**
     * @return int digit unique datasource id
     */
    public function getId(): int
    {
        return 1;
    }

    /**
     * @return array available options of the datasoure
     */
    public function getTemplate(): array
    {
        $template = array();
        array_push($template, ['id' => 'link', 'name' => 'Filelink', 'placeholder' => 'filelink']);
        array_push($template, ['id' => 'columns', 'name' => $this->l10n->t('Select columns'), 'placeholder' => $this->l10n->t('e.g. 1,2,4 or leave empty')]);
        array_push($template, ['id' => 'offset', 'name' => $this->l10n->t('Ignore leading rows'), 'placeholder' => $this->l10n->t('Number of rows')]);
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the datasoure
     * @throws NotFoundException
     * @throws \OCP\Files\NotPermittedException
     */
    public function readData($option): array
    {
        $data = array();
        $header = array();
        $headerrow = $errorMessage = 0;
        $selectedColumns = array();

        if (isset($option['path'])) {
            $file = $this->rootFolder->getUserFolder($this->userId)->get($option['path']);
        } else {
            $file = $this->rootFolder->getUserFolder($option['user_id'])->get($option['link']);
        }

        $rows = str_getcsv($file->getContent(), "\n");

        // remove x number of rows from the beginning
        if (isset($option['offset']) and is_numeric($option['offset'])) {
            $rows = array_slice($rows, $option['offset']);
        }

        // ensure that all values are integers
        if (isset($option['columns'])) {
            $new = array();
            $selectedColumns = str_getcsv($option['columns'], ',');
            foreach ($selectedColumns as $value) {
                if (is_numeric($value)) {
                    array_push($new, $value);
                }
            }
            $selectedColumns = $new;
        }

        $delimiter = $this->detectDelimiter($rows[0]);

        foreach ($rows as &$row) {
            $row = str_getcsv($row, $delimiter);
            $rowMinimized = array();

            if (count($selectedColumns) !== 0) {
                foreach ($selectedColumns as $selectedColumn) {
                    array_push($rowMinimized, $row[$selectedColumn - 1]);
                }
            } else {
                $rowMinimized = $row;
            }

            if ($headerrow === 0) {
                $header = $rowMinimized;
                $headerrow = 1;
            } else {
                array_push($data, $rowMinimized);
            }
        }
        return [
            'header' => $header,
            'data' => $data,
            'error' => $errorMessage,
        ];
    }

    private function detectDelimiter($data)
    {
        $delimiters = ["\t", ";", "|", ","];
        $data_2 = array();
        $delimiter = $delimiters[0];
        foreach ($delimiters as $d) {
            //$firstRow = str_getcsv($data, "\n")[0];
            $data_1 = str_getcsv($data, $d);
            if (sizeof($data_1) > sizeof($data_2)) {
                $delimiter = $d;
                $data_2 = $data_1;
            }
        }
        return $delimiter;
    }
}