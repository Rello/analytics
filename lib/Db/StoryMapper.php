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

class StoryMapper
{
    private $userId;
    private $l10n;
    private $db;
    private $logger;
    const TABLE_NAME = 'analytics_story';

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
            ->select('*')
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('page', $sql->createNamedParameter('0')))
            ->orderBy('name', 'ASC');
        $statement = $sql->executeQuery();
        $result = $statement->fetchAll();
        $statement->closeCursor();

        return $result;
    }

    /**
     * create report
     * @param $name
     * @param $subheader
     * @param $type
     * @param $page
     * @param $parent
     * @param $reports
     * @param $layout
     * @return int
     * @throws Exception
     */
    public function create($name, $subheader, $type, $page, $parent, $reports, $layout)
    {
        $sql = $this->db->getQueryBuilder();
        $sql->insert(self::TABLE_NAME)
            ->values([
                'user_id' => $sql->createNamedParameter($this->userId),
                'name' => $sql->createNamedParameter($name),
                'subheader' => $sql->createNamedParameter($subheader),
                'type' => $sql->createNamedParameter($type),
                'page' => $sql->createNamedParameter($page),
                'parent' => $sql->createNamedParameter($parent),
                'reports' => $sql->createNamedParameter($reports),
                'layout' => $sql->createNamedParameter($layout),
            ]);
        $sql->executeStatement();
        return (int)$sql->getLastInsertId();
    }

    /**
     * get single story including subpages for user
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
            ->orWhere($sql->expr()->eq('parent', $sql->createNamedParameter($id)))
            ->andWhere($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->orderBy('page', 'ASC');
        $statement = $sql->executeQuery();
        $result = $statement->fetch();
        $statement->closeCursor();

        return $result;
    }

    /**
     * update report
     * @param $id
     * @param $name
     * @param $subheader
     * @param $type
     * @param $page
     * @param $parent
     * @param $reports
     * @param $layout
     * @return bool
     * @throws Exception
     */
    public function update($id, $name, $subheader, $type, $page, $parent, $reports, $layout)
    {
        $name = $this->truncate($name, 64);
        $sql = $this->db->getQueryBuilder();
        $sql->update(self::TABLE_NAME)
            ->set('name', $sql->createNamedParameter($name))
            ->set('subheader', $sql->createNamedParameter($subheader))
            ->set('type', $sql->createNamedParameter($type))
            ->set('page', $sql->createNamedParameter($page))
            ->set('parent', $sql->createNamedParameter($parent))
            ->set('reports', $sql->createNamedParameter($reports))
            ->set('layout', $sql->createNamedParameter($layout))
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->eq('id', $sql->createNamedParameter($id)));
         $sql->executeStatement();
        return true;
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
            ->where($sql->expr()->eq('user_id', $sql->createNamedParameter($this->userId)))
            ->andWhere($sql->expr()->iLike('name', $sql->createNamedParameter('%' . $this->db->escapeLikeParameter($searchString) . '%')))
            ->orderBy('name', 'ASC');
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