<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Db;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ShareMapper
{
    /** @var IUserSession */
    private $userSession;
    /** @var IDBConnection */
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_share';
	const TABLE_NAME_REPORT = 'analytics_report';
	const TABLE_NAME_PANORAMA = 'analytics_panorama';

    public function __construct(
        IDBConnection $db,
        IUserSession $userSession,
        LoggerInterface $logger
    )
    {
        $this->userSession = $userSession;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * get all shared reports by token
     * uses for public pages
     * @param $token
     * @return array
     * @throws Exception
     */
    public function getReportByToken($token)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_REPORT, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SHARE', $sql->expr()->eq('DS.id', 'SHARE.item_source'))
            ->select('DS.*')
            ->addSelect('SHARE.permissions')
            ->selectAlias('SHARE.domain', 'domain')
            ->selectAlias('SHARE.password', 'password')
            ->where($sql->expr()->eq('SHARE.token', $sql->createNamedParameter($token)))
			->andWhere($sql->expr()->eq('SHARE.item_type', $sql->createNamedParameter('report')));
        $statement = $sql->executeQuery();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get all shared reports
     * @return array
     * @throws Exception
     */
    public function getAllSharedReports()
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_REPORT, 'REPORT')
            ->rightJoin('REPORT', self::TABLE_NAME, 'SHARE', $sql->expr()->eq('REPORT.id', 'SHARE.item_source'))
            ->select('REPORT.*')
            ->selectAlias('SHARE.id', 'shareId')
            ->selectAlias('SHARE.type', 'shareType')
            ->selectAlias('SHARE.uid_owner', 'shareUid_owner')
            ->addSelect('SHARE.permissions')
			->where($sql->expr()->eq('SHARE.item_type', $sql->createNamedParameter('report')));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

	/**
	 * get all shared panoramas
	 * @return array
	 * @throws Exception
	 */
	public function getAllSharedPanoramas()
	{
		$sql = $this->db->getQueryBuilder();
		$sql->from(self::TABLE_NAME_PANORAMA, 'PANORAMA')
			->rightJoin('PANORAMA', self::TABLE_NAME, 'SHARE', $sql->expr()->eq('PANORAMA.id', 'SHARE.item_source'))
			->select('PANORAMA.*')
			->selectAlias('SHARE.id', 'shareId')
			->selectAlias('SHARE.type', 'shareType')
			->selectAlias('SHARE.uid_owner', 'shareUid_owner')
			->addSelect('SHARE.permissions')
			->where($sql->expr()->eq('SHARE.item_type', $sql->createNamedParameter('panorama')));
		$statement = $sql->executeQuery();
		$result = $statement->fetchAll();
		$statement->closeCursor();
		return $result;
	}

	/**
	 * Create a new share
	 * @param $item_type
	 * @param $item_source
	 * @param $type
	 * @param $uid_owner
	 * @param $token
	 * @param null $parent
	 * @return bool
	 * @throws Exception
	 */
    public function createShare($item_type, $item_source, $type, $uid_owner, $token, $parent = null)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->Select('id')
			->where($sql->expr()->eq('item_type', $sql->createNamedParameter($item_type)))
            ->andWhere($sql->expr()->eq('item_source', $sql->createNamedParameter($item_source)))
            ->andWhere($sql->expr()->eq('type', $sql->createNamedParameter($type)))
            ->andWhere($sql->expr()->eq('uid_owner', $sql->createNamedParameter($uid_owner)))
            ->andWhere($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();

        if ($result && ($type !== 3)) {
            // don´t create double shares
            // multiple link shares (3) are possible
            return false;
        } else {
			$currentDateTime = (new \DateTime())->format('Y-m-d H:i:s');
            $sql = $this->db->getQueryBuilder();
            $sql->insert(self::TABLE_NAME)
                ->values([
					'item_type' => $sql->createNamedParameter($item_type),
                    'item_source' => $sql->createNamedParameter($item_source),
					'type' => $sql->createNamedParameter($type),
                    'uid_owner' => $sql->createNamedParameter($uid_owner),
                    'uid_initiator' => $sql->createNamedParameter($this->userSession->getUser()->getUID()),
                    'token' => $sql->createNamedParameter($token),
                    'parent' => $sql->createNamedParameter($parent),
					'permissions' => $sql->createNamedParameter(1),
					'created_at' => $sql->createNamedParameter($currentDateTime),
                ]);
            $sql->executeStatement();
        }
        return $sql->getLastInsertId();
    }

	/**
	 * Get all shares of a report
	 * @param $item_type
	 * @param $item_source
	 * @return array
	 * @throws Exception
	 */
    public function getShares($item_type, $item_source)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id', 'type', 'uid_owner', 'token', 'permissions', 'domain')
            ->selectAlias('password', 'pass')
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('item_type', $sql->createNamedParameter($item_type)))
			->andWhere($sql->expr()->eq('item_source', $sql->createNamedParameter($item_source)))
			->andWhere($sql->expr()->neq('type', $sql->createNamedParameter(2)))
            ->orderBy('id', 'ASC');
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

	/**
     * Get the all receivers of shares of a report
     * Used to derive who has to receive activities when a report changes
     * @param $reportId
     * @return array
     * @throws Exception
     */
    public function getSharedReceiver($item_type, $item_source)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('uid_owner')
			->where($sql->expr()->eq('item_type', $sql->createNamedParameter($item_type)))
			->andWhere($sql->expr()->eq('item_source', $sql->createNamedParameter($item_source)))
            ->andWhere($sql->expr()->eq('type', $sql->createNamedParameter(0)));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * Update the password of a share
     * @param $shareId
     * @param $password
     * @return bool
     * @throws Exception
     */
    public function updateSharePassword($shareId, $password)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('password', $sql->createNamedParameter($password))
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $sql->executeStatement();
        return true;
    }

    /**
     * Update the domain of a share
	 * used for injecting charts into external sites
     * @param $shareId
     * @param $domain
     * @return bool
     * @throws Exception
     */
    public function updateShareDomain($shareId, $domain)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('domain', $sql->createNamedParameter($domain))
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $sql->executeStatement();
        return true;
    }

	/**
	 * Update the permissions of a share
	 * @param $shareId
	 * @param $permissions
	 * @return bool
	 * @throws Exception
	 */
    public function updateSharePermissions($shareId, $permissions)
    {
        // update the share itself
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('permissions', $sql->createNamedParameter($permissions))
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $sql->executeStatement();

        return true;
    }

    /**
     * Delete an own share (sharee or receiver)
     * @param $shareId
     * @return bool
     * @throws Exception
     */
    public function deleteShare($shareId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($shareId)))
            ->andWhere($sql->expr()->orX(
                $sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())),
                $sql->expr()->eq('uid_owner', $sql->createNamedParameter($this->userSession->getUser()->getUID()))
            ));
        $sql->executeStatement();
        return true;
    }

	/**
	 * Delete all shares of a report
	 * Used during report deletion
	 * @param $item_type
	 * @param $item_source
	 * @return bool
	 * @throws Exception
	 */
    public function deleteSharesByItem($item_type, $item_source)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
			->where($sql->expr()->eq('item_type', $sql->createNamedParameter($item_type)))
			->andWhere($sql->expr()->eq('item_source', $sql->createNamedParameter($item_source)));
        $sql->executeStatement();
        return true;
    }

    /**
     * delete all shares when a share-receiving-user is deleted
     *
     * @param $userId
     * @return bool
     * @throws Exception
     */
    public function deleteByUser($userId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('uid_owner', $sql->createNamedParameter($userId)))
            ->andWhere($sql->expr()->eq('type', $sql->createNamedParameter(0)));
        $sql->executeStatement();
        return true;
    }
}