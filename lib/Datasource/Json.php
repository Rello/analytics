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
use phpDocumentor\Reflection\Types\This;
use Psr\Log\LoggerInterface;

class Json implements IDatasource
{
    private $logger;
    private $l10n;

    public function __construct(
        IL10N $l10n,
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
        array_push($template, ['id' => 'url', 'name' => 'URL', 'placeholder' => 'url']);
        array_push($template, ['id' => 'auth', 'name' => $this->l10n->t('Authentication'), 'placeholder' => 'User:Password']);
        array_push($template, ['id' => 'path', 'name' => $this->l10n->t('Object path'), 'placeholder' => 'x/y/z']);
        array_push($template, ['id' => 'method', 'name' => $this->l10n->t('HTTP method'), 'placeholder' => 'GET/POST', 'type' => 'tf']);
        array_push($template, ['id' => 'body', 'name' => 'Request body', 'placeholder' => '']);
        array_push($template, ['id' => 'content-type', 'name' => 'Header Content-Type', 'placeholder' => 'application/json']);
        array_push($template, ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of data load'), 'placeholder' => $this->l10n->t('true/false'), 'type' => 'tf']);
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
        $post = ($option['method'] === 'POST') ? true : false;
        $contentType = ($option['content-type'] && $option['content-type'] !== '') ? $option['content-type'] : 'application/json';
        $data = array();

        $ch = curl_init();
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'OCS-APIRequest: true',
                'Content-Type: ' . $contentType
            ));
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
                $singlePath = substr($path,0, $firstArray);
                $json = $this->get_nested_array_value($json, $singlePath);
            }

            $paths = explode(',', $matches[0][0]);
            foreach ($json as $rowArray) {
                // get the paths from the array
                // if no match is not found, the path will be used as a constant string
                $dim1 = $this->get_nested_array_value($rowArray, $paths[0]) ?: $paths[0];
                $dim2 =  $this->get_nested_array_value($rowArray, $paths[1]) ?: $paths[1];
                $val =  $this->get_nested_array_value($rowArray, $paths[2]) ?: $paths[2];
                array_push($data, [$dim1, $dim2, $val]);
            }
        } else {
            // single value extraction
            // e.g. data/currentHashrate,data/averageHashrate
            $paths = explode(',', $path);
            foreach ($paths as $singlePath) {
                // e.g. data/currentHashrate
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
            'URL' => $url,
            'error' => ($http_code>=200 && $http_code<300) ? 0 : 'HTTP response code: '.$http_code,
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