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
use Psr\Log\LoggerInterface;

class ThresholdMapper
{
    private $userId;
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_threshold';

    public function __construct(
        $userId,
        IDBConnection $db,
        LoggerInterface $logger
    )
    {
        $this->userId = $userId;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function create($reportId, $dimension, $value, $option, $severity, $coloring)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->insert(self::TABLE_NAME)
            ->values([
                'user_id' => $sql->createNamedParameter($this->userId),
                'report' => $sql->createNamedParameter($reportId),
                'dimension' => $sql->createNamedParameter($dimension),
                'target' => $sql->createNamedParameter($value),
                'option' => $sql->createNamedParameter($option),
                'severity' => $sql->createNamedParameter($severity),
				'coloring' => $sql->createNamedParameter($coloring),
				]);
        $sql->executeStatement();
        return (int)$sql->getLastInsertId();
    }

    /**
     * @throws Exception
     */
    public function getThresholdsByReport($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->addSelect('dimension')
            ->addSelect('coloring')
            ->selectAlias('target', 'value')
            ->addSelect('option')
            ->addSelect('severity')
            ->addSelect('user_id')
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
        ;
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * @throws Exception
     */
    public function getSevOneThresholdsByReport($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)))
            ->andWhere($sql->expr()->eq('severity', $sql->createNamedParameter('1')));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * @throws Exception
     */
    public function deleteThreshold($thresholdId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($thresholdId)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)));
        $sql->executeStatement();
        return true;
    }

    /**
     * @throws Exception
     */
    public function deleteThresholdByReport($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)));
        $sql->executeStatement();
        return true;
    }
}