<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\ShareReview;

use OCA\Analytics\Service\ShareService;
use OCP\DB\Exception;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ShareReviewSource
{
	/** @var LoggerInterface */
	private $logger;
	private $shareService;
	/** @var IDBConnection */
	private $db;
	const TABLE_NAME = 'analytics_share';

	public function __construct(
		IDBConnection $db,
		LoggerInterface $logger,
		ShareService    $shareService
	) {
		$this->db = $db;
		$this->logger = $logger;
		$this->shareService = $shareService;
	}

	public function getName(): string
	{
		return 'Analytics';
	}

	public function getShares()
	{
		$shares = $this->getAllShares();
		$formatted = [];
		foreach ($shares as $key => $share) {
			switch ($share['item_type']) {
				case ShareService::SHARE_ITEM_TYPE_REPORT:
					$path = 'Report';
					break;
				case ShareService::SHARE_ITEM_TYPE_DATASET:
					$path = 'Dataset';
					break;
				case ShareService::SHARE_ITEM_TYPE_PANORAMA:
					$path = 'Panorama';
					break;
			}

			$data = [
				'id' => $share['id'],
				'app' => 'Analytics',
				'object' => $path,
				'initiator' => $share['uid_initiator'],
				'type' => $share['type'],
				'recipient' => $share['uid_owner'] != '' ? $share['uid_owner'] : $share['token'],
				'permissions' => $share['permissions'],
				'time' => '',
				'action' => '',
			];

			$formatted[] = $data;
		}
		return $formatted;
	}

	/**
	 * @param $shareId
	 * @return string
	 * @throws Exception
	 */
	public function deleteShare($shareId): string
	{
		$this->logger->info('Deleting Analytics share: ' . $shareId);
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::TABLE_NAME)
			->where($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
		$sql->executeStatement();
		return true;
	}

	/**
	 * @throws Exception
	 */
	private function getAllShares() {
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME)
			->select('id', 'type', 'uid_owner', 'uid_initiator', 'permissions', 'item_type', 'token')
			->andWhere($sql->expr()->neq('type', $sql->createNamedParameter(2)))
			->orderBy('id', 'ASC');
		$statement = $sql->executeQuery();
		$result = $statement->fetchAll();
		$statement->closeCursor();
		return $result;
	}
}