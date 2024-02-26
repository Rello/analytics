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
        string           $appName,
        IRequest         $request,
        LoggerInterface  $logger,
        Github           $GithubService,
        File             $FileService,
        Regex            $RegexService,
        Json             $JsonService,
        ExternalFile     $ExternalFileService,
        Excel            $ExcelService,
        IL10N            $l10n,
        IEventDispatcher $dispatcher
    )
    {
        parent::__construct($appName, $request);
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
     * get all data source ids + names
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
     * get all data source templates
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
        if (!$this->getDatasources()[$datasourceId]) {
            $result['error'] = $this->l10n->t('Data source not available anymore');
            return $result;
        }

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

            // if data source should be timestamped/snapshoted
            if (isset($option['timestamp']) and $option['timestamp'] === 'true') {
                date_default_timezone_set('UTC');
                $result['data'] = array_map(function ($tag) {
                    $columns = count($tag);
                    if ($columns > 1) {
                        // shift values by one dimension and stores date in second column
                        return array($tag[$columns - 2], date("Y-m-d H:i:s") . 'Z', $tag[$columns - 1]);
                    } else {
                        // just return 2 columns if the original data only has one column
                        return array(date("Y-m-d H:i:s") . 'Z', $tag[$columns - 1]);
                    }}, $result['data']);
            }

            if (isset($datasetMetadata['filteroptions']) && strlen($datasetMetadata['filteroptions']) >> 2) {
                // filter data
                $result = $this->filterData($result, $datasetMetadata['filteroptions']);
                // remove columns and aggregate data
                $result = $this->aggregateData($result, $datasetMetadata['filteroptions']);
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
     * map all internal data sources to their IDs
     * @return array
     */
    private function getOwnDatasources()
    {
        $dataSources = [];
        $dataSources[self::DATASET_TYPE_FILE] = $this->FileService;
        $dataSources[self::DATASET_TYPE_EXCEL] = $this->ExcelService;
        $dataSources[self::DATASET_TYPE_GIT] = $this->GithubService;
        $dataSources[self::DATASET_TYPE_EXTERNAL_FILE] = $this->ExternalFileService;
        $dataSources[self::DATASET_TYPE_REGEX] = $this->RegexService;
        $dataSources[self::DATASET_TYPE_JSON] = $this->JsonService;
        return $dataSources;
    }

    /**
     * map all registered data sources to their IDs
     * @return array
     */
    private function getRegisteredDatasources()
    {
        $dataSources = [];
        $event = new DatasourceEvent();
        $this->dispatcher->dispatchTyped($event);

        foreach ($event->getDataSources() as $class) {
            try {
                $uniqueId = '99' . \OC::$server->get($class)->getId();

                if (isset($dataSources[$uniqueId])) {
                    $this->logger->error(new \InvalidArgumentException('Data source with the same ID already registered: ' . \OC::$server->get($class)->getName()));
                    continue;
                }
                $dataSources[$uniqueId] = \OC::$server->get($class);
            } catch (\Error $e) {
                $this->logger->error('Can not initialize data source: ' . json_encode($class));
                $this->logger->error($e->getMessage());
            }
        }
        return $dataSources;
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

                foreach ($data['data'] as $record) {
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
                $data['data'] = $filtered;
            }
        }
        return $data;
    }

    private function aggregateData($data, $filter)
    {
        $options = json_decode($filter, true);
        if (isset($options['drilldown'])) {
            // Sort the indices in descending order
            $sortedIndices = array_keys($options['drilldown']);
            rsort($sortedIndices);

            foreach ($sortedIndices as $removeIndex) {
                $aggregatedData = [];

                // remove the header of the column which is not needed
                unset($data['header'][$removeIndex]);
                $data['header'] = array_values($data['header']);

                // remove the column of the data
                foreach ($data['data'] as $row) {
                    // Remove the desired column by its index
                    unset($row[$removeIndex]);

                    // The last column is assumed to always be the value
                    $value = array_pop($row);

                    // If there are no columns left except the value column, insert a dummy
                    if (empty($row)) {
                        $key = 'xxsingle_valuexx';
                    } else {
                        // Use remaining columns as key
                        $key = implode("|", $row);
                    }

                    if (!isset($aggregatedData[$key])) {
                        $aggregatedData[$key] = 0;
                    }
                    $aggregatedData[$key] += $value;
                }

                // Convert the associative array to the desired format
                $result = [];
                foreach ($aggregatedData as $aKey => $aValue) {
                    // If only the value column remains, append its total value
                    if ($aKey === 'xxsingle_valuexx') {
                        $aKey = $this->l10n->t('Total');
                        // Add an empty column to the header because of the "total" row description
                        array_unshift($data['header'], '');
                    }
                    $result[] = [$aKey, $aValue];
                }
                $data['data'] = $result;
            }
        }
        return $data;
    }
}