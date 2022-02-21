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

namespace OCA\Analytics\Controller;

use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Analytics\Datasource\Excel;
use OCA\Analytics\Datasource\ExternalFile;
use OCA\Analytics\Datasource\File;
use OCA\Analytics\Datasource\Github;
use OCA\Analytics\Datasource\Json;
use OCA\Analytics\Datasource\Regex;
use OCP\AppFramework\Controller;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DatasourceController extends Controller
{
    private $logger;
    private $GithubService;
    private $FileService;
    private $ExternalFileService;
    private $RegexService;
    private $JsonService;
    private $ExcelService;
    /** @var IEventDispatcher */
    private $dispatcher;
    private $l10n;

    const DATASET_TYPE_GROUP = 0;
    const DATASET_TYPE_FILE = 1;
    const DATASET_TYPE_INTERNAL_DB = 2;
    const DATASET_TYPE_GIT = 3;
    const DATASET_TYPE_EXTERNAL_FILE = 4;
    const DATASET_TYPE_REGEX = 5;
    const DATASET_TYPE_JSON = 6;
    const DATASET_TYPE_EXCEL = 7;

    public function __construct(
        string $AppName,
        IRequest $request,
        LoggerInterface $logger,
        Github $GithubService,
        File $FileService,
        Regex $RegexService,
        Json $JsonService,
        ExternalFile $ExternalFileService,
        Excel $ExcelService,
        IL10N $l10n,
        IEventDispatcher $dispatcher
    )
    {
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->ExternalFileService = $ExternalFileService;
        $this->GithubService = $GithubService;
        $this->RegexService = $RegexService;
        $this->FileService = $FileService;
        $this->JsonService = $JsonService;
        $this->ExcelService = $ExcelService;
        $this->dispatcher = $dispatcher;
        $this->l10n = $l10n;
    }

    /**
     * get all datasource ids + names
     *
     * @NoAdminRequired
     */
    public function index()
    {
        $datasources = array();
        $result = array();
        foreach ($this->getDatasources() as $key => $class) {
            $datasources[$key] = $class->getName();
        }
        $result['datasources'] = $datasources;

        $options = array();
        foreach ($this->getDatasources() as $key => $class) {
            $options[$key] = $class->getTemplate();
        }
        $result['options'] = $options;

        return $result;
    }

    /**
     * get all datasource templates
     *
     * @NoAdminRequired
     * @return array
     */
    public function getTemplates()
    {
        $result = array();
        foreach ($this->getDatasources() as $key => $class) {
            $result[$key] = $class->getTemplate();
        }
        return $result;
    }

    /**
     * Get the data from a datasource;
     *
     * @NoAdminRequired
     * @param int $datasourceId
     * @param $datasetMetadata
     * @return array|NotFoundException
     */
    public function read(int $datasourceId, $datasetMetadata)
    {
        $option = array();
        // before 3.1.0, the options were in another format. as of 3.1.0 the standard option array is used
        if ($datasetMetadata['link'][0] !== '{') {
            $option['link'] = $datasetMetadata['link'];
        } else {
            $option = json_decode($datasetMetadata['link'], true);
        }
        $option['user_id'] = $datasetMetadata['user_id'];

        try {
            // read the data from the source
            $result = $this->getDatasources()[$datasourceId]->readData($option);

            // if datasource should be timestamped/snapshoted
            if (isset($option['timestamp']) and $option['timestamp'] === 'true') {
                $result['data'] = array_map(function ($tag) {
                    $columns = count($tag);
                    return array($tag[$columns - 2], $tag[$columns - 2], $tag[$columns - 1]);
                }, $result['data']);
                date_default_timezone_set('UTC');
                // shift values by one dimension and stores date in second column
                $result['data'] = $this->replaceDimension($result['data'], 1, date("Y-m-d H:i:s") . 'Z');
            }

            // filter result set if required
            if (isset($datasetMetadata['filteroptions']) && strlen($datasetMetadata['filteroptions']) >> 2) {
                $result['data'] = $this->filterData($result['data'], $datasetMetadata['filteroptions']);
            }
        } catch (\Error $e) {
            $result['error'] = $e->getMessage();
        }

        if (empty($result['data'])) {
            $result['status'] = 'nodata';
        }
        return $result;
    }

    /**
     * combine internal and registered datasources
     * @return array
     */
    private function getDatasources()
    {
        return $this->getOwnDatasources() + $this->getRegisteredDatasources();
    }

    /**
     * map all internal datasources to their IDs
     * @return array
     */
    private function getOwnDatasources()
    {
        $datasources = [];
        $datasources[self::DATASET_TYPE_FILE] = $this->FileService;
        $datasources[self::DATASET_TYPE_EXCEL] = $this->ExcelService;
        $datasources[self::DATASET_TYPE_GIT] = $this->GithubService;
        $datasources[self::DATASET_TYPE_EXTERNAL_FILE] = $this->ExternalFileService;
        $datasources[self::DATASET_TYPE_REGEX] = $this->RegexService;
        $datasources[self::DATASET_TYPE_JSON] = $this->JsonService;
        return $datasources;
    }

    /**
     * map all registered datasources to their IDs
     * @return array
     */
    private function getRegisteredDatasources()
    {
        $datasources = [];
        $event = new DatasourceEvent();
        $this->dispatcher->dispatchTyped($event);

        foreach ($event->getDataSources() as $class) {
            $uniqueId = '99' . \OC::$server->get($class)->getId();

            if (isset($datasources[$uniqueId])) {
                $this->logger->error(new \InvalidArgumentException('Datasource with the same ID already registered: ' . \OC::$server->get($class)->getName()));
                continue;
            }
            $datasources[$uniqueId] = \OC::$server->get($class);
        }
        return $datasources;
    }

    /**
     * replace all values of one dimension
     *
     * @NoAdminRequired
     * @param $Array
     * @param $Find
     * @param $Replace
     * @return array
     */
    private function replaceDimension($Array, $Find, $Replace)
    {
        if (is_array($Array)) {
            foreach ($Array as $Key => $Val) {
                if (is_array($Array[$Key])) {
                    $Array[$Key] = $this->replaceDimension($Array[$Key], $Find, $Replace);
                } else {
                    if ($Key === $Find) {
                        $Array[$Key] = $Replace;
                    }
                }
            }
        }
        return $Array;
    }

    /**
     * apply the fiven filters to the hole result set
     *
     * @NoAdminRequired
     * @param $data
     * @param $filter
     * @return array
     */
    private function filterData($data, $filter)
    {
        $options = json_decode($filter, true);
        if (isset($options['filter'])) {

            foreach ($options['filter'] as $key => $value) {

                $filterValue = $value['value'];
                $filterOption = $value['option'];
                $filtered = array();

                foreach ($data as $record) {
                    if (
                        ($filterOption === 'EQ' && $record[$key] === $filterValue)
                        || ($filterOption === 'GT' && $record[$key] > $filterValue)
                        || ($filterOption === 'LT' && $record[$key] < $filterValue)
                        || ($filterOption === 'LIKE' && strpos($record[$key], $filterValue) !== FALSE)
                    ) {
                        array_push($filtered, $record);
                    } else if ($filterOption === 'IN') {
                        $filterValues = explode(',', $filterValue);
                        if (in_array($record[$key], $filterValues)) {
                            array_push($filtered, $record);
                        }
                    }
                }
                $data = $filtered;
            }
        }
        return $data;
    }
}