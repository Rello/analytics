<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics\Datasource;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class File implements IDatasource
{
    private $logger;
    private $rootFolder;
    private $userId;
    private $l10n;

    public function __construct(
        $userId,
        IL10N $l10n,
        LoggerInterface $logger,
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
        return $this->l10n->t('Local file') . ': csv';
    }

    /**
     * @return int digit unique data source id
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
        $template[] = ['id' => 'link', 'name' => $this->l10n->t('File'), 'placeholder' => $this->l10n->t('File'), 'type' => 'filePicker'];
        $template[] = ['id' => 'offset', 'name' => $this->l10n->t('Ignore leading rows'), 'placeholder' => $this->l10n->t('Number of rows')];
        $template[] = ['id' => 'columns', 'name' => $this->l10n->t('Select columns'), 'placeholder' => $this->l10n->t('e.g. 1,2,4 or leave empty'), 'type' => 'columnPicker'];
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
        $error = 0;
        $selectedColumns = array();

        $file = $this->rootFolder->getUserFolder($option['user_id'])->get($option['link']);
        $rows = str_getcsv($file->getContent(), "\n");

        // remove x number of rows from the beginning
        if (isset($option['offset']) and is_numeric($option['offset'])) {
            $rows = array_slice($rows, $option['offset']);
        }

        if (isset($option['columns']) && strlen($option['columns']) > 0) {
            $selectedColumns = str_getcsv($option['columns'], ',');
        }
        $delimiter = $this->detectDelimiter($rows[0]);

        $header = str_getcsv($rows[0], $delimiter);
        $rows = array_slice($rows, 1);

        if (count($selectedColumns) !== 0) {
            $header = $this->minimizeRow($selectedColumns, $header);

            foreach ($rows as $row) {
                $data[] = $this->minimizeRow($selectedColumns, str_getcsv($row, $delimiter));
            }
        } else {
            foreach ($rows as $row) {
                $data[] = str_getcsv($row, $delimiter);
            }
        }
        unset($rows);

        return [
            'header' => $header,
            'dimensions' => array_slice($header, 0, count($header) - 1),
            'data' => $data,
            'error' => $error,
        ];
    }

    private function minimizeRow($selectedColumns, $row)
    {
        $rowMinimized = array();
        foreach ($selectedColumns as $selectedColumn) {
            if (is_numeric($selectedColumn)) {
                $rowMinimized[] = $row[$selectedColumn - 1];
            } else {
                $rowMinimized[] = $selectedColumn;
            }
        }
        return $rowMinimized;
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