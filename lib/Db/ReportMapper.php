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
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class ReportMapper
{
    private $userId;
    private $l10n;
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_report';

    public function __construct(
        $userId,
        IL10N $l10n,
        IDBConnection $db,
        LoggerInterface $logger
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * get reports
     * @return array
     * @throws Exception
     */
    public function index()
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->addSelect('name')
            ->addSelect('type')
            ->addSelect('parent')
            ->addSelect('dataset')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->orderBy('parent', 'ASC')
            ->addOrderBy('name', 'ASC');
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();

        if ($result !== false && is_array($result)) {
            foreach ($result as &$row) {
                $row['dataset'] = (int) $row['dataset'];
                $row['type'] = (int) $row['type'];
                $row['parent'] = (int) $row['parent'];
            }
            unset($row); // Unset reference
        }

        return $result;
    }

    /**
     * get reports for user
     * @param $userId
     * @return array
     * @throws Exception
     */
    public function indexByUser($userId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($userId)));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * create report
     * @param $name
     * @param $subheader
     * @param $parent
     * @param $type
     * @param $dataset
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return int
     * @throws Exception
     */
    public function create($name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->insert(self::TABLE_NAME)
            ->values([
                'user_id' => $sql->createNamedParameter($this->userId),
                'dataset' => $sql->createNamedParameter($dataset),
                'name' => $sql->createNamedParameter($name),
                'subheader' => $sql->createNamedParameter($subheader),
                'link' => $sql->createNamedParameter($link),
                'type' => $sql->createNamedParameter($type),
                'parent' => $sql->createNamedParameter($parent),
                'dimension1' => $sql->createNamedParameter($dimension1),
                'dimension2' => $sql->createNamedParameter($dimension2),
                //'dimension3' => $sql->createNamedParameter($this->l10n->t('Value')),
                //'dimension4' => $sql->createNamedParameter($this->l10n->t('Value')),
                //'timestamp' => $sql->createNamedParameter($this->l10n->t('Date')),
                //'unit' => $sql->createNamedParameter($this->l10n->t('Value')),
                'value' => $sql->createNamedParameter($value),
                'chart' => $sql->createNamedParameter($chart),
                'visualization' => $sql->createNamedParameter($visualization),
            ]);
        $sql->executeStatement();
        return (int)$sql->getLastInsertId();
    }

    /**
     * get single report for user
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function readOwn(int $id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($id)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->orderBy('parent', 'ASC')
            ->addOrderBy('name', 'ASC');
        $statement = $sql->executeQuery();
        $result = $statement->fetch();
        $statement->closeCursor();

        if ($result !== false) {
            $result['dataset'] = (int) $result['dataset'];
            $result['type'] = (int) $result['type'];
            $result['parent'] = (int) $result['parent'];
            $result['refresh'] = (int) $result['refresh'];
        }

        return $result;
    }

    /**
     * get single report
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function read(int $id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $statement = $sql->executeQuery();
        $result = $statement->fetch();
        $statement->closeCursor();

        if ($result !== false) {
            $result['dataset'] = (int) $result['dataset'];
            $result['type'] = (int) $result['type'];
            $result['parent'] = (int) $result['parent'];
            $result['refresh'] = (int) $result['refresh'];
        }

        return $result;
    }

    /**
     * update report
     * @param $id
     * @param $name
     * @param $subheader
     * @param $parent
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $chartoptions
     * @param $dataoptions
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @param $filteroptions
     * @return bool
     * @throws Exception
     */
    public function update($id, $name, $subheader, $parent, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value, $filteroptions = null)
    {
        $name = $this->truncate($name, 64);
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('name', $sql->createNamedParameter($name))
            ->set('subheader', $sql->createNamedParameter($subheader))
            ->set('link', $sql->createNamedParameter($link))
            ->set('visualization', $sql->createNamedParameter($visualization))
            ->set('chart', $sql->createNamedParameter($chart))
            ->set('chartoptions', $sql->createNamedParameter($chartoptions))
            ->set('dataoptions', $sql->createNamedParameter($dataoptions))
            ->set('parent', $sql->createNamedParameter($parent))
            ->set('dimension1', $sql->createNamedParameter($dimension1))
            ->set('dimension2', $sql->createNamedParameter($dimension2))
            ->set('value', $sql->createNamedParameter($value))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        if ($filteroptions !== null) $sql->set('filteroptions', $sql->createNamedParameter($filteroptions));
        $sql->executeStatement();
        return true;
    }

    /**
     * update report options
     * @param $id
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @param $tableoptions
     * @return bool
     * @throws Exception
     */
    public function updateOptions($id, $chartoptions, $dataoptions, $filteroptions, $tableoptions)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('chartoptions', $sql->createNamedParameter($chartoptions))
            ->set('dataoptions', $sql->createNamedParameter($dataoptions))
            ->set('filteroptions', $sql->createNamedParameter($filteroptions))
            ->set('tableoptions', $sql->createNamedParameter($tableoptions))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->executeStatement();
        return true;
    }

    /**
     * update report refresh interval
     * @param $id
     * @param $refresh
     * @return bool
     * @throws Exception
     */
    public function updateRefresh($id, $refresh)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('refresh', $sql->createNamedParameter($refresh))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->executeStatement();
        return true;
    }

    /**
     * update report group assignment (from drag & drop)
     * @param $id
     * @param $groupId
     * @return bool
     * @throws Exception
     */
    public function updateGroup($id, $groupId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('parent', $sql->createNamedParameter($groupId))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->executeStatement();
        return true;
    }

    /**
     * read report options
     * @param $id
     * @return array
     * @throws Exception
     */
    public function readOptions($id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('name')
            ->addSelect('visualization')
            ->addSelect('chart')
            ->addSelect('user_id')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $statement = $sql->executeQuery();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * delete report
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function delete($id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->executeStatement();
        return true;
    }

    /**
     * search reports by search string
     * @param $searchString
     * @return array
     * @throws Exception
     */
    public function search($searchString)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->addSelect('name')
            ->addSelect('type')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->iLike('name', $sql->createNamedParameter('%' . $this->db->escapeLikeParameter($searchString) . '%')))
            ->orderBy('name', 'ASC');
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();

        if ($result !== false && is_array($result)) {
            foreach ($result as &$row) {
                $row['type'] = (int) $row['type'];
            }
            unset($row); // Unset reference
        }

        return $result;
    }

    /**
     * get the report owner
     * @param $reportId
     * @return int
     * @throws Exception
     */
    public function getOwner($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('user_id')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($reportId)));
        return (string)$sql->executeQuery()->fetchOne();
    }

    /**
     * get reports by group
     * @param $groupId
     * @return array
     * @throws Exception
     */
    public function getReportsByGroup($groupId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('parent', $sql->createNamedParameter($groupId)));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();

        if ($result !== false && is_array($result)) {
            foreach ($result as &$row) {
                $row['dataset'] = (int) $row['dataset'];
                $row['type'] = (int) $row['type'];
                $row['parent'] = (int) $row['parent'];
            }
            unset($row); // Unset reference
        }

        return $result;
    }

    /**
     * reports for a dataset
     * @param $datasetId
     * @return array
     * @throws Exception
     */
    public function reportsForDataset($datasetId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->addSelect('name')
            ->addSelect('user_id')
            ->where($sql->expr()->eq('dataset', $sql->createNamedParameter($datasetId)));
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
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