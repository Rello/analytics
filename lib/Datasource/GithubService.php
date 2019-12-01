<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Datasource;

use OCP\ILogger;

class GithubService
{
    private $logger;
    private $DBController;

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
     * @param $datasetMetadata
     * @return array
     */
    public function read($datasetMetadata)
    {
        $string = 'https://api.github.com/repos/' . $datasetMetadata['link'] . '/releases';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $string);
        curl_setopt($ch, CURLOPT_REFERER, $string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        $result = curl_exec($ch);
        curl_close($ch);
        #echo $result;

        $jason_a = json_decode($result, true);
        $i = 0;

        $data = array();
        foreach ($jason_a as $item) {
            if ($i === 15) break;
            $nc_value = 0;
            foreach ($item['assets'] as $asset) {
                if (substr($asset['name'], -2) == 'gz') $nc_value = $asset['download_count'];
            }
            array_push($data, ['dimension2' => $item['tag_name'], 'dimension3' => $nc_value]);
            $i++;
        }

        $header = array();
        $header['dimension1'] = '';
        $header['dimension2'] = 'Version';
        $header['dimension3'] = 'Count';

        $result = [
            'header' => $header,
            'data' => $data
        ];

        return $result;
    }
}