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

use OCP\IDbConnection;
use OCP\IL10N;
use OCP\ILogger;

class ThresholdMapper
{
    private $userId;
    private $l10n;
    private $db;
    private $logger;

    public function __construct(
        $userId,
        IL10N $l10n,
        IDbConnection $db,
        ILogger $logger
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function createThreshold($datasetId, $dimension1, $dimension3, $option, $serverity)
    {
        $SQL = 'INSERT INTO `*PREFIX*analytics_threshold` (`user_id`,`dataset`,`dimension1`,`dimension3`,`option`,`severity`) VALUES(?,?,?,?,?,?)';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension3, $option, $serverity));
        $insertid = $this->db->lastInsertId('*PREFIX*analytics_threashold');
        return $insertid;
    }

    public function getThresholdsByDataset($datasetId)
    {
        $SQL = 'SELECT `id`, `dimension1`, `dimension2`, `dimension3`, `option`, `severity` FROM `*PREFIX*analytics_threshold` WHERE `dataset` = ?';
        //$this->logger->error($SQL);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($datasetId));
        return $stmt->fetchAll();
    }

    public function getSevOneThresholdsByDataset($datasetId)
    {
        $SQL = 'SELECT * FROM `*PREFIX*analytics_threshold` WHERE `dataset` = ? AND `severity` = 1';
        //$this->logger->error($SQL);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($datasetId));
        return $stmt->fetchAll();
    }

    public function deleteThreshold($thresholdId)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_threshold` WHERE `id` = ? AND `user_id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$thresholdId, $this->userId]);
        return true;
    }

    public function deleteThresholdByDataset($datasetId)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_threshold` WHERE `dataset` = ? AND `user_id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$datasetId, $this->userId]);
        return true;
    }
}

