<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Service;

use OCA\data\Controller\DbController;
use OCP\ILogger;

class GithubService
{
    private $logger;
    private $DBController;

    public function __construct(
        ILogger $logger,
        DbController $DBController
    )
    {
        $this->logger = $logger;
        $this->DBController = $DBController;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @return JSONResponse
     */
    public function read()
    {
        $string = 'https://api.github.com/repos/rello/audioplayer/releases';

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

        $tagName = $jason_a[0]['tag_name'];

        $tagVariable = 'tag_name';
        $tagName = $jason_a[0][$tagVariable];

        $count = $jason_a[0]['assets'][0]['download_count'];
        $count = $jason_a[0] . assets . download_cont;
        $this->logger->error($tagName . $count);

        $data = array();
        foreach ($jason_a as $item) {
            if ($i === 15) break;
            $nc_value = 0;
            foreach ($item['assets'] as $asset) {
                if (substr($asset['name'], -2) == 'gz') $nc_value = $asset['download_count'];
            }
            array_push($data, ['date' => $item['tag_name'], 'value' => $nc_value]);
            $i++;
        }
        $header = array();
        array_push($header, 'Version', 'Count');

        $result = empty($data) ? [
            'status' => 'nodata'
        ] : [
            'header' => $header,
            'data' => $data
        ];

        return $result;
    }
}