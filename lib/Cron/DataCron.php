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

namespace OCA\data\Cron;

use OC\BackgroundJob\Job;
use OCA\data\Service\DataService;
use OCP\ILogger;

class DataCron extends Job
{
    private $logger;
    private $DataService;

    public function __construct(
        string $AppName,
        IRequest $request,
        ILogger $logger,
        DataService $DataService
    )
    {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->DataService = $DataService;
    }

    /**
     * @param $argument
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function run()
    {

        $string = 'https://api.github.com/repos/rello/audioplayer_sonos/releases';

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
        $count = $jason_a[0]['assets']['download_count'];
        $this->logger->error($tagName . $count);
    }
}