<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\BackgroundJob;

use OCA\Analytics\Service\DatasetService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * Re-submits all datasets with indexing enabled after context chat requested an initial import
 */
class ContextChatInitialImport extends QueuedJob
{

    private $logger;
    private $DatasetService;

    public function __construct(ITimeFactory $time,
                                LoggerInterface $logger,
                                DatasetService $DatasetService
    )
    {
        parent::__construct($time);
        $this->logger = $logger;
        $this->DatasetService = $DatasetService;
    }

    public function run($arguments)
    {
        $count = $this->DatasetService->providerAll();
        $this->logger->info('Analytics context chat initial import submitted ' . $count . ' datasets');
    }

}
