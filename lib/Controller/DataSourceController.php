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

namespace OCA\Analytics\Controller;

use OCA\Analytics\Datasource\ExternalFileService;
use OCA\Analytics\Datasource\FileService;
use OCA\Analytics\Datasource\GithubService;
use OCP\AppFramework\Controller;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use OCP\IRequest;

class DataSourceController extends Controller
{
    private $logger;
    private $GithubService;
    private $FileService;
    private $ExternalFileService;
    private $userId;

    const DATASET_TYPE_GROUP = 0;
    const DATASET_TYPE_INTERNAL_FILE = 1;
    const DATASET_TYPE_INTERNAL_DB = 2;
    const DATASET_TYPE_GIT = 3;
    const DATASET_TYPE_EXTERNAL_FILE = 4;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        GithubService $GithubService,
        FileService $FileService,
        ExternalFileService $ExternalFileService
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->logger = $logger;
        $this->ExternalFileService = $ExternalFileService;
        $this->GithubService = $GithubService;
        $this->FileService = $FileService;
    }

    /**
     * Get the data from a datasource;
     *
     * @NoAdminRequired
     * @param $datasetMetadata
     * @param $options
     * @return array|NotFoundException
     * @throws NotFoundExceptions
     */
    public function read($datasetMetadata, $options = null)
    {
        $datasetMetadata['type'] = (int)$datasetMetadata['type'];
        //$this->logger->error('DataSourceController 65: '. $datasetMetadata['type']);
        if ($datasetMetadata['type'] === self::DATASET_TYPE_INTERNAL_FILE) $result = $this->FileService->read($datasetMetadata, $options);
        elseif ($datasetMetadata['type'] === self::DATASET_TYPE_GIT) $result = $this->GithubService->read($datasetMetadata);
        elseif ($datasetMetadata['type'] === self::DATASET_TYPE_EXTERNAL_FILE) $result = $this->ExternalFileService->read($datasetMetadata);
        else $result = new NotFoundException();

        return $result;
    }
}