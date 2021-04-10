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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class Excel implements IDatasource
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
        return $this->l10n->t('Local file') . ': Spreadsheet';
    }

    /**
     * @return int digit unique datasource id
     */
    public function getId(): int
    {
        return 7;
    }

    /**
     * @return array available options of the datasoure
     */
    public function getTemplate(): array
    {
        $template = array();
        array_push($template, ['id' => 'link', 'name' => 'Filelink', 'placeholder' => 'filelink']);
        array_push($template, ['id' => 'sheet', 'name' => $this->l10n->t('Sheet'), 'placeholder' => $this->l10n->t('sheet name')]);
        array_push($template, ['id' => 'range', 'name' => $this->l10n->t('Cell range'), 'placeholder' => $this->l10n->t('e.g. A1:C3,A5:C5')]);
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the datasoure
     * @throws NotFoundException
     * @throws \OCP\Files\NotPermittedException
     * @throws Exception
     */
    public function readData($option): array
    {

        include_once __DIR__ . '/../../vendor/autoload.php';
        $header = $dataClean = $data = array();
        $headerrow = $errorMessage = 0;

        $file = $this->rootFolder->getUserFolder($this->userId)->get($option['link']);
        $filePath = explode('/', ltrim($file->getPath(), '/'));
        // remove leading username
        array_shift($filePath);
        $filePath = implode('/', $filePath);
        $fileName = $file->getStorage()->getLocalFile($filePath);

        $inputFileType = IOFactory::identify($fileName);
        $reader = IOFactory::createReader($inputFileType);
        $reader->setReadDataOnly(true);
        if (strlen($option['sheet']) > 0) {
            $reader->setLoadSheetsOnly([$option['sheet']]);
        }

        $spreadsheet = $reader->load($fileName);

        // separated columns can be selected via ranges e.g. "A1:B9, C1:C9"
        // these ranges are read and linked
        $ranges = str_getcsv($option['range'], ',');
        foreach ($ranges as $range) {
            $values = $spreadsheet->getActiveSheet()->rangeToArray(
                $range,                // The worksheet range that we want to retrieve
                NULL,         // Value that should be returned for empty cells
                TRUE,   // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
                TRUE,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
                FALSE       // Should the array be indexed by cell row and cell column
            );
            if (empty($data)) {
                // first range will fill the array with all rows
                $data = $values;
            } else {
                // further columns will be attatched to the first ones
                foreach ($data as $key => $value) {
                    $data[$key] = array_merge($data[$key], $values[$key]);
                }

            }
        }

        foreach ($data as $key => $value) {
            if ($headerrow === 0) {
                $header = array_values($value);
                $headerrow = 1;
            } else if (!$this->containsOnlyNull($value)) {
                array_push($dataClean, array_values($value));
            }
        }

        return [
            'header' => $header,
            'dimensions' => array_slice($header, 0, count($header) - 1),
            'data' => $dataClean,
            'error' => $errorMessage,
        ];
    }

    private function containsOnlyNull($array)
    {
        return array_reduce($array, function ($carry, $item) {
            return $carry += (is_null($item) ? 0 : 1);
        }, 0) > 0 ? false : true;
    }
}