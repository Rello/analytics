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

class GithubService
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
        if (isset($option['link'])) $string = 'https://api.github.com/repos/' . $option['link'] . '/releases';
        else $string = 'https://api.github.com/repos/' . $option['user'] . '/' . $option['repository'] . '/releases';

        $ch = curl_init();
        if ($ch != false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_URL, $string);
            curl_setopt($ch, CURLOPT_REFERER, $string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
            $curlResult = curl_exec($ch);
            curl_close($ch);
        } else {
            $curlResult = '';
        }

        $json = json_decode($curlResult, true);
        $i = 0;

        $data = array();
        foreach ($json as $item) {
            if (isset($option['limit'])) {
                if ($i === (int)$option['limit']) break;
            }
            $nc_value = 0;
            foreach ($item['assets'] as $asset) {
                if (substr($asset['name'], -2) == 'gz') $nc_value = $asset['download_count'];
            }
            array_push($data, [$item['tag_name'], $this->floatvalue($nc_value)]);
            $i++;
        }

        $header = array();
        $header[0] = 'Version';
        $header[1] = 'Count';

        return [
            'header' => $header,
            'data' => $data,
            'error' => 0,
        ];
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
        array_push($template, ['id' => 'user', 'name' => 'GitHub Username', 'placeholder' => 'GitHub user']);
        array_push($template, ['id' => 'repository', 'name' => 'Repository', 'placeholder' => 'GitHub repository']);
        array_push($template, ['id' => 'limit', 'name' => 'Limit', 'placeholder' => 'Number of records']);
        array_push($template, ['id' => 'timestamp', 'name' => 'Timestamp of dataload', 'placeholder' => 'true/false']);
        return $template;
    }

    private function floatvalue($val)
    {
        $val = str_replace(",", ".", $val);
        $val = preg_replace('/\.(?=.*\.)/', '', $val);
        $val = preg_replace('/[^0-9-.]+/', '', $val);
        if (is_numeric($val)) {
            return floatval($val);
        } else {
            return false;
        }
    }
}