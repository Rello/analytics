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
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
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
        LoggerInterface $logger,
        private IEventDispatcher $eventDispatcher
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
			->andWhere($sql->expr()->eq('SHARE.item_type', $sql->createNamedParameter('report')))
			->andWhere($sql->expr()->eq('SHARE.uid_initiator', 'DS.user_id'));
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
			->where($sql->expr()->eq('SHARE.item_type', $sql->createNamedParameter('report')))
			->andWhere($sql->expr()->eq('SHARE.uid_initiator', 'REPORT.user_id'));
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
			->where($sql->expr()->eq('SHARE.item_type', $sql->createNamedParameter('panorama')))
			->andWhere($sql->expr()->eq('SHARE.uid_initiator', 'PANORAMA.user_id'));
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
	 * @return bool
	 * @throws Exception
	 */
    public function createShare($item_type, $item_source, $type, $uid_owner, $token)
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
					'permissions' => $sql->createNamedParameter(1),
					'created_at' => $sql->createNamedParameter($currentDateTime),
                ]);
            $sql->executeStatement();
        }
        $shareId = $sql->getLastInsertId();
        $this->auditShare('created', [
            'id' => $shareId,
            'item_type' => $item_type,
            'item_source' => $item_source,
            'type' => $type,
            'uid_owner' => $uid_owner,
            'uid_initiator' => $this->userSession->getUser()->getUID(),
        ]);
        return $shareId;
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
        return $this->updateShareField($shareId, 'password', $password, 'password_changed');
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
        return $this->updateShareField($shareId, 'domain', $domain, 'domain_changed');
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
        return $this->updateShareField($shareId, 'permissions', $permissions, 'permissions_changed');
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
        $sql->select('id', 'item_type', 'item_source', 'type', 'uid_owner', 'uid_initiator')
            ->from(self::TABLE_NAME)
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($shareId)))
            ->andWhere($sql->expr()->orX(
                $sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())),
                $sql->expr()->eq('uid_owner', $sql->createNamedParameter($this->userSession->getUser()->getUID()))
            ));
        $this->deleteAuditedShares($sql);
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
        $sql->select('id', 'item_type', 'item_source', 'type', 'uid_owner', 'uid_initiator')
            ->from(self::TABLE_NAME)
			->where($sql->expr()->eq('item_type', $sql->createNamedParameter($item_type)))
			->andWhere($sql->expr()->eq('item_source', $sql->createNamedParameter($item_source)));
        $this->deleteAuditedShares($sql);
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
        $sql->select('id', 'item_type', 'item_source', 'type', 'uid_owner', 'uid_initiator')
            ->from(self::TABLE_NAME)
            ->where($sql->expr()->eq('uid_owner', $sql->createNamedParameter($userId)))
            ->andWhere($sql->expr()->eq('type', $sql->createNamedParameter(0)));
        $this->deleteAuditedShares($sql);
        return true;
    }

    /** Update only shares owned by the actor; audit only persisted changes. */
    private function updateShareField($shareId, string $field, $value, string $action): bool
    {
        $sql = $this->db->getQueryBuilder();
        $sql->select('id', 'item_type', 'item_source', 'type', 'uid_owner', 'uid_initiator', $field)
            ->from(self::TABLE_NAME)
            ->where($sql->expr()->eq('uid_initiator', $sql->createNamedParameter($this->userSession->getUser()->getUID())))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($shareId)));
        $statement = $sql->executeQuery();
        $share = $statement->fetch();
        $statement->closeCursor();
        if (!$share || (string)$share[$field] === (string)$value) {
            return true;
        }

        $sql->update(self::TABLE_NAME)->set($field, $sql->createNamedParameter($value));
        if ($sql->executeStatement() > 0) {
            // Password material must never enter either the message or event parameters.
            $changes = $field === 'password' ? [] : [
                'previous' => $share[$field],
                'new' => $value,
            ];
            $this->auditShare($action, $share, $changes);
        }
        return true;
    }

    /** Preserve the original deletion predicates, including recipient/owner checks. */
    private function deleteAuditedShares(IQueryBuilder $sql): void
    {
        $statement = $sql->executeQuery();
        $shares = $statement->fetchAll();
        $statement->closeCursor();
        foreach ($shares as $share) {
            $delete = $this->db->getQueryBuilder();
            $delete->delete(self::TABLE_NAME)
                ->where($delete->expr()->eq('id', $delete->createNamedParameter($share['id'])));
            // Do not revoke a share whose ownership or target changed since selection.
            foreach (['uid_initiator', 'uid_owner', 'item_type', 'item_source', 'type'] as $field) {
                $delete->andWhere($share[$field] === null
                    ? $delete->expr()->isNull($field)
                    : $delete->expr()->eq($field, $delete->createNamedParameter($share[$field])));
            }
            if ($delete->executeStatement() > 0) {
                $this->auditShare('revoked', $share);
            }
        }
    }

    private function auditShare(string $action, array $share, array $changes = []): void
    {
        $table = match ($share['item_type']) {
            'report' => self::TABLE_NAME_REPORT,
            'panorama' => self::TABLE_NAME_PANORAMA,
            'dataset' => 'analytics_dataset',
            default => null,
        };
        $name = null;
        if ($table !== null) {
            $query = $this->db->getQueryBuilder();
            $query->select('name')->from($table)
                ->where($query->expr()->eq('id', $query->createNamedParameter($share['item_source'])));
            $result = $query->executeQuery();
            $item = $result->fetch();
            $result->closeCursor();
            $name = $item ? $item['name'] : null;
        }
        $shareType = match ((int)$share['type']) {
            0 => 'user',
            1 => 'group',
            2 => 'user/group',
            3 => 'public link',
            10 => 'Talk room',
            default => 'unknown recipient type',
        };
        $parameters = [
            'itemType' => in_array($share['item_type'], ['report', 'panorama'], true)
                ? 'Analytics ' . $share['item_type']
                : $share['item_type'],
            'name' => $name ?? '(unavailable)',
            'itemId' => (int)$share['item_source'],
            'owner' => $share['uid_initiator'],
            'shareType' => $shareType,
            'recipient' => (int)$share['type'] === 3 ? 'anyone with the link' : ($share['uid_owner'] ?? ''),
            'actor' => $this->userSession->getUser()?->getUID() ?? 'system',
            'shareId' => (int)$share['id'],
        ];
        $message = match ($action) {
            'created' => 'The %s "%s" with ID "%s" owned by "%s" has been shared to the %s "%s" by "%s" (share ID "%s").',
            'revoked' => 'The share of the %s "%s" with ID "%s" from "%s" to the %s "%s" has been revoked by "%s" (share ID "%s").',
            'permissions_changed' => 'The permissions for the %s "%s" with ID "%s" shared from "%s" to the %s "%s" have been changed by "%s" (share ID "%s").',
            'password_changed' => 'The password for the %s "%s" with ID "%s" shared from "%s" to the %s "%s" has been changed by "%s" (share ID "%s").',
            'domain_changed' => 'The embedding domain for the %s "%s" with ID "%s" shared from "%s" to the %s "%s" has been changed by "%s" (share ID "%s").',
        };
        if ($changes !== []) {
            if ($action === 'permissions_changed') {
                foreach ($changes as $key => $value) {
                    $changes[$key] = match ((int)$value) {
                        1 => 'read-only',
                        2 => 'update allowed',
                        default => 'permissions ' . $value,
                    };
                }
            }
            $parameters += $changes;
            $message .= ' Changed from "%s" to "%s".';
        }
        // The first three arguments are supported on every supported Nextcloud version.
        $this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent($message, $parameters));
    }

}
