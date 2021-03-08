<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */

namespace OCA\Analytics\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
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
        IDBConnection $db,
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
     * create data
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @param string|null $user_id
     * @param int|null $timestamp
     * @param null $bulkInsert
     * @return string
     */
    public function create(int $datasetId, $dimension1, $dimension2, $value, string $user_id = null, $timestamp = null, $bulkInsert = null)
    {
        $dimension1 = str_replace('*', '', $dimension1);
        $dimension2 = str_replace('*', '', $dimension2);
        $timestamp = $timestamp ?? time();
        $result = '';

        if ($user_id) $this->userId = $user_id;

        if ($bulkInsert === null) {
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
        }

        if ($result) {
            $sql = $this->db->getQueryBuilder();
            $sql->update(self::TABLE_NAME)
                ->set('value', $sql->createNamedParameter($value))
                ->set('timestamp', $sql->createNamedParameter($timestamp))
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
                    'value' => $sql->createNamedParameter($value),
                    'timestamp' => $sql->createNamedParameter($timestamp),
                ]);
            $sql->execute();
            return 'insert';
        }
    }

    /**
     * read data for dataset
     * @param int $dataset
     * @param array $options
     * @return array
     */
    public function read(int $dataset, $options = '')
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($dataset)))
            ->addgroupBy('dataset');

        // loop the available dimensions and check if any is hidden by the drilldown selection of the user
        $availableDimensions = array('dimension1', 'dimension2');
        foreach ($availableDimensions as $dimension) {
            if (!isset($options['drilldown'][$dimension])) {
                $sql->addSelect($dimension)
                    ->addGroupBy($dimension)
                    ->addOrderBy($dimension, 'ASC');
            }
        }

        // value column deeds to be at the last position in the select. So it needs to be after the dynamic selects
        $sql->addSelect($sql->func()->sum('value'));

        // add the where clauses depending on the filter selection of the
        if (isset($options['filter'])) {
            foreach ($options['filter'] as $key => $value) {
                $this->sqlWhere($sql, $key, $value['option'], $value['value']);
            }
        }

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
     * delete data
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return bool
     */
    public function delete(int $datasetId, $dimension1, $dimension2)
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
     * @return array
     */
    public function deleteSimulate(int $datasetId, $dimension1, $dimension2)
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
    public function deleteByDataset(int $datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)));
        $sql->execute();
        return true;
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
}