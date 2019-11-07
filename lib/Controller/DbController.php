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

/**
 * Controller class for main page.
 */
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
        $SQL .= ' FROM `*PREFIX*data_facts`
                WHERE `dataset` = ?
                GROUP BY `dataset`';
        if ($objectDrilldown === 'true') $SQL .= ', `dimension1`';
        if ($dateDrilldown === 'true') $SQL .= ', `dimension2`';
        $SQL .= ' ORDER BY';
        if ($objectDrilldown === 'true') $SQL .= ' `dimension1`,';
        $SQL .= ' `dimension2` ASC';

        // $this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($dataset));
        return $stmt->fetchAll();
    }

    /**
     * update data
     */
    public function updateData()
    {
    }

    /**
     * create data
     */
    public function deleteData()
    {
    }

    /**
     * create data
     */
    public function createData($datasetId, $dimension1, $dimension2, $dimension3)
    {
        $SQL = 'SELECT `id` FROM `*PREFIX*data_facts` WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` = ? AND `dimension2` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension2));
        $row = $stmt->fetch();
        if ($row) {
            $SQL = 'UPDATE `*PREFIX*data_facts` SET `dimension3` = ? WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` = ? AND `dimension2` = ?';
            $stmt = $this->db->prepare($SQL);
            $stmt->execute(array($dimension3, $this->userId, $datasetId, $dimension1, $dimension2));
            $stmt->fetch();
            return 'update';
        } else {
            $SQL = 'INSERT INTO `*PREFIX*data_facts` (`user_id`,`dataset`,`dimension1`,`dimension2`,`dimension3`) VALUES(?,?,?,?,?)';
            $stmt = $this->db->prepare($SQL);
            $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension2, $dimension3));
            $stmt->fetch();
            return 'insert';
        }
    }

    /**
     * create dataset
     */
    public function createDataset()
    {
        $SQL = 'INSERT INTO `*PREFIX*data_dataset` (`user_id`,`name`,`type`,`parent`,`dimension1`,`dimension2`,`dimension3`) VALUES(?,?,?,?,?,?,?)';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, 'New', 2, 0, 'object', 'date', 'value'));
        return $stmt->fetch();
    }

    /**
     * update dataset
     */
    public function updateDataset($id, $name, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3)
    {
        $SQL = 'UPDATE `*PREFIX*data_dataset` SET `name`= ?, `type`= ?, `link`= ?, `visualization`= ?, `chart`= ?, `parent`= ?, `dimension1` = ?, `dimension2` = ?, `dimension3` = ? WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $name = $this->truncate($name, 64);
        $stmt->execute(array($name, $type, $link, $visualization, $chart, $parent, $dimension1, $dimension2, $dimension3, $this->userId, $id));
        return $stmt->fetch();
    }

    /**
     * delete dataset
     */
    public function deleteDataset($id)
    {
        $SQL = 'DELETE FROM `*PREFIX*data_dataset` WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $id));
        return $stmt->fetch();
    }

    /**
     * get datasets
     */
    public function getDatasets()
    {
        $SQL = 'SELECT id, name, type, parent FROM `*PREFIX*data_dataset` WHERE  `user_id` = ? ORDER BY `parent` ASC, `name` ASC';
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
        $SQL = 'SELECT * FROM `*PREFIX*data_dataset` WHERE `id` = ? AND `user_id` = ?';
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
        $SQL = 'SELECT `name`, `visualization`, `chart` FROM `*PREFIX*data_dataset` WHERE `id` = ?';
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
        $SQL = 'SELECT DS.*, SH.password AS password FROM `*PREFIX*data_dataset` AS DS JOIN `*PREFIX*data_share` AS SH ON DS.id = SH.dataset WHERE SH.token = ?';
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
        $SQL = 'SELECT DS.id, DS.name, \'S\' as type, 0 as parent FROM `*PREFIX*data_dataset` AS DS JOIN `*PREFIX*data_share` AS SH ON DS.id = SH.dataset WHERE SH.uid_owner = ? ORDER BY DS.name ASC';
        $this->logger->error($this->userId);
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
        $SQL = 'SELECT DS.* FROM `*PREFIX*data_dataset` AS DS JOIN `*PREFIX*data_share` AS SH ON DS.id = SH.dataset WHERE SH.uid_owner = ? AND DS.id = ?';
        //$this->logger->error($id);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $id]);
        return $stmt->fetch();
    }

    public function createShare($datasetId, $type, $uid_owner, $token)
    {
        $SQL = 'INSERT INTO `*PREFIX*data_share` (`dataset`,`type`,`uid_owner`,`uid_initiator`,`token`) VALUES(?,?,?,?,?)';
        //$this->logger->error($datasetId, $type, $uid_owner, $token);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($datasetId, $type, $uid_owner, $this->userId, $token));
        return $stmt->fetch();
    }

    public function getShare($datasetId)
    {
        $SQL = 'SELECT id, type, uid_owner, token, (CASE  WHEN password IS NOT NULL THEN true ELSE false END) AS pass FROM `*PREFIX*data_share` WHERE uid_initiator = ? AND dataset = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $datasetId]);
        return $stmt->fetchAll();
    }

    public function updateShare($shareId, $password)
    {
        $SQL = 'UPDATE `*PREFIX*data_share` SET `password`= ? WHERE `uid_initiator` = ? AND `id` = ?';
        //$this->logger->error($shareId. $password);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$password, $this->userId, $shareId]);
        return $stmt->fetch();
    }

    public function deleteShare($shareId)
    {
        $SQL = 'DELETE FROM `*PREFIX*data_share` WHERE `uid_initiator` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $shareId]);
        return $stmt->fetch();
    }

}
