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

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCA\Analytics\Service\VariableService;

class ExternalFile implements IDatasource
{
    private $logger;
    private $userId;
    private $l10n;
    private $VariableService;

    public function __construct(
        $userId,
        IL10N $l10n,
        LoggerInterface $logger,
        VariableService $VariableService
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->VariableService = $VariableService;
    }

    /**
     * @return string Display Name of the datasource
     */
    public function getName(): string
    {
        return $this->l10n->t('External file') . ': csv';
    }

    /**
     * @return int digit unique data source id
     */
    public function getId(): int
    {
        return 4;
    }

    /**
     * @return array available options of the datasoure
     */
    public function getTemplate(): array
    {
        $template = array();
        $template[] = ['id' => 'link', 'name' => $this->l10n->t('External URL'), 'placeholder' => 'url'];
        $template[] = ['id' => 'hasHeader', 'name' => $this->l10n->t('Header row'), 'placeholder' => 'true-' . $this->l10n->t('Yes').'/false-'.$this->l10n->t('No'), 'type' => 'tf'];
        $template[] = ['id' => 'offset', 'name' => $this->l10n->t('Ignore leading rows'), 'placeholder' => $this->l10n->t('Number of rows'), 'type' => 'number'];
        $template[] = ['id' => 'columns', 'name' => $this->l10n->t('Select columns'), 'placeholder' => $this->l10n->t('e.g. 1,2,4 or leave empty'), 'type' => 'columnPicker'];
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the datasoure
     */
    public function readData($option): array
    {
        $url = htmlspecialchars_decode($option['link'], ENT_NOQUOTES);
        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
            $curlResult = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $curlResult = '';
        }

        $rows = str_getcsv($curlResult, "\n");

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
            'rawdata' => $curlResult,
            'error' => ($http_code>=200 && $http_code<300) ? 0 : 'HTTP response code: '.$http_code,
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