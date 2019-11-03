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
        //ToDo
    }
}