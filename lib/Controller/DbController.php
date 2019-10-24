<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Controller;

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
     * validate unsigned int values
     *
     * @param string $value
     * @return int value
     */
    private function normalizeInteger($value)
    {
        // convert format '1/10' to '1' and '-1' to null
        $tmp = explode('/', $value);
        $tmp = explode('-', $tmp[0]);
        $value = $tmp[0];
        if (is_numeric($value) && ((int)$value) > 0) {
            $value = (int)$value;
        } else {
            $value = 0;
        }
        return $value;
    }

    /**
     * Get file id for single track
     * @param int $dataset
     * @param  $objectDrilldown
     * @param  $dateDrilldown
     * @return array
     */
    public function getData(int $dataset, $objectDrilldown = null, $dateDrilldown = null, $userId = null)
    {
        if (isset($userId)) $this->userId = $userId;
        $SQL = 'SELECT';
        if ($objectDrilldown === 'true') $SQL .= ' `dimension1`,';
        if ($dateDrilldown === 'true') $SQL .= ' `dimension2`,';
        $SQL .= ' SUM(`dimension3`) AS `dimension3`';
        $SQL .= ' FROM `*PREFIX*data_facts`
                WHERE  `user_id` = ? AND `dataset` = ?
                GROUP BY `dataset`';
        if ($objectDrilldown === 'true') $SQL .= ', `dimension1`';
        if ($dateDrilldown === 'true') $SQL .= ', `dimension2`';
        $SQL .= ' ORDER BY';
        if ($objectDrilldown === 'true') $SQL .= ' `dimension1`,';
        $SQL .= ' `dimension2` ASC';

        // $this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $dataset));
        $results = $stmt->fetchAll();
        return $results;
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
    public function createData($datasetId, $object, $date, $value)
    {
        $SQL = 'SELECT `id` FROM `*PREFIX*data_facts` WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` = ? AND `dimension2` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId, $object, $date));
        $row = $stmt->fetch();
        if ($row) {
            $SQL = 'UPDATE `*PREFIX*data_facts` SET `value` = ? WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` = ? AND `dimension2` = ?';
            $stmt = $this->db->prepare($SQL);
            $stmt->execute(array($value, $this->userId, $datasetId, $object, $date));
            $stmt->fetch();
            return 'update';
        } else {
            $SQL = 'INSERT INTO `*PREFIX*data_facts` (`user_id`,`dataset`,`dimension1`,`dimension2`,`dimension3`) VALUES(?,?,?,?,?)';
            $stmt = $this->db->prepare($SQL);
            $stmt->execute(array($this->userId, $datasetId, $object, $date, $value));
            $stmt->fetch();
            return 'insert';
        }
    }

    /**
     * create dataset
     */
    public function createDataset()
    {
        $SQL = 'INSERT INTO `*PREFIX*data_dataset` (`user_id`,`name`,`dimension1`,`dimension2`,`dimension3`) VALUES(?,?,?,?,?)';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, 'New', 'object', 'date', 'value'));
        $results = $stmt->fetch();
        return $results;
    }

    /**
     * update dataset
     */
    public function updateDataset($id, $name, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3)
    {
        $SQL = 'UPDATE `*PREFIX*data_dataset` SET `name`= ?, `type`= ?, `link`= ?, `visualization`= ?, `chart`= ?, `parent`= ?, `dimension1` = ?, `dimension2` = ?, `dimension3` = ? WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($name, $type, $link, $visualization, $chart, $parent, $dimension1, $dimension2, $dimension3, $this->userId, $id));
    }

    /**
     * delete dataset
     */
    public function deleteDataset($id)
    {
        $SQL = 'DELETE FROM `*PREFIX*data_dataset` WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $id));
    }

    /**
     * get datasets
     */
    public function getDatasets()
    {
        $SQL = 'SELECT id, name, type FROM `*PREFIX*data_dataset` WHERE  `user_id` = ? ORDER BY `name` ASC';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId));
        $results = $stmt->fetchAll();
        return $results;
    }

    /**
     * get datasets
     * @param $id
     * @return
     */
    public function getDataset($id)
    {
        $SQL = 'SELECT * FROM `*PREFIX*data_dataset` WHERE `id` = ? AND `user_id` = ?';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($id, $this->userId));
        $results = $stmt->fetch();
        return $results;
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
        $results = $stmt->fetch();
        return $results;
    }

    /**
     * get datasets
     * @param $token
     * @return
     */
    public function getDatasetByToken($token)
    {
        $SQL = 'SELECT * FROM `*PREFIX*data_share` WHERE `token` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$token]);
        $results = $stmt->fetch();
        return $results;
        //$this->logger->error($results['password']);
    }

    /**
     * get shared datasets
     * @return
     */
    public function getSharedDatasets()
    {
        $SQL = 'SELECT DS.id, DS.name, \'S\' as type FROM `*PREFIX*data_dataset` AS DS JOIN `*PREFIX*data_share` AS SH ON DS.id = SH.dataset WHERE SH.uid_owner = ? ORDER BY DS.name ASC';
        $this->logger->error($this->userId);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId]);
        $results = $stmt->fetchAll();
        return $results;
    }

    /**
     * get shared datasets
     * @return
     */
    public function getSharedDataset($id)
    {
        $SQL = 'SELECT DS.* FROM `*PREFIX*data_dataset` AS DS JOIN `*PREFIX*data_share` AS SH ON DS.id = SH.dataset WHERE SH.uid_owner = ? AND DS.id = ? ORDER BY DS.name ASC';
        $this->logger->error($id);
        $stmt = $this->db->prepare($SQL);
        $stmt->execute([$this->userId, $id]);
        $results = $stmt->fetch();
        return $results;
    }

}
