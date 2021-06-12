<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */

namespace OCA\Analytics\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ShareMapper
{
    /** @var IUserSession */
    private $userSession;
    private $l10n;
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_share';
    const TABLE_NAME_DATASET = 'analytics_dataset';

    public function __construct(
        IL10N $l10n,
        IDBConnection $db,
        IUserSession $userSession,
        LoggerInterface $logger
    )
    {
        $this->userSession = $userSession;
        $this->l10n = $l10n;
        $this->db = $db;
        $this->logger = $logger;
        self::TABLE_NAME;
        self::TABLE_NAME_DATASET;
    }

    /**
     * get all shared datasets by token
     * uses for public pages
     * @param $token
     * @return array
     */
    public function getDatasetByToken($token)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_DATASET, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.dataset'))
            ->select('DS.*')
            ->addSelect('SH.permissions')
            ->selectAlias('SH.password', 'password')
            ->where($sql->expr()->eq('SH.token', $sql->createNamedParameter($token)));
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get all shared datasets by group
     * @param $group
     * @return array
     */
    public function getDatasetsByGroup($group)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_DATASET, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.dataset'))
            ->select('DS.id', 'DS.name')
            ->where($sql->expr()->eq('SH.uid_owner', $sql->createNamedParameter($group)))
            ->andWhere($sql->expr()->eq('SH.type', $sql->createNamedParameter(1)));
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get all shared datasets by group
     * @param $group
     * @param $id
     * @return array
     */
    public function getDatasetByGroupId($group, $id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_DATASET, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.dataset'))
            ->select('DS.*')
            ->addSelect('SH.permissions')
            ->where($sql->expr()->eq('SH.uid_owner', $sql->createNamedParameter($group)))
            ->andWhere($sql->expr()->eq('SH.type', $sql->createNamedParameter(1)))
            ->andWhere($sql->expr()->eq('SH.dataset', $sql->createNamedParameter($id)));
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get shared datasets for current user
     * @return array
     */
    public function getSharedDatasets()
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_DATASET, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.dataset'))
            ->select('DS.id', 'DS.name')
            ->selectAlias('SH.id', 'shareId')
            ->where($sql->expr()->eq('SH.uid_owner', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->neq('SH.type', $sql->createNamedParameter(1))); // don´t find groups with the same name
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get single shared dataset for current user
     * @param $id
     * @return array
     */
    public function getSharedDataset($id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_DATASET, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.dataset'))
            ->select('DS.*')
            ->addSelect('SH.permissions')
            ->selectAlias($sql->createNamedParameter(true, IQueryBuilder::PARAM_BOOL), 'isShare')
            ->where($sql->expr()->eq('SH.uid_owner', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('DS.id', $sql->createNamedParameter($id)));
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * Create a new share
     * @param $datasetId
     * @param $type
     * @param $uid_owner
     * @param $token
     * @param $parent
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function createShare($datasetId, $type, $uid_owner, $token, $parent = null)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->Select('id')
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->eq('type', $sql->createNamedParameter($type)))
            ->andWhere($sql->expr()->eq('uid_owner', $sql->createNamedParameter($uid_owner)))
            ->andWhere($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())));
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();

        if ($result && ($type !== 3)) {
            // don´t create double shares
            // multiple link shares (3) are possible
            return false;
        } else {
            $sql = $this->db->getQueryBuilder();
            $sql->insert(self::TABLE_NAME)
                ->values([
                    'dataset' => $sql->createNamedParameter($datasetId),
                    'type' => $sql->createNamedParameter($type),
                    'uid_owner' => $sql->createNamedParameter($uid_owner),
                    'uid_initiator' => $sql->createNamedParameter($this->userSession->getUser()->getUID()),
                    'token' => $sql->createNamedParameter($token),
                    'parent' => $sql->createNamedParameter($parent),
                ]);
            $sql->execute();
        }
        return $sql->getLastInsertId();
    }

    /**
     * Get single shares metadata
     * @param $shareId
     * @return array
     */
    public function getShare($shareId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id', 'type', 'parent')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * Get all shares of a dataset
     * @param $datasetId
     * @return array
     */
    public function getShares($datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id', 'type', 'uid_owner', 'token', 'permissions')
            ->selectAlias('password', 'pass')
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->neq('type', $sql->createNamedParameter(2)));
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * Get the all receivers of shares of a dataset
     * Used to derive who has to receive activites when a dataset changes
     * @param $datasetId
     * @return array
     */
    public function getSharedReceiver($datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('uid_owner')
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->eq('type', $sql->createNamedParameter(0)));
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * Update the password of a share
     * @param $shareId
     * @param $password
     * @return bool
     */
    public function updateSharePassword($shareId, $password)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('password', $sql->createNamedParameter($password))
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $sql->execute();
        return true;
    }

    /**
     * Update the permissions of a share
     * @param $shareId
     * @param $password
     * @return bool
     */
    public function updateSharePermissions($shareId, $permissions)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('permissions', $sql->createNamedParameter($permissions))
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $sql->execute();
        return true;
    }

    /**
     * Delete a share
     * @param $shareId
     * @return bool
     * @throws \OCP\DB\Exception
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
        $sql->execute();
        return true;
    }

    /**
     * Delete all shares by parent ID (users of a group share)
     * @param $parent
     * @return bool
     */
    public function deleteShareByParent($parent)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('parent', $sql->createNamedParameter($parent)));
        $sql->execute();
        return true;
    }

    /**
     * Delete all shares of a dataset
     * Used during dataset deletion
     * @param $datasetId
     * @return bool
     */
    public function deleteShareByDataset($datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)));
        $sql->execute();
        return true;
    }
}