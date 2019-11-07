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

namespace OCA\Analytics\Datasource;

use OCA\Analytics\Controller\DbController;
use OCA\Analytics\Service\DataService;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;

class FileService
{
    private $logger;
    private $DBController;
    private $rootFolder;
    private $DataService;
    private $userId;

    public function __construct(
        $userId,
        ILogger $logger,
        IRootFolder $rootFolder,
        DbController $DBController,
        DataService $DataService
    )
    {
        $this->userId = $userId;
        $this->DataService = $DataService;
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
     * @throws NotFoundException
     */
    public function read($datasetMetadata)
    {
        //$this->logger->error('dataset path: ' . $datasetMetadata['link']);
        $file = $this->rootFolder->getUserFolder($datasetMetadata['user_id'])->get($datasetMetadata['link']);
        $data = $file->getContent();
        return ['header' => '', 'data' => $data];
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $path
     * @return array
     * @throws NotFoundException
     */
    public function import(int $datasetId, $path)
    {
        $file = $this->rootFolder->getUserFolder($this->userId)->get($path);
        $import = $file->getContent();
        return $this->DataService->import($datasetId, $import);
    }

}