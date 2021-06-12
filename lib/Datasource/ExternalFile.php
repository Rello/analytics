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

use OCP\IL10N;
use Psr\Log\LoggerInterface;

class ExternalFile implements IDatasource
{
    private $logger;
    private $userId;
    private $l10n;

    public function __construct(
        $userId,
        IL10N $l10n,
        LoggerInterface $logger
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    /**
     * @return string Display Name of the datasource
     */
    public function getName(): string
    {
        return $this->l10n->t('External file') . ': csv';
    }

    /**
     * @return int digit unique datasource id
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
        array_push($template, ['id' => 'link', 'name' => $this->l10n->t('External URL'), 'placeholder' => 'url']);
        array_push($template, ['id' => 'offset', 'name' => $this->l10n->t('Ignore leading rows'), 'placeholder' => $this->l10n->t('Number of rows')]);
        array_push($template, ['id' => 'columns', 'name' => $this->l10n->t('Select columns'), 'placeholder' => $this->l10n->t('e.g. 1,2,4 or leave empty')]);
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the datasoure
     */
    public function readData($option): array
    {
        $data = array();
        $header = array();
        $headerrow = 0;
        $selectedColumns = array();

        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_URL, $option['link']);
            curl_setopt($ch, CURLOPT_REFERER, $option['link']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
            $curlResult = curl_exec($ch);
            curl_close($ch);
        } else {
            $curlResult = '';
        }

        $rows = str_getcsv($curlResult, "\n");

        // remove x number of rows from the beginning
        if (isset($option['offset']) and is_numeric($option['offset'])) {
            $rows = array_slice($rows, $option['offset']);
        }

        if (isset($option['columns']) && strlen($option['columns']) > 0) {
            $selectedColumns = str_getcsv($option['columns'], ',');
        }
        $delimiter = $this->detectDelimiter($rows[0]);

        foreach ($rows as &$row) {
            $row = str_getcsv($row, $delimiter);
            $rowMinimized = array();

            if (count($selectedColumns) !== 0) {
                foreach ($selectedColumns as $selectedColumn) {
                    if (is_numeric($selectedColumn)) {
                        array_push($rowMinimized, $row[$selectedColumn - 1]);
                    } else {
                        array_push($rowMinimized, $selectedColumn);
                    }
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
            'dimensions' => array_slice($header, 0, count($header) - 1),
            'data' => $data,
            'error' => 0,
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