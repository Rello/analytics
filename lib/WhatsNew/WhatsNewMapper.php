<?php
declare(strict_types=1);
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\WhatsNew;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class WhatsNewMapper extends QBMapper
{
    public const TABLE_NAME = 'analytics_whats_new';
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        IDBConnection $db
    )
    {
        parent::__construct($db, self::TABLE_NAME);
        $this->logger = $logger;
    }

    /**
     * @throws DoesNotExistException
     */
    public function getChanges(string $version): WhatsNewResult
    {
        /* @var $qb IQueryBuilder */
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('*')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('version', $qb->createNamedParameter($version)))
            ->execute();

        $data = $result->fetch();
        $result->closeCursor();
        if ($data === false) {
            throw new DoesNotExistException('Changes info is not present');
        }
        return WhatsNewResult::fromRow($data);
    }
}