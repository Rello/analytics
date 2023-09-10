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

class Json implements IDatasource
{
    private LoggerInterface $logger;
    private IL10N $l10n;

    public function __construct(
        IL10N           $l10n,
        LoggerInterface $logger
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
        $template[] = ['id' => 'url', 'name' => 'URL', 'placeholder' => 'url'];
        $template[] = ['id' => 'method', 'name' => $this->l10n->t('HTTP method'), 'placeholder' => 'GET/POST', 'type' => 'tf'];
        $template[] = ['id' => 'path', 'name' => $this->l10n->t('Object path'), 'placeholder' => 'x/y/z'];
        $template[] = ['id' => 'section', 'name' => $this->l10n->t('More options'), 'type' => 'section'];
        $template[] = ['id' => 'content-type', 'name' => 'Header Content-Type', 'placeholder' => 'application/json'];
        $template[] = ['id' => 'customHeaders', 'name' => 'Custom headers', 'placeholder' => 'key: value,key: value'];
        $template[] = ['id' => 'auth', 'name' => $this->l10n->t('Authentication'), 'placeholder' => 'User:Password'];
        $template[] = ['id' => 'body', 'name' => 'Request body', 'placeholder' => ''];
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
        $url = htmlspecialchars_decode($option['url'], ENT_NOQUOTES);
        $path = $option['path'];
        $auth = $option['auth'];
        $post = $option['method'] === 'POST';
        $contentType = ($option['content-type'] && $option['content-type'] !== '') ? $option['content-type'] : 'application/json';
        $data = array();
        $http_code = '';
        $headers = ($option['customHeaders'] && $option['customHeaders'] !== '') ? explode(",", $option['customHeaders']) : [];
        $headers = array_map('trim', $headers);
        $headers[] = 'OCS-APIRequest: true';
        $headers[] = 'Content-Type: ' . $contentType;

        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            if ($option['body'] && $option['body'] !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $option['body']);
            }
            $rawResult = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $rawResult = '';
        }

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
            'customHeaders' => $headers,
            'URL' => $url,
            'error' => ($http_code >= 200 && $http_code < 300) ? 0 : 'HTTP response code: ' . $http_code,
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