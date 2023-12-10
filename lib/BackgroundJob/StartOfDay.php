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

class StartOfDay extends TimedJob
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
        $startOfDay = new \DateTime('today 00:00:00'); // Start of the day
        $endOfFirst15Minutes = (clone $startOfDay)->modify('+15 minutes'); // End of first 15 minutes of the day

        if ($currentTime >= $startOfDay && $currentTime <= $endOfFirst15Minutes) {
            try {
                $this->logger->debug('Analytics Dataload - Start of day');
                $this->DataloadService->executeBySchedule('s');
            } catch (\Exception $e) {
                // Handle exception or log error
            }
        }
    }

}