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
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
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

	public function __construct(
		$appName,
		IRequest $request,
		LoggerInterface $logger,
		DatasetService $DatasetService,
		ReportService $ReportService,
		StorageService $StorageService,
		StorageMapper $StorageMapper,
		IDateTimeFormatter $IDateTimeFormatter
	) {
		parent::__construct($appName, $request, 'POST');
		$this->logger = $logger;
		$this->DatasetService = $DatasetService;
		$this->ReportService = $ReportService;
		$this->StorageService = $StorageService;
		$this->StorageMapper = $StorageMapper;
		$this->IDateTimeFormatter = $IDateTimeFormatter;
	}

	/**
	 * add data via there database names
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function addData(int $datasetId) {
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
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function addDataV2(int $datasetId) {
		$message = 'No -data- parameter';
		$params = $this->request->getParams();
		$datasetMetadata = $this->DatasetService->readOwn($datasetId);

		$response = $this->deriveMaintenancePossible($datasetMetadata);
		if ($response !== true) return $response;

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

			$this->StorageService->update($datasetId, $dimension1, $dimension2, $value);

			// Update the Context Chat backend
			$this->DatasetService->provider($datasetId);

			$message = 'Data update successful';
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
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function deleteDataV2(int $datasetId) {
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
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function dataGetV3(int $reportId) {
		$params = $this->request->getParams();
		$reportMetadata = $this->ReportService->read($reportId);

		if (!empty($reportMetadata)) {
			$options = json_decode($reportMetadata['filteroptions'], true);
			$allData = $this->StorageMapper->read((int)$reportMetadata['dataset'], $options);

			return new DataResponse($allData, HTTP::STATUS_OK);
		} else {
			return new DataResponse([
				'message' => 'No data available for given report id',
			], HTTP::STATUS_OK);
		}
	}

	/**
	 * delete data
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function dataDeleteV3(int $datasetId) {
		return $this->deleteDataV2($datasetId);
	}

	/**
	 * add data via there real field names
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function dataAddV3(int $datasetId) {
		return $this->addDataV2($datasetId);
	}

	/**
	 * list datasets
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @return array
	 * @throws \Exception
	 */
	public function datasetIndexV3() {
		return $this->DatasetService->index();
	}

	/**
	 * list reports
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @return array
	 * @throws \Exception
	 */
	public function reportIndexV3() {
		return $this->ReportService->index();
	}

	/**
	 * read data of a dataset with additional information for table and series
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @return DataResponse
	 * @throws \Exception
	 */
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
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @param int $datasetId
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function addDataV4(int $datasetId) {
		$message = 'No -data- parameter';
		$params = $this->request->getParams();
		$datasetMetadata = $this->DatasetService->readOwn($datasetId);

		$response = $this->deriveMaintenancePossible($datasetMetadata);
		if ($response !== true) return $response;

		foreach ($params['data'] as $dataArray) {

			$dimension1 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension1');
			$dimension2 = $this->deriveParameterNames($dataArray, $datasetMetadata, 'dimension2');
			$value = $this->deriveParameterNames($dataArray, $datasetMetadata, 'value');

			if (!empty($this->errors)) {
				return $this->requestResponse(false, self::MISSING_PARAM, implode(',', $this->errors));
			}

			$this->StorageService->update($datasetId, $dimension1, $dimension2, $value);

			// Update the Context Chat backend
			$this->DatasetService->provider($datasetId);

			$message = 'Data update successful';
		}

		return $this->requestResponse(true, Http::STATUS_OK, $message);
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
	// curl -u admin:pR9Pc-KifMn-WdXmx-Bg4sG-F2ngL -d '{"data":[{"dimension1": "%currentDate%", "dimension2": "%currentTime%", "value": "2"}]}' -X POST -H "Content-Type: application/json" http://localhost:8031/apps/analytics/api/4.0/data/22/add
}
