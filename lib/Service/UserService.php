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

namespace OCA\Analytics\Service;

use Psr\Log\LoggerInterface;

class UserService
{
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        ReportService $reportService,
        DatasetService $datasetService,
        ShareService $shareService
    )
    {
        $this->logger = $logger;
        $this->ReportService = $reportService;
        $this->DatasetService = $datasetService;
        $this->ShareService = $shareService;
    }

    /**
     * delete all user data
     *
     * @param $userId
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function deleteUserData($userId)
    {
        $this->logger->info('Deleting all Analytics data for: ' . $userId);
        $this->ReportService->deleteByUser($userId);
        $this->DatasetService->deleteByUser($userId);
        $this->ShareService->deleteByUser($userId);
        return true;
    }
}