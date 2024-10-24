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

class ShareReviewSource {
	/** @var LoggerInterface */
	private $logger;
	/** @var IDBConnection */
	private $db;

	const TABLE_NAME = 'analytics_share';
	const TABLE_NAME_REPORT = 'analytics_report';
	const TABLE_NAME_PANORAMA = 'analytics_panorama';

	public function __construct(
		IDBConnection   $db,
		LoggerInterface $logger
	) {
		$this->db = $db;
		$this->logger = $logger;
	}

	/**
	 * Name of the app
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'Analytics';
	}

	/**
	 * Return all shares in the defined format
	 *
	 * [
	 *  'id' => (int) The unique identifier for the share,,
	 *  'app' => (string) The name of the application,
	 *  'object' => (string) The name/title of the object like file path or report name,
	 *  'initiator' => (string) The userId of the initiator,
	 *  'type' => (int) The OCP\Share\IShare type of the share,
	 *  'recipient' => (string) The userId of the owner or the token of a link,
	 *  'permissions' => (int) The permissions level, default is 1 if not set,
	 *  'time' => (string) The creation time, defaults to '1970-01-01 01:00:00' if null,
	 *  'action' => (string) Any additional actions, currently empty,
	 * ];
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getShares(): array {
		$shares = $this->getAllShares();
		$formatted = [];
		foreach ($shares as $share) {
			switch ($share['item_type']) {
				case ShareService::SHARE_ITEM_TYPE_REPORT:
					$object = $this->getSourceName(self::TABLE_NAME_REPORT, $share['item_source']) . ' (Report)';
					break;
				case ShareService::SHARE_ITEM_TYPE_DATASET:
					$object = 'Dataset';
					break;
				case ShareService::SHARE_ITEM_TYPE_PANORAMA:
					$object = $this->getSourceName(self::TABLE_NAME_PANORAMA, $share['item_source']) . ' (Panorama)';
					break;
			}

			$data = [
				'id' => $share['id'],
				'app' => $this->getName(),
				'object' => $object,
				'initiator' => $share['uid_initiator'],
				'type' => $share['type'],
				'recipient' => $share['uid_owner'] != '' ? $share['uid_owner'] : $share['token'],
				'permissions' => $share['permissions'] != '' ? $share['permissions'] : 1,
				'time' => $share['created_at'] != null ? $share['created_at'] : '1970-01-01 01:00:00',
				'action' => '',
			];

			$formatted[] = $data;
		}
		return $formatted;
	}

	/**
	 * Delete a share by its id
	 *
	 * @param $shareId
	 * @return bool
	 * @throws Exception
	 */
	public function deleteShare($shareId): bool {
		$this->logger->info('Deleting Analytics share: ' . $shareId);
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::TABLE_NAME)->where($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
		$sql->executeStatement();
		return true;
	}

	/**
	 * Get all Analytics shares
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getAllShares(): array {
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME)
			->select('id', 'type', 'uid_owner', 'uid_initiator', 'permissions', 'item_type', 'item_source', 'token', 'created_at')
			->andWhere($sql->expr()->neq('type', $sql->createNamedParameter(2)))->orderBy('id', 'ASC');
		$statement = $sql->executeQuery();
		$result = $statement->fetchAll();
		$statement->closeCursor();
		return $result;
	}

	/**
	 * Get the name of the report or panorama
	 *
	 * @param $table
	 * @param $item_source
	 * @return string
	 * @throws Exception
	 */
	private function getSourceName($table, $item_source): string {
		$sql = $this->db->getQueryBuilder();
		$sql->from($table)->select('name')->Where($sql->expr()->eq('id', $sql->createNamedParameter($item_source)));
		$statement = $sql->executeQuery();
		$result = $statement->fetch();
		$statement->closeCursor();
		return $result['name'];
	}
}