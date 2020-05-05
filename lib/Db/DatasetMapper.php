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

class DatasetMapper
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
    public function updateDataset($id, $name, $subheader, $parent, $type, $link, $visualization, $chart, $chartoptions, $dimension1, $dimension2, $dimension3)
    {
        $SQL = 'UPDATE `*PREFIX*analytics_dataset` SET `name`= ?, `subheader`= ?, `type`= ?, `link`= ?, `visualization`= ?, `chart`= ?, `chartoptions`= ?, `parent`= ?, `dimension1` = ?, `dimension2` = ?, `dimension3` = ? WHERE `user_id` = ? AND `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $name = $this->truncate($name, 64);
        $stmt->execute(array($name, $subheader, $type, $link, $visualization, $chart, $chartoptions, $parent, $dimension1, $dimension2, $dimension3, $this->userId, $id));
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
     * @param int $id
     * @param string $user_id
     * @return
     */
    public function getOwnDataset($id, string $user_id = null)
    {
        $SQL = 'SELECT * FROM `*PREFIX*analytics_dataset` WHERE `id` = ? AND `user_id` = ?';
        //$this->logger->error($SQL);
        $stmt = $this->db->prepare($SQL);
        if ($user_id) $this->userId = $user_id;
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
        $SQL = 'SELECT `name`, `visualization`, `chart`, `user_id` FROM `*PREFIX*analytics_dataset` WHERE `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($id));
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
