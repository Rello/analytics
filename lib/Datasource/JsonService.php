<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Datasource;

use OCP\ILogger;

class JsonService
{
    private $logger;

    public function __construct(
        ILogger $logger
    )
    {
        $this->logger = $logger;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param array $option
     * @return array
     */
    public function read($option)
    {
        $string = $option['url'];
        $path = $option['path'];
        $auth = $option['auth'];
        $data = array();

        $ch = curl_init();
        if ($ch != false) {
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

        //$this->logger->debug($curlResult);
        $json = json_decode($curlResult, true);
        $array = $this->get_nested_array_value($json, $path);

        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $group = end(explode('/', $path));
                array_push($data, [$group, $key, $value]);
            }
        } else {
            $key = end(explode('/', $path));
            array_push($data, ['', $key, $array]);
        }

        $header = array();
        $header[0] = '';
        $header[1] = 'Key';
        $header[2] = 'Value';

        return [
            'header' => $header,
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

    /**
     * template for options & settings
     *
     * @NoAdminRequired
     * @return array
     */
    public function getTemplate()
    {
        $template = array();
        array_push($template, ['id' => 'url', 'name' => 'JSON Url', 'placeholder' => 'url']);
        array_push($template, ['id' => 'auth', 'name' => 'Authentication', 'placeholder' => 'User:Password']);
        array_push($template, ['id' => 'path', 'name' => 'JSON path', 'placeholder' => 'x/y/z']);
        array_push($template, ['id' => 'timestamp', 'name' => 'Timestamp of dataload', 'placeholder' => 'true/false']);
        return $template;
    }
}