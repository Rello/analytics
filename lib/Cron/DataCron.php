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

namespace OCA\Analytics\Cron;

use OCA\Analytics\Service\DataService;
use OCP\BackgroundJob\Job;
use OCP\ILogger;
use OCP\IRequest;

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