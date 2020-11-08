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

use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Analytics\Datasource\ExternalFileService;
use OCA\Analytics\Datasource\FileService;
use OCA\Analytics\Datasource\GithubService;
use OCA\Analytics\Datasource\JsonService;
use OCA\Analytics\Datasource\RegexService;
use OCP\AppFramework\Controller;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\NotFoundException;
use OCP\IL10N;
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
    /** @var IEventDispatcher */
    protected $dispatcher;
    private $l10n;

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
        ExternalFileService $ExternalFileService,
        IL10N $l10n,
        IEventDispatcher $dispatcher
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
        $this->dispatcher = $dispatcher;
        $this->l10n = $l10n;
    }

    /**
     * get all datasets
     *
     * @NoAdminRequired
     */
    public function index()
    {
        //$result[self::DATASET_TYPE_GROUP] = $this->l10n->t('No Data / Group');
        //$result[self::DATASET_TYPE_INTERNAL_DB] = $this->l10n->t('Internal Database');
        $result[self::DATASET_TYPE_INTERNAL_FILE] = $this->FileService->getName();
        $result[self::DATASET_TYPE_GIT] = $this->GithubService->getName();
        $result[self::DATASET_TYPE_EXTERNAL_FILE] = $this->ExternalFileService->getName();
        $result[self::DATASET_TYPE_REGEX] = $this->RegexService->getName();
        $result[self::DATASET_TYPE_JSON] = $this->JsonService->getName();

        $result = $result + $this->getRegisteredDatasourceNames();
        return $result;
    }

    /**
     * template for options & settings
     *
     * @NoAdminRequired
     * @return array
     */
    public function getTemplates()
    {
        $result[self::DATASET_TYPE_INTERNAL_FILE] = $this->FileService->getTemplate();
        $result[self::DATASET_TYPE_GIT] = $this->GithubService->getTemplate();
        $result[self::DATASET_TYPE_EXTERNAL_FILE] = $this->ExternalFileService->getTemplate();
        $result[self::DATASET_TYPE_REGEX] = $this->RegexService->getTemplate();
        $result[self::DATASET_TYPE_JSON] = $this->JsonService->getTemplate();
        $result = $result + $this->getRegisteredDatasourceTemplates();
        return $result;
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

    private function getRegisteredDatasourceTemplates()
    {
        $event = new DatasourceEvent();
        $this->dispatcher->dispatchTyped($event);

        $datasources = [];
        foreach ($event->getDataSources() as $class) {
            $uniqueId = $this->uniqueId(\OC::$server->get($class)->getId());

            if (isset($datasources[$uniqueId])) {
                $this->logger->logException(new \InvalidArgumentException('Datasource with the same ID already registered: ' . \OC::$server->get($class)->getName()), ['level' => ILogger::INFO]);
                continue;
            }

            $datasources[$uniqueId] = \OC::$server->get($class)->getTemplate();
        }
        return $datasources;
    }

    private function getRegisteredDatasourceNames()
    {
        $event = new DatasourceEvent();
        $this->dispatcher->dispatchTyped($event);

        $datasources = [];
        foreach ($event->getDataSources() as $class) {
            $uniqueId = $this->uniqueId(\OC::$server->get($class)->getId());
            $datasources[$uniqueId] = \OC::$server->get($class)->getName();
        }
        return $datasources;
    }

    private function uniqueId($id)
    {
        return '99' . $id;
    }
}