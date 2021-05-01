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
use OCP\ILogger;

class Json implements IDatasource
{
    private $logger;
    private $l10n;

    public function __construct(
        IL10N $l10n,
        ILogger $logger
    )
    {
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    /**
     * @return string Display Name of the datasource
     */
    public function getName(): string
    {
        return $this->l10n->t('JSON');
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
        array_push($template, ['id' => 'url', 'name' => 'URL', 'placeholder' => 'url']);
        array_push($template, ['id' => 'auth', 'name' => $this->l10n->t('Authentication'), 'placeholder' => 'User:Password']);
        array_push($template, ['id' => 'path', 'name' => $this->l10n->t('Object path'), 'placeholder' => 'x/y/z']);
        array_push($template, ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of dataload'), 'placeholder' => $this->l10n->t('true/false'), 'type' => 'tf']);
        return $template;
    }

    /**
     * Read the Data
     * @param $option
     * @return array available options of the datasoure
     */
    public function readData($option): array
    {
        $string = $option['url'];
        $path = $option['path'];
        $auth = $option['auth'];
        $data = array();

        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_URL, $string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'OCS-APIRequest: true'
            ));
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $curlResult = curl_exec($ch);
            curl_close($ch);
        } else {
            $curlResult = '';
        }

        $json = json_decode($curlResult, true);
        $paths = explode(',', $path);

        foreach ($paths as $singlePath) {
            $array = $this->get_nested_array_value($json, $singlePath);

            if (is_array($array)) {
                foreach ($array as $key => $value) {
                    $pathArray = explode('/', $singlePath);
                    $group = end($pathArray);
                    array_push($data, [$group, $key, $value]);
                }
            } else {
                $pathArray = explode('/', $singlePath);
                $key = end($pathArray);
                array_push($data, ['', $key, $array]);
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
            'error' => 0,
        ];
    }

    /**
     * get array object from string
     *
     * @NoAdminRequired
     * @param $array
     * @param $path
     * @param string $delimiter
     * @return array|string
     */
    private function get_nested_array_value(&$array, $path, $delimiter = '/')
    {
        $pathParts = explode($delimiter, $path);

        $current = &$array;
        foreach ($pathParts as $key) {
            $current = &$current[$key];
        }
        return $current;
    }

}