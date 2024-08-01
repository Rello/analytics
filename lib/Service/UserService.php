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

class UserService {
	private $logger;
	private $ShareService;
	private $ReportService;
	private $DatasetService;
	private $PanoramaService;

	public function __construct(
		LoggerInterface $logger,
		ReportService   $ReportService,
		DatasetService  $DatasetService,
		ShareService    $ShareService,
		PanoramaService $PanoramaService
	) {
		$this->logger = $logger;
		$this->ReportService = $ReportService;
		$this->DatasetService = $DatasetService;
		$this->ShareService = $ShareService;
		$this->PanoramaService = $PanoramaService;
	}

	/**
	 * delete all user data
	 *
	 * @param $userId
	 * @return bool
	 * @throws Exception
	 */
	public function deleteUserData($userId) {
		$this->logger->info('Deleting all Analytics data for: ' . $userId);
		$this->ReportService->deleteByUser($userId);
		$this->DatasetService->deleteByUser($userId);
		$this->ShareService->deleteByUser($userId);
		$this->PanoramaService->deleteByUser($userId);
		return true;
	}
}