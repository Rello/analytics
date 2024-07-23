<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Db;

use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class DatasetMapper {
	private $l10n;
	private $userId;
	private $db;
	private $logger;
	const TABLE_NAME = 'analytics_dataset';

	public function __construct(
		$userId,
		IL10N $l10n,
		IDBConnection $db,
		LoggerInterface $logger
	) {
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->db = $db;
		$this->logger = $logger;
	}

	/**
	 * get datasets
	 * @return array
	 * @throws Exception
	 */
	public function index(): array {
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME)->select('id')->addSelect('name')->addSelect('dimension1')->addSelect('dimension2')
			->addSelect('value')->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
			->andWhere($sql->expr()->eq('type', $sql->createNamedParameter('2')))->addOrderBy('name', 'ASC');
		$statement = $sql->executeQuery();
		$result = $statement->fetchAll();
		$statement->closeCursor();
		return $result;
	}

	/**
	 * get datasets
	 * @param $userId
	 * @return array
	 * @throws Exception
	 */
	public function indexByUser($userId): array {
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME)->select('id')->where($sql->expr()
															  ->eq('user_id', $sql->createNamedParameter($userId)));
		$statement = $sql->executeQuery();
		$result = $statement->fetchAll();
		$statement->closeCursor();
		return $result;
	}

	/**
	 * create dataset
	 * @param $name
	 * @param $dimension1
	 * @param $dimension2
	 * @param $value
	 * @return int
	 * @throws Exception
	 */
	public function create($name, $dimension1, $dimension2, $value): int {
		$sql = $this->db->getQueryBuilder();
		$sql->insert(self::TABLE_NAME)->values([
												   'user_id' => $sql->createNamedParameter($this->userId),
												   'name' => $sql->createNamedParameter($name),
												   'dimension1' => $sql->createNamedParameter($dimension1),
												   'dimension2' => $sql->createNamedParameter($dimension2),
												   'value' => $sql->createNamedParameter($value),
												   'type' => $sql->createNamedParameter('2'),
												   'ai_index' => $sql->createNamedParameter('0'),
											   ]);
		$sql->executeStatement();
		return $sql->getLastInsertId();
	}

	/**
	 * get single dataset
	 * @param int $id
	 * @return array|bool
	 * @throws Exception
	 */
	public function readOwn(int $id) {
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME)->select('*')->where($sql->expr()->eq('id', $sql->createNamedParameter($id)))
			->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))->orderBy('parent', 'ASC')
			->addOrderBy('name', 'ASC');
		$statement = $sql->executeQuery();
		$result = $statement->fetch();
		$statement->closeCursor();
		return $result;
	}

	/**
	 * get single dataset
	 * @param int $id
	 * @return array|bool
	 * @throws Exception
	 */
	public function read(int $id) {
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME)->select('*')->where($sql->expr()->eq('id', $sql->createNamedParameter($id)))
			->orderBy('parent', 'ASC')->addOrderBy('name', 'ASC');
		$statement = $sql->executeQuery();
		$result = $statement->fetch();
		$statement->closeCursor();
		return $result;
	}

	/**
	 * update dataset
	 * @param $id
	 * @param $nameﬂ
	 * @param $dimension1
	 * @param $dimension2
	 * @param $value
	 * @param $subheader
	 * @param $aiIndex
	 * @return bool
	 * @throws Exception
	 */
	public function update($id, $name, $subheader, $dimension1, $dimension2, $value, $aiIndex): bool {
		$name = $this->truncate($name, 64);
		$sql = $this->db->getQueryBuilder();
		$sql->update(self::TABLE_NAME)->set('name', $sql->createNamedParameter($name))
			->set('subheader', $sql->createNamedParameter($subheader))
			->set('dimension1', $sql->createNamedParameter($dimension1))
			->set('dimension2', $sql->createNamedParameter($dimension2))
			->set('value', $sql->createNamedParameter($value))
			->set('ai_index', $sql->createNamedParameter($aiIndex))
			->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))->andWhere($sql->expr()
																										  ->eq('id', $sql->createNamedParameter($id)));
		$sql->executeStatement();
		return true;
	}

	/**
	 * delete dataset
	 * @param $id
	 * @return bool
	 * @throws Exception
	 */
	public function delete($id): bool {
		$sql = $this->db->getQueryBuilder();
		$sql->delete(self::TABLE_NAME)->where($sql->expr()->eq('id', $sql->createNamedParameter($id)));
		$sql->executeStatement();
		return true;
	}

	/**
	 * get the newest timestamp of the data of a dataset
	 * @param $datasetId
	 * @return int
	 * @throws Exception
	 */
	public function getLastUpdate($datasetId): int {
		$sql = $this->db->getQueryBuilder();
		$sql->from('analytics_facts')->select($sql->func()->max('timestamp'))->where($sql->expr()
																						 ->eq('dataset', $sql->createNamedParameter($datasetId)));
		return (int)$sql->executeQuery()->fetchOne();
	}

	/**
	 * get the report owner
	 * @param $datasetId
	 * @return string
	 * @throws Exception
	 */
	public function getOwner($datasetId): string {
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME)->select('user_id')->where($sql->expr()
																   ->eq('id', $sql->createNamedParameter($datasetId)));
		return (string)$sql->executeQuery()->fetchOne();
	}

	/**
	 * truncates fields do DB-field size
	 *
	 * @param $string
	 * @param int $length
	 * @param string $dots
	 * @return string
	 */
	private function truncate($string, int $length, string $dots = "..."): string {
		return (strlen($string) > $length) ? mb_strcut($string, 0, $length - strlen($dots)) . $dots : $string;
	}
}