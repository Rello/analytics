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
     */
    public function index()
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('id')
            ->addSelect('name')
            ->addSelect('type')
            ->addSelect('parent')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->orderBy('parent', 'ASC')
            ->addOrderBy('name', 'ASC');
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * create report
     * @return int
     */
    public function create()
    {
        $sql = $this->db->getQueryBuilder();
        $sql->insert(self::TABLE_NAME)
            ->values([
                'user_id' => $sql->createNamedParameter($this->userId),
                'name' => $sql->createNamedParameter($this->l10n->t('New')),
                'type' => $sql->createNamedParameter(2),
                'parent' => $sql->createNamedParameter(0),
                'dimension1' => $sql->createNamedParameter($this->l10n->t('Object')),
                'dimension2' => $sql->createNamedParameter($this->l10n->t('Date')),
                //'dimension3' => $sql->createNamedParameter($this->l10n->t('Value')),
                //'dimension4' => $sql->createNamedParameter($this->l10n->t('Value')),
                //'timestamp' => $sql->createNamedParameter($this->l10n->t('Date')),
                //'unit' => $sql->createNamedParameter($this->l10n->t('Value')),
                'value' => $sql->createNamedParameter($this->l10n->t('Value')),
                'chart' => $sql->createNamedParameter('column'),
                'visualization' => $sql->createNamedParameter('ct'),
                'dataset' => $sql->createNamedParameter('0'),
            ]);
        $sql->execute();
        return (int)$sql->getLastInsertId();
    }

    /**
     * get single report
     * @param int $id
     * @param string|null $user_id
     * @return array
     */
    public function read(int $id, string $user_id = null)
    {
        if ($user_id) $this->userId = $user_id;

        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('*')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($id)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->orderBy('parent', 'ASC')
            ->addOrderBy('name', 'ASC');
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * update report
     * @param $id
     * @param $name
     * @param $subheader
     * @param $parent
     * @param $type
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
     */
    public function update($id, $name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value, $filteroptions = null)
    {
        $name = $this->truncate($name, 64);
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('name', $sql->createNamedParameter($name))
            ->set('subheader', $sql->createNamedParameter($subheader))
            ->set('type', $sql->createNamedParameter($type))
            ->set('link', $sql->createNamedParameter($link))
            ->set('visualization', $sql->createNamedParameter($visualization))
            ->set('chart', $sql->createNamedParameter($chart))
            ->set('chartoptions', $sql->createNamedParameter($chartoptions))
            ->set('dataoptions', $sql->createNamedParameter($dataoptions))
            ->set('parent', $sql->createNamedParameter($parent))
            ->set('dimension1', $sql->createNamedParameter($dimension1))
            ->set('dimension2', $sql->createNamedParameter($dimension2))
            ->set('value', $sql->createNamedParameter($value))
            ->set('dataset', $sql->createNamedParameter($dataset))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        if ($filteroptions !== null) $sql->set('filteroptions', $sql->createNamedParameter($filteroptions));
        $sql->execute();
        return true;
    }

    /**
     * update report options
     * @param $id
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return bool
     */
    public function updateOptions($id, $chartoptions, $dataoptions, $filteroptions)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('chartoptions', $sql->createNamedParameter($chartoptions))
            ->set('dataoptions', $sql->createNamedParameter($dataoptions))
            ->set('filteroptions', $sql->createNamedParameter($filteroptions))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->execute();
        return true;
    }

    /**
     * update report refresh interval
     * @param $id
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return bool
     */
    public function updateRefresh($id, $refresh)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('refresh', $sql->createNamedParameter($refresh))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->execute();
        return true;
    }

    /**
     * read report options
     * @param $id
     * @return array
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
        $statement = $sql->execute();
        $result = $statement->fetch();
        $statement->closeCursor();
        return $result;
    }

    /**
     * delete report
     * @param $id
     * @return bool
     */
    public function delete($id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->execute();
        return true;
    }

    /**
     * search reports by searchstring
     * @param $searchString
     * @return array
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
        $statement = $sql->execute();
        $result = $statement->fetchAll();
        $statement->closeCursor();
        return $result;
    }

    /**
     * get the report owner
     * @param $reportId
     * @return int
     */
    public function getOwner($reportId)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->from(self::TABLE_NAME)
            ->select('user_id')
            ->where($sql->expr()->eq('id', $sql->createNamedParameter($reportId)));
        $result = (string)$sql->execute()->fetchOne();
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