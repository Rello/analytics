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

use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;

class DatasetMapper
{
    private $userId;
    private $l10n;
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_dataset';

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
     * create dataset
     * @return int
     */
    public function createDataset()
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
            ]);
        $sql->execute();
        return (int)$this->db->lastInsertId(self::TABLE_NAME);
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
     * @param $chartoptions
     * @param $dataoptions
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return bool
     */
    public function updateDataset($id, $name, $subheader, $parent, $type, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value)
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
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->execute();
        return true;
    }

    /**
     * update dataset
     * @param $id
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return bool
     */
    public function updateDatasetOptions($id, $chartoptions, $dataoptions, $filteroptions)
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
     * delete dataset
     * @param $id
     * @return bool
     */
    public function deleteDataset($id)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->delete(self::TABLE_NAME)
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
        $sql->execute();
        return true;
    }

    /**
     * get datasets
     * @return array
     */
    public function getDatasets()
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
     * search datasets by searchstring
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
     * get datasets
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
     * get datasets
     * @param $id
     * @return array
     */
    public function getDatasetOptions($id)
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