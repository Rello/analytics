<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Db;

use OCP\IDbConnection;
use OCP\IL10N;
use OCP\ILogger;

class StorageMapper
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
     * Get file id for single track
     * @param int $dataset
     * @param  $objectDrilldown
     * @param  $dateDrilldown
     * @return array
     */
    public function getData(int $dataset, $objectDrilldown = null, $dateDrilldown = null)
    {
        $SQL = 'SELECT';
        if ($objectDrilldown === 'true') $SQL .= ' `dimension1`,';
        if ($dateDrilldown === 'true') $SQL .= ' `dimension2`,';
        $SQL .= ' SUM(`dimension3`) AS `dimension3`';
        $SQL .= ' FROM `*PREFIX*analytics_facts`
                WHERE `dataset` = ?
                GROUP BY `dataset`';
        if ($objectDrilldown === 'true') $SQL .= ', `dimension1`';
        if ($dateDrilldown === 'true') $SQL .= ', `dimension2`';
        $SQL .= ' ORDER BY';
        if ($objectDrilldown === 'true') $SQL .= ' `dimension1`,';
        $SQL .= ' `dimension2` ASC';

        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($dataset));
        return $stmt->fetchAll();
    }

    /**
     * delete data
     */
    public function deleteData(int $datasetId, $dimension1, $dimension2)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_facts` WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` = ? AND `dimension2` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension2));
        return true;
    }

    /**
     * delete all data of a dataset
     */
    public function deleteDataByDataset(int $datasetId)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_facts` WHERE `user_id` = ? AND `dataset` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId));
        return true;
    }

    /**
     * create data
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @param string|null $user_id
     * @return string
     */
    public function createData(int $datasetId, $dimension1, $dimension2, $dimension3, string $user_id = null)
    {
        if ($user_id) $this->userId = $user_id;
        $SQL = 'SELECT `id` FROM `*PREFIX*analytics_facts` WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` = ? AND `dimension2` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension2));
        $row = $stmt->fetch();
        if ($row) {
            $SQL = 'UPDATE `*PREFIX*analytics_facts` SET `dimension3` = ? WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` = ? AND `dimension2` = ?';
            $stmt = $this->db->prepare($SQL);
            $stmt->execute(array($dimension3, $this->userId, $datasetId, $dimension1, $dimension2));
            //$stmt->fetch();
            return 'update';
        } else {
            $SQL = 'INSERT INTO `*PREFIX*analytics_facts` (`user_id`,`dataset`,`dimension1`,`dimension2`,`dimension3`) VALUES(?,?,?,?,?)';
            $stmt = $this->db->prepare($SQL);
            $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension2, $dimension3));
            return 'insert';
        }
    }
}