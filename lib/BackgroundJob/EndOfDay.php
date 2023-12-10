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

namespace OCA\Analytics\BackgroundJob;

use OCA\Analytics\Service\DataloadService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class EndOfDay extends TimedJob
{

    private $logger;
    private $DataloadService;

    public function __construct(ITimeFactory $time,
                                LoggerInterface $logger,
                                DataloadService $DataloadService
    )
    {
        parent::__construct($time);
        $this->setInterval(60 * 10); // Check every 10 minutes to ensure it hits the 15 min window once
        $this->logger = $logger;
        $this->DataloadService = $DataloadService;
    }

    public function run($arguments)
    {
        $currentTime = new \DateTime(); // Current time
        $endOfDay = new \DateTime('today 23:59:59'); // End of the day
        $startOfLast15Minutes = (clone $endOfDay)->modify('-15 minutes'); // Start of last 15 minutes of the day

        if ($currentTime >= $startOfLast15Minutes && $currentTime <= $endOfDay) {
            try {
                $this->logger->debug('Analytics Dataload - End of day');
                $this->DataloadService->executeBySchedule('e');
            } catch (\Exception $e) {
                // Handle exception or log error
            }
        }
    }

}