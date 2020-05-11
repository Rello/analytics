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
    const TABLE_NAME = 'analytics_facts';

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
        self::TABLE_NAME;
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
        $sql->from(self::TABLE_NAME)
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($dataset)))
            ->addgroupBy('dataset');

        // derive if a column should be removed from drilldown as by user input
        // take all possible columns and overwrite with "false" if demanded
        $drilldownColumns = array('dimension1' => 'true', 'dimension2' => 'true');
        if (isset($options['drilldown'])) {
            $drilldownColumns = array_intersect_key($options['drilldown'], $drilldownColumns) + $drilldownColumns;
        }
        foreach ($drilldownColumns as $key => $value) {
            if ($value !== 'false') {
                $sql->addSelect($key)
                    ->addGroupBy($key)
                    ->addOrderBy($key, 'ASC');
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

        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->like('dimension1', $sql->createNamedParameter($dimension1)))
            ->andWhere($sql->expr()->like('dimension2', $sql->createNamedParameter($dimension2)));
        $sql->execute();

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

        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->addSelect($sql->func()->count('*'))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->like('dimension1', $sql->createNamedParameter($dimension1)))
            ->andWhere($sql->expr()->like('dimension2', $sql->createNamedParameter($dimension2)));
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();

        return $result;
    }

    /**
     * delete all data of a dataset
     * @param int $datasetId
     * @return bool
     */
    public function deleteDataByDataset(int $datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)));
        $sql->execute();
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

        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->addSelect('id')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
            ->andWhere($sql->expr()->eq('dimension1', $sql->createNamedParameter($dimension1)))
            ->andWhere($sql->expr()->eq('dimension2', $sql->createNamedParameter($dimension2)));
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();

        if ($result) {
            $sql = $this->db->getQueryBuilder();
            $sql->update(self::TABLE_NAME)
                ->set('dimension3', $sql->createNamedParameter($dimension3))
                ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
                ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)))
                ->andWhere($sql->expr()->eq('dimension1', $sql->createNamedParameter($dimension1)))
                ->andWhere($sql->expr()->eq('dimension2', $sql->createNamedParameter($dimension2)));
            $sql->execute();
            return 'update';
        } else {
            $sql = $this->db->getQueryBuilder();
            $sql->insert(self::TABLE_NAME)
                ->values([
                    'user_id' => $sql->createNamedParameter($this->userId),
                    'dataset' => $sql->createNamedParameter($datasetId),
                    'dimension1' => $sql->createNamedParameter($dimension1),
                    'dimension2' => $sql->createNamedParameter($dimension2),
                    'dimension3' => $sql->createNamedParameter($dimension3),
                ]);
            $sql->execute();
            return 'insert';
        }
    }
}