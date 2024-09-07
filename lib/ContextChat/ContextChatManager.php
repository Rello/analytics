<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\ContextChat;

use OCP\DB\Exception;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCA\ContextChat\Public\ContentItem;
use OCA\ContextChat\Public\ContentManager;
use OCA\Analytics\Service\StorageService;
use OCA\Analytics\Service\DatasetService;

class ContextChatManager {
	private $userId;
	private $logger;
	private $l10n;
	private $StorageService;
	private $DatasetService;
	private $ContentManager;

	public function __construct(
		$userId,
		IL10N $l10n,
		LoggerInterface $logger,
		StorageService $StorageService,
		DatasetService $DatasetService,
		ContentManager $ContentManager
	) {
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->StorageService = $StorageService;
		$this->DatasetService = $DatasetService;
		$this->ContentManager = $ContentManager;
	}

	/**
	 * Providers can use this to submit content for indexing in context chat
	 *
	 * @param int $datasetId
	 * @return bool
	 * @throws Exception
	 */
	public function submitContent(int $datasetId): bool {
		$datasetMetadata = $this->DatasetService->read($datasetId);
		if ($datasetMetadata['ai_index'] !== 1) return false;

		$columns = $datasetMetadata['dimension1'] .', '.$datasetMetadata['dimension2'].', '.$datasetMetadata['value'];

		$storageData = $this->StorageService->read($datasetId, null);
		$data = array_map(function ($subArray) {
			return implode(",", $subArray);
		}, $storageData['data']);
		$dataString = implode("\n", $data);

		$content = 'This is a set of statistical data. The name of the report is: ' . $datasetMetadata['name'] . '. ';
		$content .= 'The description of the data is: ' . $datasetMetadata['subheader'] . '. ';
		$content .= 'The data comes in multiple rows which 3 columns separated by a comma. ';
		$content .= 'The columns are ' . $columns . '. ';
		$content .= 'The data is: ' . $dataString;

		$contentItem = new ContentItem(
			$datasetId,
			'report',
			$datasetMetadata['name'],
			$content,
			'Report',
			new \DateTime(),
			[$datasetMetadata['user_id']]
		);

		$contentItems = [$contentItem];
		$this->logger->info('Adding Analytics to context chat: ' . json_encode($contentItem));
		$this->ContentManager->submitContent('analytics', $contentItems);
		return true;
	}

	/**
	 * Remove a dataset from context chat
	 *
	 * @param int $datasetId
	 * @return bool
	 * @throws Exception
	 */
	public function removeContentByDataset(int $datasetId): bool {
		$datasetMetadata = $this->DatasetService->read($datasetId);
		$this->logger->info('Removing Analytics dataset from context chat: ' . $datasetId);
		$this->ContentManager->removeContentForUsers('analytics', 'report', $datasetId, [$datasetMetadata['user_id']]);
		return true;
	}

	/**
	 * Remove a dataset from context chat
	 *
	 * @param string $user
	 * @return bool
	 */
	public function removeContentByUser(string $user): bool {
		$this->logger->info('Removing Analytics from context chat for user: .' . $user);
		$this->ContentManager->removeAllContentForUsers('analytics', 'report', [$user]);
		return true;
	}

	/**
	 * return true if the data set has indexing activated
	 *
	 * @param int $datasetId
	 * @return bool
	 * @throws Exception
	 */
	public function isActiveForDataset(int $datasetId) {
		$datasetMetadata = $this->DatasetService->read($datasetId);
		return $datasetMetadata['ai_index'] === 1;
	}
}