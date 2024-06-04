<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Service;

use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class UserService
{
    private $logger;
    private $ShareService;
    private $ReportService;
    private $DatasetService;

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
     * @throws Exception
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