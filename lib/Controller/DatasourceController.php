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
use OCA\Analytics\Datasource\IReportTemplateProvider;
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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

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
	 * @param int|null $datasourceType
	 * @return array
	 */
	#[NoAdminRequired]
	public function index(?int $datasourceType = null) {
		$result = [];
		$datasourceIndex = $this->getDatasources($datasourceType);

		$datasources = [];
		$options = [];
		$reportTemplates = [];

		foreach ($datasourceIndex as $key => $class) {
			$datasources[$key] = $class->getName();
			$options[$key] = $class->getTemplate();
			if ($class instanceof IReportTemplateProvider) {
				$reportTemplates[$key] = $class->getReportTemplates();
			} else {
				$reportTemplates[$key] = [];
			}
		}

		$result['datasources'] = $datasources;
		$result['options'] = $options;
		$result['reportTemplates'] = $reportTemplates;

		return $result;
	}

	/**
	 * get one data source
	 *
	 * @param int|null $datasourceType
	 * @return array
	 */
	#[NoAdminRequired]
	public function indexFiltered(?int $datasourceType = null) {
		return $this->index($datasourceType);
	}

	/**
	 * get all data source templates
	 *
	 * @return array
	 */
	#[NoAdminRequired]
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
	 * @param int $datasourceId
	 * @param $datasetMetadata
	 * @param bool $allowCacheValidation
	 * @return array|NotFoundException
	 */
	#[NoAdminRequired]
	public function read(int $datasourceId, $datasetMetadata, bool $allowCacheValidation = true) {
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
		unset($option['cacheKey']);
		$option['user_id'] = $datasetMetadata['user_id'];
		if ($allowCacheValidation && isset($datasetMetadata['cacheKey']) && $datasetMetadata['cacheKey'] !== '') {
			$option['cacheKey'] = $datasetMetadata['cacheKey'];
		}

		try {
			// read the data from the source
			$result = $this->getDatasources()[$datasourceId]->readData($option);
			if (isset($result['cache']['notModified']) && $result['cache']['notModified'] === true) {
				return $result;
			}

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

			$originalFilterOptions = $datasetMetadata['filteroptions'] ?? null;
			$datasetMetadata['filteroptions'] = $this->normalizeFilterOptionsForData(
				$originalFilterOptions,
				$result['header'] ?? []
			);
			if (is_string($originalFilterOptions)
				&& trim($originalFilterOptions) !== ''
				&& $datasetMetadata['filteroptions'] !== $originalFilterOptions) {
				// Return sanitized report options so callers can persist updated settings.
				$result['filteroptions'] = $datasetMetadata['filteroptions'];
			}

			// filter data
			// data sources have their dimension array with index numbers e.g. 0: test
			// not typed like internal storage with e.g. dimension1: test
			// due to this, we first need to filter because aggregation would alter the index numbers
			$result = $this->filterData($result, $datasetMetadata['filteroptions']);
			$result = $this->aggregateData($result, $datasetMetadata['filteroptions']);

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
	private function getDatasources(?int $datasourceType = null) {
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
	private function getOwnDatasources(?int $datasourceType = null) {
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
	private function getRegisteredDatasources(?int $datasourceType = null) {
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
					if (($filterOption === 'EQ' && $record[$key] === $filterValueNoQuotes) || ($filterOption === 'GT' && $record[$key] > $filterValueNoQuotes) || ($filterOption === 'LT' && $record[$key] < $filterValueNoQuotes) || ($filterOption === 'LIKE' && $this->matchesLikeFilter($record[$key], $filterValueNoQuotes)) || ($filterOption === 'NOTLIKE' && !$this->matchesLikeFilter($record[$key], $filterValueNoQuotes))) {
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

	private function matchesLikeFilter($value, string $filterValue): bool {
		$value = (string)$value;
		if (strpbrk($filterValue, '*?') === false) {
			return strpos($value, $filterValue) !== false;
		}

		$pattern = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($filterValue, '/')) . '$/u';
		return preg_match($pattern, $value) === 1;
	}

	private function aggregateData($data, $filter) {
		$options = json_decode($filter, true);
		if (!is_array($options)) {
			$options = [];
		}

		$header = isset($data['header']) && is_array($data['header']) ? $data['header'] : [];
		$dimensionCount = max(count($header) - 1, 0);
		$hiddenDrilldownIndices = $this->getHiddenDrilldownIndices($options, $dimensionCount);
		if (!empty($hiddenDrilldownIndices)) {
			$data = $this->removeColumnsByIndex($data, $hiddenDrilldownIndices);
		}

		if (!$this->shouldAggregate($options)) {
			return $data;
		}

		return $this->aggregateRows($data);
	}

	private function normalizeFilterOptionsForData($filterOptions, array $header): string {
		if (!is_string($filterOptions) || trim($filterOptions) === '') {
			return '{}';
		}

		$options = json_decode($filterOptions, true);
		if (!is_array($options)) {
			return '{}';
		}

		if (isset($options['drilldown']) && is_array($options['drilldown'])) {
			$dimensionCount = max(count($header) - 1, 0);
			$validDrilldown = [];
			foreach ($options['drilldown'] as $index => $hidden) {
				$drilldownIndex = filter_var($index, FILTER_VALIDATE_INT);
				if ($drilldownIndex === false || $drilldownIndex < 0 || $drilldownIndex >= $dimensionCount) {
					continue;
				}
				$validDrilldown[(string)$drilldownIndex] = $hidden;
			}

			if (empty($validDrilldown)) {
				unset($options['drilldown']);
			} else {
				$options['drilldown'] = $validDrilldown;
			}
		}

		return $this->encodeFilterOptions($options);
	}

	private function getHiddenDrilldownIndices(array $options, int $dimensionCount): array {
		if (!isset($options['drilldown']) || !is_array($options['drilldown'])) {
			return [];
		}

		$hiddenIndices = [];
		foreach ($options['drilldown'] as $index => $hidden) {
			$drilldownIndex = filter_var($index, FILTER_VALIDATE_INT);
			if ($drilldownIndex === false || $drilldownIndex < 0 || $drilldownIndex >= $dimensionCount) {
				continue;
			}
			if ($hidden === false || $hidden === 'false' || $hidden === 0 || $hidden === '0') {
				$hiddenIndices[] = $drilldownIndex;
			}
		}

		return $hiddenIndices;
	}

	private function removeColumnsByIndex(array $data, array $indices): array {
		rsort($indices, SORT_NUMERIC);

		foreach ($indices as $removeIndex) {
			if (isset($data['header']) && is_array($data['header'])) {
				unset($data['header'][$removeIndex]);
			}

			if (!isset($data['data']) || !is_array($data['data'])) {
				continue;
			}

			foreach ($data['data'] as $rowIndex => $row) {
				if (!is_array($row)) {
					continue;
				}

				unset($row[$removeIndex]);
				$data['data'][$rowIndex] = array_values($row);
			}
		}

		if (isset($data['header']) && is_array($data['header'])) {
			$data['header'] = array_values($data['header']);
		}

		return $data;
	}

	private function shouldAggregate(array $options): bool {
		if (!array_key_exists('aggregate', $options)) {
			// Default behavior: aggregate unless explicitly disabled.
			return true;
		}

		$value = $options['aggregate'];
		return !($value === false || $value === 'false' || $value === 0 || $value === '0');
	}

	private function aggregateRows(array $data): array {
		if (!isset($data['data']) || !is_array($data['data']) || empty($data['data'])) {
			return $data;
		}

		$aggregatedData = [];

		foreach ($data['data'] as $row) {
			if (!is_array($row) || empty($row)) {
				continue;
			}

			$row = array_values($row);
			$value = array_pop($row);
			$key = empty($row) ? 'xxsingle_valuexx' : implode("|", $row);

			if (!isset($aggregatedData[$key])) {
				$aggregatedData[$key] = 0;
			}

			if (is_numeric($value)) {
				$aggregatedData[$key] += $value;
			} elseif ($value !== null && $value !== '') {
				$aggregatedData[$key] = $value;
			}
		}

		if (empty($aggregatedData)) {
			$data['data'] = [];
			return $data;
		}

		$result = [];
		foreach ($aggregatedData as $aKey => $aValue) {
			if ($aKey === 'xxsingle_valuexx') {
				if (!isset($data['header']) || !is_array($data['header'])) {
					$data['header'] = [''];
				} else {
					array_unshift($data['header'], '');
				}
				$result[] = [$this->l10n->t('Total'), $aValue];
			} else {
				$components = explode('|', $aKey);
				$result[] = array_merge($components, [$aValue]);
			}
		}
		$data['data'] = $result;
		return $data;
	}

	private function encodeFilterOptions(array $options): string {
		if (empty($options)) {
			return '{}';
		}

		$encoded = json_encode($options);
		return $encoded === false ? '{}' : $encoded;
	}
}
