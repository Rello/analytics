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

namespace OCA\Analytics\Db;

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
     */
    public function getReportByToken($token)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_REPORT, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.report'))
            ->select('DS.*')
            ->addSelect('SH.permissions')
            ->selectAlias('SH.domain', 'domain')
            ->selectAlias('SH.password', 'password')
            ->where($sql->expr()->eq('SH.token', $sql->createNamedParameter($token)));
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get shared reports for current user
     * @return array
     */
    public function getSharedReports()
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_REPORT, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.report'))
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
     * get single shared report for current user
     * @param $id
     * @return array
     */
    public function getSharedReport($id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME_REPORT, 'DS')
            ->leftJoin('DS', self::TABLE_NAME, 'SH', $sql->expr()->eq('DS.id', 'SH.report'))
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
     * @param $reportId
     * @param $type
     * @param $uid_owner
     * @param $token
     * @param $parent
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function createShare($reportId, $type, $uid_owner, $token, $parent = null)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->Select('id')
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)))
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
                    'report' => $sql->createNamedParameter($reportId),
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
     * Get all shares of a report
     * @param $reportId
     * @return array
     */
    public function getShares($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id', 'type', 'uid_owner', 'token', 'permissions', 'domain')
            ->selectAlias('password', 'pass')
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('report', $sql->createNamedParameter($reportId)))
            ->andWhere($sql->expr()->neq('type', $sql->createNamedParameter(2)))
            ->orderBy('id', 'ASC');
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * Get the all receivers of shares of a report
     * Used to derive who has to receive activities when a report changes
     * @param $reportId
     * @return array
     */
    public function getSharedReceiver($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('uid_owner')
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)))
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
     * Update the password of a share
     * @param $shareId
     * @param $domain
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function updateShareDomain($shareId, $domain)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('domain', $sql->createNamedParameter($domain))
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
        // update the share itself
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('permissions', $sql->createNamedParameter($permissions))
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $sql->execute();

        // update the users within a group share
        $sqlg = $this->db->getQueryBuilder();
        $sqlg->update(self::TABLE_NAME)
            ->set('permissions', $sqlg->createNamedParameter($permissions))
            ->where($sqlg->expr()->eq('uid_initiator', $sqlg->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sqlg->expr()->eq('parent', $sqlg->createNamedParameter($shareId)));
        $sqlg->execute();

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
     * Delete all shares of a report
     * Used during report deletion
     * @param $reportId
     * @return bool
     */
    public function deleteShareByReport($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)));
        $sql->execute();
        return true;
    }
}