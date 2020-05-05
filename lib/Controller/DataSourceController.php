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

namespace OCA\Analytics\Controller;

use OCA\Analytics\Datasource\ExternalFileService;
use OCA\Analytics\Datasource\FileService;
use OCA\Analytics\Datasource\GithubService;
use OCA\Analytics\Datasource\JsonService;
use OCA\Analytics\Datasource\RegexService;
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
    private $RegexService;
    private $JsonService;
    private $userId;

    const DATASET_TYPE_GROUP = 0;
    const DATASET_TYPE_INTERNAL_FILE = 1;
    const DATASET_TYPE_INTERNAL_DB = 2;
    const DATASET_TYPE_GIT = 3;
    const DATASET_TYPE_EXTERNAL_FILE = 4;
    const DATASET_TYPE_REGEX = 5;
    const DATASET_TYPE_JSON = 6;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        GithubService $GithubService,
        FileService $FileService,
        RegexService $RegexService,
        JsonService $JsonService,
        ExternalFileService $ExternalFileService
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->logger = $logger;
        $this->ExternalFileService = $ExternalFileService;
        $this->GithubService = $GithubService;
        $this->RegexService = $RegexService;
        $this->FileService = $FileService;
        $this->JsonService = $JsonService;
    }

    /**
     * Get the data from a datasource;
     *
     * @NoAdminRequired
     * @param int $datasource
     * @param $option
     * @return array|NotFoundException
     * @throws NotFoundException
     */
    public function read(int $datasource, $option)
    {
        //$this->logger->debug('DataSourceController 66: Datasource Id: ' . $datasource . ', Option: ' . json_encode($option));
        if ($datasource === self::DATASET_TYPE_INTERNAL_FILE) $result = $this->FileService->read($option);
        elseif ($datasource === self::DATASET_TYPE_GIT) $result = $this->GithubService->read($option);
        elseif ($datasource === self::DATASET_TYPE_EXTERNAL_FILE) $result = $this->ExternalFileService->read($option);
        elseif ($datasource === self::DATASET_TYPE_REGEX) $result = $this->RegexService->read($option);
        elseif ($datasource === self::DATASET_TYPE_JSON) $result = $this->JsonService->read($option);
        else $result = new NotFoundException();

        return $result;
    }

    /**
     * template for options & settings
     *
     * @NoAdminRequired
     * @param int $datasource
     * @return array
     */
    public function getTemplates()
    {
        $result[self::DATASET_TYPE_INTERNAL_FILE] = $this->FileService->getTemplate();
        $result[self::DATASET_TYPE_GIT] = $this->GithubService->getTemplate();
        $result[self::DATASET_TYPE_EXTERNAL_FILE] = $this->ExternalFileService->getTemplate();
        $result[self::DATASET_TYPE_REGEX] = $this->RegexService->getTemplate();
        $result[self::DATASET_TYPE_JSON] = $this->JsonService->getTemplate();
        return $result;
    }
}