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

    public function create($reportId, $dimension1, $value, $option, $severity)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->insert(self::TABLE_NAME)
            ->values([
                'user_id' => $sql->createNamedParameter($this->userId),
                'report' => $sql->createNamedParameter($reportId),
                'dimension1' => $sql->createNamedParameter($dimension1),
                'value' => $sql->createNamedParameter($value),
                'option' => $sql->createNamedParameter($option),
                'severity' => $sql->createNamedParameter($severity),
                'dataset' => $sql->createNamedParameter($reportId),
            ]);
        $sql->execute();
        return (int)$sql->getLastInsertId();
    }

    public function getThresholdsByReport($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->addSelect('dimension1')
            ->addSelect('dimension2')
            ->addSelect('value')
            ->addSelect('option')
            ->addSelect('severity')
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)));
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    public function getSevOneThresholdsByReport($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)))
            ->andWhere($sql->expr()->eq('severity', $sql->createNamedParameter('1')));
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    public function deleteThreshold($thresholdId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($thresholdId)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)));
        $sql->execute();
        return true;
    }

    public function deleteThresholdByReport($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('report', $sql->createNamedParameter($reportId)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)));
        $sql->execute();
        return true;
    }
}