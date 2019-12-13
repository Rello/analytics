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

namespace OCA\Analytics\Controller;

use OCP\AppFramework\Controller;
use OCP\IDbConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

class DbController extends Controller
{

    private $userId;
    private $l10n;
    private $db;
    private $logger;

    public function __construct(
        $appName,
        IRequest $request,
        $userId,
        IL10N $l10n,
        IDbConnection $db,
        ILogger $logger
    )
    {
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->db = $db;
        $this->logger = $logger;
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
     * @return string
     */
    public function createData(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
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

    /**
     * create dataset
     */
    public function createDataset()
    {
        $SQL = 'INSERT INTO `*PREFIX*analytics_dataset` (`user_id`,`name`,`type`,`parent`,`dimension1`,`dimension2`,`dimension3`) VALUES(?,?,?,?,?,?,?)';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $this->l10n->t('New'), 2, 0, $this->l10n->t('Object'), $this->l10n->t('Date'), $this->l10n->t('Value')));
        $insertid = $this->db->lastInsertId('*PREFIX*analytics_dataset');
        return $insertid;
    }

    /**
     * update dataset
     * @param $id
     * @param $name
     * @param $subheader
     * @param $parent
     * @param $type
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return bool
     */
    public function updateDataset($id, $name, $subheader, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3)
    {
        $SQL = 'UPDATE `*PREFIX*analytics_dataset` SET `name`= ?, `subheader`= ?, `type`= ?, `link`= ?, `visualization`= ?, `chart`= ?, `parent`= ?, `dimension1` = ?, `dimension2` = ?, `dimension3` = ? WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $name = $this->truncate($name, 64);
        $stmt->execute(array($name, $subheader, $type, $link, $visualization, $chart, $parent, $dimension1, $dimension2, $dimension3, $this->userId, $id));
        return true;
    }

    /**
     * delete dataset
     * @param $id
     * @return
     */
    public function deleteDataset($id)
    {
        $SQL = 'DELETE FROM `*PREFIX*analytics_dataset` WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $id));
        return true;
    }

    /**
     * get datasets
     */
    public function getDatasets()
    {
        $SQL = 'SELECT id, name, type, parent FROM `*PREFIX*analytics_dataset` WHERE  `user_id` = ? ORDER BY `parent` ASC, `name` ASC';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId));
        return $stmt->fetchAll();
    }

    /**
     * get datasets
     * @param $id
     * @return
     */
    public function getOwnDataset($id)
    {
        $SQL = 'SELECT * FROM `*PREFIX*analytics_dataset` WHERE `id` = ? AND `user_id` = ?';
        //$this->logger->error($SQL);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($id, $this->userId));
        return $stmt->fetch();
    }

    /**
     * get datasets
     * @param $id
     * @return
     */
    public function getDatasetOptions($id)
    {
        $SQL = 'SELECT `name`, `visualization`, `chart` FROM `*PREFIX*analytics_dataset` WHERE `id` = ?';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($id));
        return $stmt->fetch();
    }

    // ********** SHARE ************
    // ********** SHARE ************
    // ********** SHARE ************

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
        $SQL = 'SELECT * FROM `*PREFIX*analytics_threshold` WHERE `dataset` = ?';
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

}