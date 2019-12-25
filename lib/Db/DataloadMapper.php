<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Db;

use OCP\IDbConnection;
use OCP\IL10N;
use OCP\ILogger;

class DataloadMapper
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
     * get Dataload by id
     * @param int $dataloadId
     * @return array
     */
    public function getDataloadById(int $dataloadId)
    {
        $SQL = 'SELECT * FROM `*PREFIX*analytics_dataload` WHERE `id` = ?';
        $stmt = $this->db->prepare($SQL);
        $stmt->execute(array($dataloadId));
        return $stmt->fetch();
    }

}
