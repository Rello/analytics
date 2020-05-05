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

namespace OCA\Analytics\BackgroundJob;

use OCA\Analytics\Controller\DataloadController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\ILogger;

class Hourly extends TimedJob
{

    private $logger;
    private $DataloadController;

    public function __construct(
        ITimeFactory $time,
        ILogger $logger,
        DataloadController $DataloadController
    )
    {
        parent::__construct($time);
        $this->setInterval((60 * 60) - 120); // 2 minutes because exact times would drift to the next cron execution
        $this->logger = $logger;
        $this->DataloadController = $DataloadController;
    }

    public function run($arguments)
    {
        //$this->logger->debug('Cron 38: Job started');
        try {
            $this->DataloadController->executeBySchedule('h');
        } catch (\Exception $e) {
        }
    }

}