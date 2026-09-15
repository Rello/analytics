<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\StorageService;
use OCA\Analytics\Service\VariableService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\Constants;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use OCP\IDateTimeFormatter;

class ApiDataController extends ApiController {
	const UNKNOWN = 9001;
	const MISSING_PARAM = 9002;
	const NOT_FOUND = 9003;
	const NOT_ALLOWED = 9004;

	protected $errors = [];
	private $logger;
	private $DatasetService;
	private $ReportService;
	private $StorageService;
	private $StorageMapper;
	private $IDateTimeFormatter;
	private VariableService $VariableService;

	public function __construct(
		$appName,
		IRequest $request,
		LoggerInterface $logger,
		DatasetService $DatasetService,
		ReportService $ReportService,
		StorageService $StorageService,
		StorageMapper $StorageMapper,
		IDateTimeFormatter $IDateTimeFormatter,
		VariableService $VariableService
	) {
		parent::__construct($appName, $request, 'POST');
		$this->logger = $logger;
		$this->DatasetService = $DatasetService;
		$this->ReportService = $ReportService;
		$this->StorageService = $StorageService;
		$this->StorageMapper = $StorageMapper;
		$this->IDateTimeFormatter = $IDateTimeFormatter;
		$this->VariableService = $VariableService;
	}

	/**
	 * add data via there database names
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function addData(int $datasetId) {
		$datasetId = $this->getDatasetIdFromRoute($datasetId);
		$params = $this->request->getParams();
		$datasetMetadata = $this->DatasetService->readOwn($datasetId);

		$response = $this->deriveMaintenancePossible($datasetMetadata);
		if ($response !== true) return $response;

		if (!isset($params['dimension1'])) {
			$this->errors[] = 'Dimension 1 required';
		} elseif (!isset($params['dimension2'])) {
			$this->errors[] = 'Dimension 2 required';
		} elseif (!isset($params['dimension3'])) {
			$this->errors[] = 'Dimension 3 required';
		}
		if (!empty($this->errors)) {
			return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
		}

		$this->StorageService->update($datasetId, $params['dimension1'], $params['dimension2'], $params['dimension3']);

		// Update the Context Chat backend
		$this->DatasetService->provider($datasetId);

		return $this->requestResponse(true, Http::STATUS_OK, 'Data update successful');
	}

	/**
	 * add data via there real field names
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function addDataV2(int $datasetId) {
		$datasetId = $this->getDatasetIdFromRoute($datasetId);
		$message = 'No -data- parameter';
		$params = $this->request->getParams();
		$datasetMetadata = $this->DatasetService->readOwn($datasetId);

		$response = $this->deriveMaintenancePossible($datasetMetadata);
		if ($response !== true) return $response;

		$insert = $update = 0;
		foreach ($params['data'] as $dataArray) {

			$dimension1 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension1');
			$dimension2 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension2');
			$value = $this->deriveParameterNames($dataArray, $datasetMetadata, 'value');

			if (!empty($this->errors)) {
				return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
			}

			// replace text variables like %now%
			$dimension1 = $this->replaceTextVariablesSingle($dimension1);
			$dimension2 = $this->replaceTextVariablesSingle($dimension2);

			$action = $this->StorageService->update($datasetId, $dimension1, $dimension2, $value, null, null, null, false);
			$insert = $insert + $action['insert'];
			$update = $update + $action['update'];

			$message = 'Data update successful';
		}
		if ($insert > 0 || $update > 0) {
			$this->ReportService->increaseVersionByDataset($datasetId);
			$this->DatasetService->provider($datasetId);
		}

		return $this->requestResponse(true, Http::STATUS_OK, $message);
	}

	/**
	 * from V3 to V4, the data format for %currentDate% has changed.
	 * before it was local format - this needs to be kept backwards compatible
	 * as of V4, the ISO "Y-m-d" will be used
	 * %currentDate% will be parsed here - from there, the default logic in the storageController applies
	 * @param $field
	 * @return array|mixed|string|string[]|null
	 */
	private function replaceTextVariablesSingle($field) {
		if ($field !== null) {
			preg_match_all("/%.*?%/", $field, $matches);
			if (count($matches[0]) > 0) {
				foreach ($matches[0] as $match) {
					$replace = null;
					if ($match === '%currentDate%') {
						$replace = $this->IDateTimeFormatter->formatDate(time(), 'short');
					}
					if ($replace !== null) {
						$field = preg_replace('/' . $match . '/', $replace, $field);
					}
				}
			}
		}
		return $field;
	}

	/**
	 * delete data
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function deleteDataV2(int $datasetId) {
		$datasetId = $this->getDatasetIdFromRoute($datasetId);
		$message = 'No -delete- parameter';
		$params = $this->request->getParams();
		$datasetMetadata = $this->DatasetService->readOwn($datasetId);

		$response = $this->deriveMaintenancePossible($datasetMetadata);
		if ($response !== true) return $response;

		foreach ($params['delete'] as $dataArray) {
			$dimension1 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension1');
			$dimension2 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension2');

			if (!empty($this->errors)) {
				return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
			}

			$this->StorageService->delete($datasetId, $dimension1, $dimension2);
			$message = 'Data deleted';
		}

		return $this->requestResponse(true, Http::STATUS_OK, $message);
	}


	///
	/// API V3
	///

	/**
	 * get all data of a report and respect filter options
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function dataGetV3(int $reportId) {
		$params = $this->request->getParams();
		$reportMetadata = $this->ReportService->read($reportId);

		if (!empty($reportMetadata)) {
			$options = json_decode($reportMetadata['filteroptions'], true);
				$allData = $this->StorageService->read((int)$reportMetadata['dataset'], $reportMetadata);
				return new DataResponse(
					($allData['storageMode'] ?? 'legacy') === 'flexible_shared' ? $allData : ($allData['data'] ?? []),
					HTTP::STATUS_OK
				);
		} else {
			return new DataResponse([
				'message' => 'No data available for given report id',
			], HTTP::STATUS_OK);
		}
	}

	/**
	 * delete data
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function dataDeleteV3(int $datasetId) {
		return $this->deleteDataV2($datasetId);
	}

	/**
	 * add data via there real field names
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function dataAddV3(int $datasetId) {
		return $this->addDataV2($datasetId);
	}

	/**
	 * list datasets
	 * @return array
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function datasetIndexV3() {
		return $this->DatasetService->index();
	}

	/**
	 * list reports
	 * @return array
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function reportIndexV3() {
		return $this->ReportService->index();
	}

	/**
	 * read data of a dataset with additional information for table and series
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function reportDetailV3(int $reportId) {
		$reportMetadata = $this->ReportService->read($reportId);
		unset($reportMetadata['user_id'], $reportMetadata['link'], $reportMetadata['permissions'], $reportMetadata['dimension3']);

		if (!empty($reportMetadata)) {
			return new DataResponse($reportMetadata, HTTP::STATUS_OK);
		} else {
			return new DataResponse([
				'message' => 'No metadata available for given $reportId',
			], HTTP::STATUS_OK);
		}
	}

	/**
	 * add data via there real field names
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function addDataV4(int $datasetId) {
		$datasetId = $this->getDatasetIdFromRoute($datasetId);
		$message = 'No -data- parameter';
		$params = $this->request->getParams();
		$datasetMetadata = $this->DatasetService->readOwn($datasetId);

		$response = $this->deriveMaintenancePossible($datasetMetadata);
		if ($response !== true) return $response;

		$insert = $update = 0;
		foreach ($params['data'] as $dataArray) {

			$dimension1 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension1');
			$dimension2 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension2');
			$value = $this->deriveParameterNames($dataArray, $datasetMetadata, 'value');

			if (!empty($this->errors)) {
				return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
			}

			$action = $this->StorageService->update($datasetId, $dimension1, $dimension2, $value, null, null, null, false);
			$insert = $insert + $action['insert'];
			$update = $update + $action['update'];

			$message = 'Data update successful';
		}
		if ($insert > 0 || $update > 0) {
			$this->ReportService->increaseVersionByDataset($datasetId);
			$this->DatasetService->provider($datasetId);
		}

		return $this->requestResponse(true, Http::STATUS_OK, $message);
	}

	/**
	 * Delete legacy dataset rows selected by a structured filter.
	 *
	 * The filter uses the same option and value syntax as a report filter. Date
	 * variables such as %last 5 days% are resolved when the request runs.
	 *
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	public function deleteDataV4(int $datasetId): DataResponse {
		$datasetId = $this->getDatasetIdFromRoute($datasetId);
		$params = $this->request->getParams();
		$datasetMetadata = $this->DatasetService->readOwn($datasetId);

		$response = $this->deriveMaintenancePossible($datasetMetadata);
		if ($response !== true) {
			return $response;
		}

		$filter = $this->normalizeDeletionFilter($params['filter'] ?? null, $datasetMetadata);
		if ($filter === null) {
			return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
		}

		$filterMetadata = $this->VariableService->replaceFilterVariables([
			'filteroptions' => json_encode(['filter' => $filter], JSON_THROW_ON_ERROR),
		]);
		$resolvedFilter = json_decode($filterMetadata['filteroptions'], true, 512, JSON_THROW_ON_ERROR);
		$deleted = $this->StorageService->deleteWithFilter($datasetId, $resolvedFilter);
		$this->DatasetService->provider($datasetId);

		return new DataResponse([
			'success' => true,
			'message' => 'Data deleted',
			'delete' => $deleted,
		], Http::STATUS_OK);
	}

	/**
	 * @param mixed $filter
	 * @param array<string, mixed> $datasetMetadata
	 * @return array<string, array{option:string, value:string}>|null
	 */
	private function normalizeDeletionFilter($filter, array $datasetMetadata): ?array {
		if (!is_array($filter) || $filter === []) {
			$this->errors[] = 'filter required';
			return null;
		}

		$columnNames = [
			'dimension1' => (string)$datasetMetadata['dimension1'],
			'dimension2' => (string)$datasetMetadata['dimension2'],
		];
		$allowedOptions = ['EQ', 'GT', 'LT', 'IN', 'LIKE', 'NOTLIKE', 'BETWEEN'];
		$normalized = [];

		foreach ($filter as $column => $condition) {
			$technicalColumn = array_key_exists($column, $columnNames)
				? $column
				: array_search($column, $columnNames, true);
			if ($technicalColumn === false || !is_array($condition)) {
				$this->errors[] = 'valid filter required';
				return null;
			}

			$option = $condition['option'] ?? null;
			$value = $condition['value'] ?? null;
			if (!is_string($option) || !in_array($option, $allowedOptions, true) || !is_string($value)) {
				$this->errors[] = 'valid filter required';
				return null;
			}

			$normalized[$technicalColumn] = [
				'option' => $option,
				'value' => $value,
			];
		}

		return $normalized;
	}

	/**
	 * JSON request bodies can overwrite controller arguments after route matching.
	 * Always use the dataset id captured from the endpoint URL when available.
	 */
	private function getDatasetIdFromRoute(int $datasetId): int {
		return isset($this->request->urlParams['datasetId'])
			? (int)$this->request->urlParams['datasetId']
			: $datasetId;
	}

	/**
	 * derive if the parameter is technical or the free text description from the report
	 * @param $data
	 * @param $datasetMetadata
	 * @param $dimension
	 * @return array | bool
	 */
	protected function deriveParameterNames($data, $datasetMetadata, $dimension) {
		if (isset($data[$dimension])) {
			return $data[$dimension];
		} elseif (isset($data[$datasetMetadata[$dimension]])) {
			return $data[$datasetMetadata[$dimension]];
		} else {
			$this->errors[] = $dimension . ' required';
			return false;
		}
	}

	/**
	 * derive if maintenance is possible
	 * @param $datasetMetadata
	 * @return bool|DataResponse
	 */
	protected function deriveMaintenancePossible($datasetMetadata) {
		if (empty($datasetMetadata)) {
			$this->errors[] = 'Unknown or unauthorized report or dataset';
			return $this->requestResponse(false, self::NOT_FOUND, implode(',', $this->errors));
		} elseif (($datasetMetadata['storageMode'] ?? 'legacy') !== 'legacy') {
			$this->errors[] = 'Flexible datasets must use the stable-column record API';
			return $this->requestResponse(false, self::NOT_ALLOWED, implode(',', $this->errors));
		} else {
			return true;
		}
	}

	/**
	 * @param bool $success
	 * @param int|null $code
	 * @param string|null $message
	 * @return DataResponse
	 */
	protected function requestResponse($success, $code = null, $message = null) {
		if (!$success) {
			if ($code === null) {
				$code = self::UNKNOWN;
			}
			$array = [
				'success' => false,
				'error' => [
					'code' => $code,
					'message' => $message
				]
			];
		} else {
			$array = [
				'success' => true,
				'message' => $message
			];
		}
		$response = new DataResponse();
		$response->setData($array)->render();
		return $response;
	}
	// curl -u USER:APP_PASSWORD -d '{"data":[{"dimension1": "%currentDate%", "dimension2": "%currentTime%", "value": "2"}]}' -X POST -H "Content-Type: application/json" https://cloud.example/apps/analytics/api/4.0/data/DATASET_ID/add
}
