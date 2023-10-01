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
use OCA\Analytics\Service\VariableService;

class File implements IDatasource
{
    private $logger;
    private $rootFolder;
    private $userId;
    private $l10n;
    private $VariableService;

    public function __construct(
        $userId,
        IL10N $l10n,
        LoggerInterface $logger,
        IRootFolder $rootFolder,
        VariableService $VariableService
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->rootFolder = $rootFolder;
        $this->VariableService = $VariableService;
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
        $template[] = ['id' => 'hasHeader', 'name' => $this->l10n->t('Header row'), 'placeholder' => 'true-' . $this->l10n->t('Yes').'/false-'.$this->l10n->t('No'), 'type' => 'tf'];
        $template[] = ['id' => 'offset', 'name' => $this->l10n->t('Ignore leading rows'), 'placeholder' => $this->l10n->t('Number of rows'), 'type' => 'number'];
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
        $error = 0;
        $file = $this->rootFolder->getUserFolder($option['user_id'])->get($option['link']);
        $rows = str_getcsv($file->getContent(), "\n");

        // remove x number of rows from the beginning
        if (isset($option['offset']) and is_numeric($option['offset'])) {
            $rows = array_slice($rows, $option['offset']);
        }

        $selectedColumns = array();
        if (isset($option['columns']) && strlen($option['columns']) > 0) {
            $selectedColumns = str_getcsv($option['columns'], ',');
        }

        // get the delimiter by reading the first row
        $delimiter = $this->detectDelimiter($rows[0]);

        // the first row will define the column headers, even if it is not a real header
        // trim removes any leading or ending spaces
        $header = array_map('trim', str_getcsv($rows[0], $delimiter));

        // if the data has a real header, remove the first row
        if (!isset($option['hasHeader']) or $option['hasHeader'] !== 'false') {
            $rows = array_slice($rows, 1);
        }

        $data = array();
        if (count($selectedColumns) !== 0) {
            // if only a subset of columns or fixed column values are set, they are replaced here
            $header = $this->minimizeRow($selectedColumns, $header);
            foreach ($rows as $row) {
                $data[] = $this->minimizeRow($selectedColumns, array_map('trim', str_getcsv($row, $delimiter)));
            }
        } else {
            foreach ($rows as $row) {
                $data[] = array_map('trim', str_getcsv($row, $delimiter));
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
                // if columns contain replacement variables, they are processed here
                $rowMinimized[] = $this->VariableService->replaceDatasourceColumns($selectedColumn);
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
            $data_1 = str_getcsv($data, $d);
            if (sizeof($data_1) > sizeof($data_2)) {
                $delimiter = $d;
                $data_2 = $data_1;
            }
        }
        return $delimiter;
    }
}