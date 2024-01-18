<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics\Db;

use OCP\DB\Exception;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class DataloadMapper
{
    private $userId;
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_dataload';

    public function __construct(
        $userId,
        IDBConnection $db,
        LoggerInterface $logger
    )
    {
        $this->userId = $userId;
        $this->db = $db;
        $this->logger = $logger;
        self::TABLE_NAME;
    }

    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function rollBack()
    {
        $this->db->rollBack();
    }

    /**
     * create a new dataload
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param int $datasourceId
     * @return integer
     * @throws \OCP\DB\Exception
     */
    public function create(int $datasetId, int $datasourceId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->insert(self::TABLE_NAME)
            ->values([
                'user_id' => $sql->createNamedParameter($this->userId),
                'name' => $sql->createNamedParameter('New'),
                'dataset' => $sql->createNamedParameter($datasetId),
                'datasource' => $sql->createNamedParameter($datasourceId),
                'option' => $sql->createNamedParameter('{}'),
            ]);
        $sql->executeStatement();
        return (int)$sql->getLastInsertId();
    }

    /**
     * get all data loads for a dataset
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return array
     */
    public function read(int $datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    public function getAllDataloadMetadata()
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('dataset')
            ->selectAlias($sql->func()->count('id'), 'dataloads')
            ->selectAlias($sql->func()->max('schedule'), 'schedules')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->addgroupBy('dataset');
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get all data load & schedule metadata
     *
     * @NoAdminRequired
     * @param $schedule
     * @return array
     */
    public function getDataloadBySchedule($schedule)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('schedule', $sql->createNamedParameter($schedule)));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
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
        $name = $this->truncate($name, 64);
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('name', $sql->createNamedParameter($name))
            ->set('option', $sql->createNamedParameter($option))
            ->set('schedule', $sql->createNamedParameter($schedule))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($dataloadId)));
        $sql->executeStatement();
        return true;
    }

    /**
     * copy a data load
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return bool
     * @throws Exception
     */
    public function copy(int $dataloadId)
    {
        $sql = $this->db->getQueryBuilder();
        $selectSql = $sql->select('*')
            ->from(self::TABLE_NAME)
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($dataloadId)));
        $statement = $sql->executeQuery($selectSql);
        $record = $statement->fetch();

        if ($record) {
            $insertSql = $this->db->getQueryBuilder();
            $insertSql->insert(self::TABLE_NAME)
                ->values([
                    'user_id' => $insertSql->createNamedParameter($this->userId),
                    'name' => $insertSql->createNamedParameter($record['name'] . ' (copy)'),
                    'dataset' => $insertSql->createNamedParameter($record['dataset']),
                    'datasource' => $insertSql->createNamedParameter($record['datasource']),
                    'option' => $insertSql->createNamedParameter($record['option']),
                ]);
            $insertSql->executeStatement();
            return true;
        } else {
            return false;
        }
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
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($dataloadId)));
        $sql->executeStatement();
        return true;
    }

    /**
     * delete a dataload
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return bool
     */
    public function deleteByDataset(int $datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)));
        $sql->executeStatement();
        return true;
    }

    /**
     * get data load by id
     * @param int $dataloadId
     * @return array
     */
    public function getDataloadById(int $dataloadId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($dataloadId)));
        $statement = $sql->executeQuery();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
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