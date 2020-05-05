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

use OCP\DB\QueryBuilder\IQueryBuilder;
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
     * @param array $options
     * @return array
     */
    public function getData(int $dataset, $options)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from('*PREFIX*analytics_facts');
        $sql->where($sql->expr()->eq('dataset', $sql->createNamedParameter($dataset)));
        $sql->addgroupBy('dataset');

        // derive if a column should be removed from drilldown as by user input
        // take all possible columns and overwrite with "false" if demanded
        $drilldownColumns = array('dimension1' => 'true', 'dimension2' => 'true');
        if (isset($options['drilldown'])) {
            $drilldownColumns = array_intersect_key($options['drilldown'], $drilldownColumns) + $drilldownColumns;
        }
        foreach ($drilldownColumns as $key => $value) {
            if ($value !== 'false') {
                $sql->addSelect($key);
                $sql->addGroupBy($key);
                $sql->addOrderBy($key, 'ASC');
            }
        }

        // value column deeds to be at the last position in the select. So it needs to be after the dynamic selects
        $sql->addSelect($sql->func()->sum('dimension3'));

        // add the where clauses
        foreach ($options['filter'] as $key => $value) {
            if ($value['enabled'] === 'true') {
                $this->sqlWhere($sql, $key, $value['option'], $value['value']);
            }
        }

        //$this->logger->debug('StorageMapper 79: ' . $sql->getSQL());
        //$this->logger->debug('StorageMapper 79: ' . json_encode($sql->getParameters()));
        $statement = $sql->execute();
        $rows = $statement->fetchAll();
        $statement->closeCursor();

        // reindex result to get rid of the column headers as the frontend works incremental
        foreach ($rows as &$row) {
            $row = array_values($row);
        }
        return $rows;
    }

    /**
     * Add where statements to a query builder
     *
     * @param IQueryBuilder $sql
     * @param $column
     * @param $option
     * @param $value
     */
    protected function sqlWhere(IQueryBuilder $sql, $column, $option, $value)
    {
        if ($option === 'EQ') {
            $sql->andWhere($sql->expr()->eq($column, $sql->createNamedParameter($value)));
        } elseif ($option === 'GT') {
            $sql->andWhere($sql->expr()->gt($column, $sql->createNamedParameter($value)));
        } elseif ($option === 'LT') {
            $sql->andWhere($sql->expr()->lt($column, $sql->createNamedParameter($value)));
        } elseif ($option === 'IN') {
            $sql->andWhere($sql->expr()->in($column, $sql->createParameter('inValues')));
            $sql->setParameter('inValues', explode(',', $value), IQueryBuilder::PARAM_STR_ARRAY);
        } elseif ($option === 'LIKE') {
            $sql->andWhere($sql->expr()->like($column, $sql->createNamedParameter('%' . $value . '%')));
        }
    }

    /**
     * delete data
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return bool
     */
    public function deleteData(int $datasetId, $dimension1, $dimension2)
    {
        $dimension1 = str_replace('*', '%', $dimension1);
        $dimension2 = str_replace('*', '%', $dimension2);
        $SQL = 'DELETE FROM `*PREFIX*analytics_facts` WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` like ? AND `dimension2` like ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension2));
        return true;
    }

    /**
     * Simulate delete data
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return array
     */
    public function deleteDataSimulate(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        $dimension1 = str_replace('*', '%', $dimension1);
        $dimension2 = str_replace('*', '%', $dimension2);
        $SQL = 'select count(*) as `count` FROM `*PREFIX*analytics_facts` WHERE `user_id` = ? AND `dataset` = ? AND `dimension1` like ? AND `dimension2` like ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $datasetId, $dimension1, $dimension2));
        return $stmt->fetch();
    }

    /**
     * delete all data of a dataset
     * @param int $datasetId
     * @return bool
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
        $dimension1 = str_replace('*', '', $dimension1);
        $dimension2 = str_replace('*', '', $dimension2);
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