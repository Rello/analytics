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

namespace OCA\data\Service;

use OCA\data\Controller\DbController;
use OCP\Files\IRootFolder;
use OCP\ILogger;

class FileService
{
    private $logger;
    private $DBController;
    private $rootFolder;

    public function __construct(
        ILogger $logger,
        IRootFolder $rootFolder,
        DbController $DBController
    )
    {
        $this->logger = $logger;
        $this->DBController = $DBController;
        $this->rootFolder = $rootFolder;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param $datasetMetadata
     * @return array
     */
    public function read($datasetMetadata)
    {
        $this->logger->error('dataset path: ' . $datasetMetadata['link']);
        $file = $this->rootFolder->getUserFolder($datasetMetadata['user_id'])->get($datasetMetadata['link']);
        $data = $file->getContent();
        return ['header' => '', 'data' => $data];
    }
}