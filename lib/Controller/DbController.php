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
    public function getData(int $dataset, $objectDrilldown = null, $dateDrilldown = null)
    {
        $SQL = 'SELECT';
        if ($objectDrilldown === 'true') $SQL .= ' `object`.`name` AS `object`,';
        if ($dateDrilldown === 'true') $SQL .= ' `fact`.`date` AS `date`,';
        $SQL .= ' SUM(`fact`.`value`) AS `value`';
        $SQL .= ' FROM `*PREFIX*data_facts` AS `fact`
                LEFT JOIN `*PREFIX*data_object` AS `object`
                ON  `fact`.`object` = `object`.`id`
                WHERE  `fact`.`user_id` = ? AND `fact`.`dataset` = ?
                GROUP BY `fact`.`dataset`';
        if ($objectDrilldown === 'true') $SQL .= ', `object`.`name`';
        if ($dateDrilldown === 'true') $SQL .= ', `fact`.`date`';
        $SQL .= ' ORDER BY';
        if ($objectDrilldown === 'true') $SQL .= ' `object`,';
        $SQL .= ' `date` ASC';

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
    public function createData()
    {
    }

    /**
     * create dataset
     */
    public function createDataset()
    {
        $SQL = 'INSERT INTO `*PREFIX*data_dataset` (`user_id`,`name`) VALUES(?,?)';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, 'New'));
        $results = $stmt->fetchAll();
        return $results;
    }

    /**
     * update dataset
     */
    public function updateDataset($id, $name, $type, $link, $visualization, $chart)
    {
        $SQL = 'UPDATE `*PREFIX*data_dataset` SET `name`= ?, `type`= ?, `link`= ?, `visualization`= ?, `chart`= ? WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($name, $type, $link, $visualization, $chart, $this->userId, $id));
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
        $SQL = 'SELECT `id`, `name`, `type`, `link`, `visualization`, `chart` FROM `*PREFIX*data_dataset` WHERE  `user_id` = ? ORDER BY `name` ASC';
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
        $SQL = 'SELECT `id`, `name`, `type`, `link`, `visualization`, `chart` FROM `*PREFIX*data_dataset` WHERE  `user_id` = ? AND `id` = ?';
        //$this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $id));
        $results = $stmt->fetchAll();
        return $results;
    }

}
