<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Datasource;

use OCP\Files\IRootFolder;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class LocalJson implements IDatasource
{
	private $rootFolder;
    private LoggerInterface $logger;
    private IL10N $l10n;

    public function __construct(
        IL10N           $l10n,
		IRootFolder $rootFolder,
        LoggerInterface $logger
    )
    {
        $this->l10n = $l10n;
		$this->rootFolder = $rootFolder;
        $this->logger = $logger;
    }

    /**
     * @return string Display Name of the datasource
     */
    public function getName(): string
    {
        return $this->l10n->t('Local') . ': JSON';
    }

    /**
     * @return int digit unique datasource id
     */
    public function getId(): int
    {
        return 6;
    }

    /**
     * @return array available options of the datasoure
     */
    public function getTemplate(): array
    {
        $template = array();
		$template[] = ['id' => 'link', 'name' => $this->l10n->t('LocalCsv'), 'placeholder' => $this->l10n->t('LocalCsv'), 'type' => 'filePicker'];
        $template[] = ['id' => 'path', 'name' => $this->l10n->t('Object path'), 'placeholder' => 'x/y/z'];
        $template[] = ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of data load'), 'placeholder' => 'true-' . $this->l10n->t('Yes') . '/false-' . $this->l10n->t('No'), 'type' => 'tf'];
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the datasoure
     */
    public function readData($option): array
    {
		$error = 0;
		$path = $option['path'];
		$file = $this->rootFolder->getUserFolder($option['user_id'])->get($option['link']);
		$rawResult = $file->getContent();

		$json = json_decode($rawResult, true);

        // check if a specific array of values should be extracted
        // e.g. {BTC,tmsp,price}
        preg_match_all("/(?<={).*(?=})/", $path, $matches);
        if (count($matches[0]) > 0) {
            // array extraction

            // check if absolute path is in front of the array
            // e.g. data/data{from,to,intensity/forecast}
            $firstArray = strpos($path, '{');
            if ($firstArray && $firstArray !== 0) {
                $singlePath = substr($path, 0, $firstArray);
                $json = $this->get_nested_array_value($json, $singlePath);
            }

            // separate the fields of the array {BTC,tmsp,price}
            $paths = explode(',', $matches[0][0]);
            // fill up with dummies in case of missing columns
            while (count($paths) < 3) {
                array_unshift($paths, 'empty');
            }
            foreach ($json as $rowArray) {
                // get the array fields from the json
                // if no match is not found, the field name will be used as a constant string
                $dim1 = $this->get_nested_array_value($rowArray, $paths[0]) ?: $paths[0];
                $dim2 = $this->get_nested_array_value($rowArray, $paths[1]) ?: $paths[1];
                $val = $this->get_nested_array_value($rowArray, $paths[2]) ?: $paths[2];
                $data[] = [$dim1, $dim2, $val];
            }
        } else {
            // single value extraction
            // e.g. data/currentHashrate,data/averageHashrate
            $paths = explode(',', $path);
            foreach ($paths as $singlePath) {
                // e.g. data/currentHashrate
                $array = $this->get_nested_array_value($json, $singlePath);

                if (is_array($array)) {
                    // if the tartet is an array itself
                    foreach ($array as $key => $value) {
                        $pathArray = explode('/', $singlePath);
                        $group = end($pathArray);
                        $data[] = [$group, $key, $value];
                    }
                } else {
                    $pathArray = explode('/', $singlePath);
                    $key = end($pathArray);
                    $data[] = ['', $key, $array];
                }
            }
        }

        $header = array();
        $header[0] = '';
        $header[1] = 'Key';
        $header[2] = 'Value';

        return [
            'header' => $header,
            'dimensions' => array_slice($header, 0, count($header) - 1),
            'data' => $data,
            'rawdata' => $rawResult,
            'error' => $error,
        ];
    }

    /**
     * get array object from string
     *
     * @NoAdminRequired
     * @param $array
     * @param $path
     * @return array|string|null
     */
    private function get_nested_array_value(&$array, $path)
    {
        $pathParts = explode('/', $path);
        $current = &$array;
        foreach ($pathParts as $key) {
            $current = &$current[$key];
        }
        return $current;
    }
}