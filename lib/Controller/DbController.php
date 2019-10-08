<?php
/**
 * Audio Player Editor
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IDbConnection;
use OCP\Share\IManager;
use OCP\ILogger;
use OCP\ITagManager;

/**
 * Controller class for main page.
 */
class DbController extends Controller
{

    private $userId;
    private $l10n;
    private $db;
    private $shareManager;
    private $tagManager;
    private $logger;

    public function __construct(
        $appName,
        IRequest $request,
        $userId,
        IL10N $l10n,
        IDbConnection $db,
        ITagManager $tagManager,
        IManager $shareManager,
        ILogger $logger
    )
    {
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->db = $db;
        $this->shareManager = $shareManager;
        $this->tagManager = $tagManager;
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
     * Add artist to db if not exist
     * @param int $userId
     * @param string $sArtist
     * @return int
     */
    public function writeXxxToDB($userId, $sArtist)
    {
        $sArtist = $this->truncate($sArtist, '256');

        $stmt = $this->db->prepare('SELECT `id` FROM `*PREFIX*audioplayer_artists` WHERE `user_id` = ? AND `name` = ?');
        $stmt->execute(array($userId, $sArtist));
        $row = $stmt->fetch();
        if ($row) {
            return $row['id'];
        } else {
            $stmt = $this->db->prepare('INSERT INTO `*PREFIX*audioplayer_artists` (`user_id`,`name`) VALUES(?,?)');
            $stmt->execute(array($userId, $sArtist));
            $insertid = $this->db->lastInsertId('*PREFIX*audioplayer_artists');
            return $insertid;
        }
    }

    /**
     * Get file id for single track
     * @param int $dataset
     * @param  $objectDrilldown
     * @param  $dateDrilldown
     * @return array
     */
    public function getDataset(int $dataset, $objectDrilldown = null, $dateDrilldown = null)
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

        $this->logger->error($SQL);

        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($this->userId, $dataset));
        $results = $stmt->fetchAll();
        return $results;
    }

}
