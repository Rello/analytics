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

class ShareMapper
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

    /**
     * get datasets
     * @param $token
     * @return
     */
    public function getDatasetByToken($token)
    {
        $SQL = 'SELECT DS.*, SH.password AS password FROM `*PREFIX*analytics_dataset` AS DS JOIN `*PREFIX*analytics_share` AS SH ON DS.id = SH.dataset WHERE SH.token = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$token]);
        return $stmt->fetch();
        //$this->logger->error($results['password']);
    }

    /**
     * get shared datasets
     * @return
     */
    public function getSharedDatasets()
    {
        $SQL = 'SELECT DS.id, DS.name, \'99\' as type, 0 as parent FROM `*PREFIX*analytics_dataset` AS DS JOIN `*PREFIX*analytics_share` AS SH ON DS.id = SH.dataset WHERE SH.uid_owner = ? ORDER BY DS.name ASC';
        //$this->logger->error($this->userId);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }

    /**
     * get shared datasets
     * @return
     */
    public function getSharedDataset($id)
    {
        $SQL = 'SELECT DS.* FROM `*PREFIX*analytics_dataset` AS DS JOIN `*PREFIX*analytics_share` AS SH ON DS.id = SH.dataset WHERE SH.uid_owner = ? AND DS.id = ?';
        //$this->logger->error($id);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $id]);
        return $stmt->fetch();
    }

    public function createShare($datasetId, $type, $uid_owner, $token)
    {
        $SQL = 'INSERT INTO `*PREFIX*analytics_share` (`dataset`,`type`,`uid_owner`,`uid_initiator`,`token`) VALUES(?,?,?,?,?)';
        //$this->logger->error($datasetId, $type, $uid_owner, $token);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($datasetId, $type, $uid_owner, $this->userId, $token));
        return true;
    }

    public function getShares($datasetId)
    {
        $SQL = 'SELECT id, type, uid_owner, token, (CASE  WHEN password IS NOT NULL THEN true ELSE false END) AS pass FROM `*PREFIX*analytics_share` WHERE uid_initiator = ? AND dataset = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $datasetId]);
        return $stmt->fetchAll();
    }

    public function getSharedReceiver($datasetId)
    {
        $SQL = 'SELECT uid_owner FROM `*PREFIX*analytics_share` WHERE uid_initiator = ? AND dataset = ? AND type = 0';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $datasetId]);
        return $stmt->fetchAll();
    }

    public function updateShare($shareId, $password)
    {
        $SQL = 'UPDATE `*PREFIX*analytics_share` SET `password`= ? WHERE `uid_initiator` = ? AND `id` = ?';
        //$this->logger->error($shareId. $password);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$password, $this->userId, $shareId]);
        return true;
    }

    public function deleteShare($shareId)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_share` WHERE `uid_initiator` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $shareId]);
        return true;
    }

    public function deleteShareByDataset($datasetId)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_share` WHERE `uid_initiator` = ? AND `dataset` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $datasetId]);
        return true;
    }
}