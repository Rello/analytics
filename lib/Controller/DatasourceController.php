<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Analytics\Datasource\ExternalCsv;
use OCA\Analytics\Datasource\ExternalJson;
use OCA\Analytics\Datasource\Github;
use OCA\Analytics\Datasource\LocalCsv;
use OCA\Analytics\Datasource\LocalSpreadsheet;
use OCA\Analytics\Datasource\LocalJson;
use OCA\Analytics\Datasource\Regex;
use OCP\AppFramework\Controller;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class DatasourceController extends Controller {
	private $logger;
	private $GithubService;
	private $ExternalCsvService;
	private $RegexService;
	private $ExternalJsonService;
	private $LocalJsonService;
	private $LocalCsvService;
	private $LocalSpreadsheetService;
	/** @var IEventDispatcher */
	private $dispatcher;
	private $l10n;
	/** @var IAppConfig */
	protected $appConfig;

	const DATASET_TYPE_GROUP = 0;
	const DATASET_TYPE_LOCAL_CSV = 1;
	const DATASET_TYPE_INTERNAL_DB = 2;
	const DATASET_TYPE_GIT = 3;
	const DATASET_TYPE_EXTERNAL_CSV = 4;
	const DATASET_TYPE_REGEX = 5;
	const DATASET_TYPE_EXTERNAL_JSON = 6;
	const DATASET_TYPE_LOCAL_SPREADSHEET = 7;
	const DATASET_TYPE_LOCAL_JSON = 8;

	public function __construct(
		string           $appName,
		IRequest         $request,
		LoggerInterface  $logger,
		Github           $GithubService,
		LocalCsv         $LocalCsvService,
		Regex            $RegexService,
		ExternalJson     $ExternalJsonService,
		LocalJson        $LocalJsonService,
		ExternalCsv      $ExternalCsvService,
		LocalSpreadsheet $LocalSpreadsheetService,
		IL10N            $l10n,
		IEventDispatcher $dispatcher,
		IAppConfig       $appConfig,
	) {
		parent::__construct($appName, $request);
		$this->logger = $logger;
		$this->ExternalCsvService = $ExternalCsvService;
		$this->GithubService = $GithubService;
		$this->RegexService = $RegexService;
		$this->LocalCsvService = $LocalCsvService;
		$this->ExternalJsonService = $ExternalJsonService;
		$this->LocalJsonService = $LocalJsonService;
		$this->LocalSpreadsheetService = $LocalSpreadsheetService;
		$this->dispatcher = $dispatcher;
		$this->l10n = $l10n;
		$this->appConfig = $appConfig;
	}

	/**
	 * get all data source ids + names
	 *
	 * @NoAdminRequired
	 * @param int|null $datasourceType
	 * @return array
	 */
	public function index(int $datasourceType = null) {
		$result = [];
		$datasourceIndex = $this->getDatasources($datasourceType);

		$datasources = [];
		$options = [];

		foreach ($datasourceIndex as $key => $class) {
			$datasources[$key] = $class->getName();
			$options[$key] = $class->getTemplate();
		}

		$result['datasources'] = $datasources;
		$result['options'] = $options;

		return $result;
	}

	/**
	 * get one data source
	 *
	 * @NoAdminRequired
	 * @param int|null $datasourceType
	 * @return array
	 */
	public function indexFiltered(int $datasourceType = null) {
		return $this->index($datasourceType);
	}

	/**
	 * get all data source templates
	 *
	 * @NoAdminRequired
	 * @return array
	 */
	public function getTemplates() {
		$result = array();
		foreach ($this->getDatasources() as $key => $class) {
			$result[$key] = $class->getTemplate();
		}
		return $result;
	}

	/**
     * Get the data from a data source;
	 *
	 * @NoAdminRequired
	 * @param int $datasourceId
	 * @param $datasetMetadata
	 * @return array|NotFoundException
	 */
	public function read(int $datasourceId, $datasetMetadata) {
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
					}
				}, $result['data']);
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
	 * combine internal and registered data sources
	 * @param int|null $datasourceType
	 * @return array
	 */
	private function getDatasources(int $datasourceType = null) {
		$datasources = $this->getOwnDatasources($datasourceType) + $this->getRegisteredDatasources($datasourceType);

		// Data sources can be disabled globally by their ID
		// occ config:app:set analytics disabledDataSources --type=string --value="1,3,4"
		$disabledString = $this->appConfig->getValueString('analytics', 'disabledDataSources');
		$disabledArray = explode(',', $disabledString);
		$datasources = array_diff_key($datasources, array_flip($disabledArray));

		return $datasources;
	}

	/**
	 * map all internal data sources to their IDs
	 * @param int|null $datasourceType
	 * @return array
	 */
	private function getOwnDatasources(int $datasourceType = null) {
		$dataSources = [];
		$serviceMapping = [
			self::DATASET_TYPE_GIT => $this->GithubService,
			self::DATASET_TYPE_LOCAL_CSV => $this->LocalCsvService,
			self::DATASET_TYPE_LOCAL_SPREADSHEET => $this->LocalSpreadsheetService,
			self::DATASET_TYPE_EXTERNAL_CSV => $this->ExternalCsvService,
			self::DATASET_TYPE_REGEX => $this->RegexService,
			self::DATASET_TYPE_EXTERNAL_JSON => $this->ExternalJsonService,
			self::DATASET_TYPE_LOCAL_JSON => $this->LocalJsonService,
		];

		if ($datasourceType !== null && isset($serviceMapping[$datasourceType])) {
			$dataSources[$datasourceType] = $serviceMapping[$datasourceType];
		} elseif ($datasourceType === null) {
			$dataSources = $serviceMapping;
		}

		return $dataSources;
	}

	/**
	 * map all registered data sources to their IDs
	 * @param int|null $datasourceType
	 * @return array
	 */
	private function getRegisteredDatasources(int $datasourceType = null) {
		$dataSources = [];
		$event = new DatasourceEvent();
		$this->dispatcher->dispatchTyped($event);

		foreach ($event->getDataSources() as $class) {
			try {
				$uniqueId = '99' . \OC::$server->get($class)->getId();

				if ($datasourceType !== null && $datasourceType !== (int)$uniqueId) {
					continue;
				}
				if (isset($dataSources[$uniqueId])) {
					$this->logger->error(new \InvalidArgumentException('Data source with the same ID already registered: ' . \OC::$server->get($class)
																																		 ->getName()));
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
	private function filterData($data, $filter) {
		$options = json_decode($filter, true);
		if (isset($options['filter'])) {
			foreach ($options['filter'] as $key => $value) {
				$filterValue = $value['value'];
				$filterValueNoQuotes = trim($value['value'], "'");
				$filterOption = $value['option'];
				$filtered = array();

				foreach ($data['data'] as $record) {
					if (($filterOption === 'EQ' && $record[$key] === $filterValueNoQuotes) || ($filterOption === 'GT' && $record[$key] > $filterValueNoQuotes) || ($filterOption === 'LT' && $record[$key] < $filterValueNoQuotes) || ($filterOption === 'LIKE' && strpos($record[$key], $filterValueNoQuotes) !== false)) {
						$filtered[] = $record;
					} else if ($filterOption === 'IN') {
						preg_match_all("/'(?:[^'\\\\]|\\\\.)*'|[^,;]+/", $filterValue, $matches);
						$valuesArray = array_map(function($v) {
							return trim($v, " '");
						}, $matches[0]);

						if (in_array($record[$key], $valuesArray)) {
							$filtered[] = $record;
						}
					}
				}
				$data['data'] = $filtered;
			}
		}
		return $data;
	}

	private function aggregateData($data, $filter) {
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

					//$this->logger->info("Aggregating data for key $key and $value");
					if (is_numeric($value)) {
						//$this->logger->info("value is float");
						$aggregatedData[$key] += $value;
					} else {
						//$this->logger->info("value is not float");
						$aggregatedData[$key] = $value;
					}
					//$this->logger->info("aggregatedData: ". json_encode($aggregatedData));

				}
				//$this->logger->info(json_encode($aggregatedData));

				// Convert the associative array to the desired format
				$result = [];
				foreach ($aggregatedData as $aKey => $aValue) {
					// If only the value column remains, append its total value
					if ($aKey === 'xxsingle_valuexx') {
						$aKey = $this->l10n->t('Total');
						// Add an empty column to the header because of the "total" row description
						array_unshift($data['header'], '');
					} else {
						// Split the key into components
						$components = explode('|', $aKey);
						// Combine components with the aggregated value
						$result[] = array_merge($components, [$aValue]);
					}
				}
				$data['data'] = $result;
			}
		}
		return $data;
	}
}