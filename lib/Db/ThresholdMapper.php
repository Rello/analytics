<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Db;

use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;

class ThresholdMapper
{
    private $userId;
    private $l10n;
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_threshold';

    public function __construct(
        $userId,
        IL10N $l10n,
        IDBConnection $db,
        ILogger $logger
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->db = $db;
        $this->logger = $logger;
        self::TABLE_NAME;
    }

    public function createThreshold($datasetId, $dimension1, $value, $option, $serverity)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->insert(self::TABLE_NAME)
            ->values([
                'user_id' => $sql->createNamedParameter($this->userId),
                'dataset' => $sql->createNamedParameter($datasetId),
                'dimension1' => $sql->createNamedParameter($dimension1),
                'value' => $sql->createNamedParameter($value),
                'option' => $sql->createNamedParameter($option),
                'severity' => $sql->createNamedParameter($serverity),
            ]);
        $sql->execute();
        return (int)$this->db->lastInsertId(self::TABLE_NAME);
    }

    public function getThresholdsByDataset($datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->addSelect('dimension1')
            ->addSelect('dimension2')
            ->addSelect('value')
            ->addSelect('option')
            ->addSelect('severity')
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)));
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    public function getSevOneThresholdsByDataset($datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
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

    public function deleteThresholdByDataset($datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)));
        $sql->execute();
        return true;
    }
}