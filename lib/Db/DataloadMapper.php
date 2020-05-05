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

class DataloadMapper
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
     * create a new dataload
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param int $datasourceId
     * @return integer
     */
    public function create(int $datasetId, int $datasourceId)
    {
        $SQL = 'INSERT INTO `*PREFIX*analytics_dataload` (`user_id`,`name`,`dataset`,`datasource`,`option`) VALUES(?,?,?,?,?)';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, 'New', $datasetId, $datasourceId, '{}'));
        return $this->db->lastInsertId('*PREFIX*analytics_dataload');
    }

    /**
     * get all dataloads for a dataset
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return array
     */
    public function read(int $datasetId)
    {
        $SQL = 'SELECT * FROM `*PREFIX*analytics_dataload` WHERE `user_id` = ? AND `dataset` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId));
        return $stmt->fetchAll();
    }

    /**
     * get all dataload & schedule metadata
     *
     * @NoAdminRequired
     * @return array
     */
    public function getAllDataloadMetadata()
    {
        $SQL = 'SELECT `dataset`, COUNT(id) AS `dataloads`, COUNT(NULLIF( schedule, \'\' )) AS `schedules` FROM `*PREFIX*analytics_dataload` WHERE `user_id` = ? GROUP BY dataset';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId));
        return $stmt->fetchAll();
    }

    /**
     * get all dataload & schedule metadata
     *
     * @NoAdminRequired
     * @param $schedule
     * @return array
     */
    public function getDataloadBySchedule($schedule)
    {
        $SQL = 'SELECT `id` FROM `*PREFIX*analytics_dataload` WHERE `schedule` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($schedule));
        return $stmt->fetchAll();
    }

    /**
     * update dataload
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @param $name
     * @param $option
     * @param $schedule
     * @return bool
     */
    public function update(int $dataloadId, $name, $option, $schedule)
    {
        $SQL = 'UPDATE `*PREFIX*analytics_dataload` SET `name`= ?, `option`= ?, `schedule` = ? WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $name = $this->truncate($name, 64);
        $stmt->execute(array($name, $option, $schedule, $this->userId, $dataloadId));
        return true;
    }

    /**
     * delete a dataload
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return bool
     */
    public function delete(int $dataloadId)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_dataload` WHERE `id` = ? AND `user_id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$dataloadId, $this->userId]);
        return true;
    }

    /**
     * delete a dataload
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return bool
     */
    public function deleteDataloadByDataset(int $datasetId)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_dataload` WHERE `dataset` = ? AND `user_id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$datasetId, $this->userId]);
        return true;
    }

    /**
     * get Dataload by id
     * @param int $dataloadId
     * @return array
     */
    public function getDataloadById(int $dataloadId)
    {
        $SQL = 'SELECT * FROM `*PREFIX*analytics_dataload` WHERE `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($dataloadId));
        return $stmt->fetch();
    }

    /**
     * truncates fiels do DB-field size
     *
     * @param $string
     * @param $length
     * @param $dots
     * @return string
     */
    private function truncate($string, $length, $dots = "...")
    {
        return (strlen($string) > $length) ? mb_strcut($string, 0, $length - strlen($dots)) . $dots : $string;
    }
}