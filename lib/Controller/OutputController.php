<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\DataSession;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Service\ThresholdService;
use OCA\Analytics\Service\StorageService;
use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Service\PanoramaService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class OutputController extends Controller {
	private $userId;
	private $logger;
	private $ReportService;
	private $DataSession;
	private $ShareService;
	private $DatasourceController;
	private $StorageService;
	private $ThresholdService;
	private $VariableService;
	private $PanoramaService;

	public function __construct(
		$userId,
		string $appName,
		IRequest $request,
		LoggerInterface $logger,
		ReportService $ReportService,
		ShareService $ShareService,
		DataSession $DataSession,
		DatasourceController $DatasourceController,
		StorageService $StorageService,
		ThresholdService $ThresholdService,
		VariableService $VariableService,
		PanoramaService $PanoramaService
	) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->ReportService = $ReportService;
		$this->DataSession = $DataSession;
		$this->ShareService = $ShareService;
		$this->DatasourceController = $DatasourceController;
		$this->StorageService = $StorageService;
		$this->ThresholdService = $ThresholdService;
		$this->VariableService = $VariableService;
		$this->PanoramaService = $PanoramaService;
	}

	/**
	 * get the data when requested from internal page
	 *
	 * @NoAdminRequired
	 * @param int $reportId
	 * @param $filteroptions
	 * @param $dataoptions
	 * @param $chartoptions
	 * @param $tableoptions
	 * @return DataResponse|NotFoundResponse
	 * @throws Exception
	 */
	public function read(int $reportId, $filteroptions, $dataoptions, $chartoptions, $tableoptions) {
		$reportMetadata = $this->ReportService->read($reportId);
		if (empty($reportMetadata)) $reportMetadata = $this->ShareService->getSharedReport($reportId);

		if (!empty($reportMetadata)) {
			$reportMetadata = $this->evaluateCanFilter($reportMetadata, $filteroptions, $dataoptions, $chartoptions, $tableoptions);
			$result = $this->getData($reportMetadata);
			return new DataResponse($result, HTTP::STATUS_OK);
		} else {
			return new NotFoundResponse();
		}
	}

	/**
	 * get the data when requested from a panorama
	 *
	 * @NoAdminRequired
	 * @param int $reportIds
	 * @return DataResponse|NotFoundResponse
	 * @throws Exception
	 */
	public function readPanorama(int $reportId) {
		$reportMetadata = $this->ReportService->read($reportId);
		if (empty($reportMetadata)) $reportMetadata = $this->ShareService->getSharedPanoramaReport($reportId);

		if (!empty($reportMetadata)) {
			$result = $this->getData($reportMetadata);
			return new DataResponse($result, HTTP::STATUS_OK);
		} else {
			return new NotFoundResponse();
		}
	}

	/**
	 * Preview the data
	 *
	 * @NoAdminRequired
	 * @param $type
	 * @param $options
	 * @return DataResponse|NotFoundResponse
	 * @throws Exception
	 */
	public function readPreview($type, $options) {
		$reportMetadata = [];
		$array = json_decode($options, true);
		foreach ($array as $key => $value) {
			$array[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
		}
		$reportMetadata['link'] = json_encode($array);
		$reportMetadata['type'] = $type;
		$reportMetadata['dataset'] = 0;
		$reportMetadata['filteroptions'] = '';
		$reportMetadata['user_id'] = $this->userId;
		$reportMetadata['id'] = 0;

		$result = $this->getData($reportMetadata);
		unset($result['options'], $result['dimensions'], $result['filterApplied'], $result['thresholds']);
		$result['data'] = array_slice($result['data'], 0, 1);

		return new DataResponse($result, HTTP::STATUS_OK);
	}


	/**
	 * Get the data from backend;
	 * pre-evaluation of valid datasetId within read & readPublic is trusted here
	 * also used in Pagecontroller-indexPublicMin ToDo: make this private
	 *
	 * @NoAdminRequired
	 * @param $reportMetadata
	 * @return array
	 * @throws Exception
	 */
	public function getData($reportMetadata) {
		$datasource = (int)$reportMetadata['type'];
		$datasetId = (int)$reportMetadata['dataset'];

		$filterOptions = $reportMetadata['filteroptions']; // need to remember the filter with original text variables
		$reportMetadata = $this->VariableService->replaceFilterVariables($reportMetadata); // replace %xx% dynamic variables

		if ($datasource === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
			// Internal data
			if ($datasetId !== 0) {
				$result = $this->StorageService->read($datasetId, $reportMetadata);
			} else {
				$result['error'] = 'inconsistent report';
			}
		} else {
			// Realtime data
			$result = $this->DatasourceController->read($datasource, $reportMetadata);
			unset($result['rawdata']);
		}

		// sort the data by a given column
		if ($filterOptions && isset($result['data'])) {
			$result['data'] = $this->sortByColumn($result['data'], $filterOptions);
		}

		unset($reportMetadata['parent'], $reportMetadata['user_id'], $reportMetadata['link'], $reportMetadata['dimension1'], $reportMetadata['dimension2'], $reportMetadata['dimension3'], $reportMetadata['value'], $reportMetadata['password'], $reportMetadata['dataset']);

		$result['filterApplied'] = $reportMetadata['filteroptions'];
		$reportMetadata['filteroptions'] = $filterOptions; // keep the original filters

		// there can be different values for no options. null during creation; empty after removal; => harmonize them
		if (array_key_exists('chartoptions', $reportMetadata)) {
			$reportMetadata['chartoptions'] = ($reportMetadata['chartoptions'] === '' || $reportMetadata['chartoptions'] === 'null') ? null : $reportMetadata['chartoptions'];
		}

		if (array_key_exists('dataoptions', $reportMetadata)) {
			$reportMetadata['dataoptions'] = ($reportMetadata['dataoptions'] === '' || $reportMetadata['dataoptions'] === 'null') ? null : $reportMetadata['dataoptions'];
		}
		$result['options'] = $reportMetadata;
		$result['thresholds'] = $this->ThresholdService->read($reportMetadata['id']);
		return $result;
	}

	/**
	 * get the data when requested from public page
	 *
	 * @NoAdminRequired
	 * @PublicPage
	 * @UseSession
	 * @param $token
	 * @param $filteroptions
	 * @param $dataoptions
	 * @param $chartoptions
	 * @param $tableoptions
	 * @return DataResponse|NotFoundResponse
	 * @throws Exception
	 */
	public function readPublic($token, $filteroptions, $dataoptions, $chartoptions, $tableoptions) {
		$share = $this->ShareService->getReportByToken($token);
		if (empty($share)) {
			// Dataset not shared or wrong token
			return new NotFoundResponse();
		} else {
			if ($share['password'] !== null) {
				$password = $this->DataSession->getPasswordForShare($token);
				$passwordVerification = $this->ShareService->verifyPassword($password, $share['password']);
				if ($passwordVerification === false) {
					return new NotFoundResponse();
				}
			}
			$share = $this->evaluateCanFilter($share, $filteroptions, $dataoptions, $chartoptions, $tableoptions);
			$result = $this->getData($share);
			return new DataResponse($result, HTTP::STATUS_OK);
		}
	}

	/**
	 * evaluate if the user did filter in the report and if he is allowed to filter (shared reports)
	 *
	 * @param $metadata
	 * @param $filteroptions
	 * @param $dataoptions
	 * @param $chartoptions
	 * @param $tableoptions
	 * @return mixed
	 */
	private function evaluateCanFilter($metadata, $filteroptions, $dataoptions, $chartoptions, $tableoptions) {
		// send current user filter options to the data request
		// only if the report has update-permissions
		// if nothing is changed by the user, the filter which is stored for the report, will be used
		if ($filteroptions and $filteroptions !== '' and (int)$metadata['permissions'] === Constants::PERMISSION_UPDATE) {
			$metadata['filteroptions'] = $filteroptions;
			$metadata['dataoptions'] = $dataoptions;
			$metadata['chartoptions'] = $chartoptions;
			$metadata['tableoptions'] = $tableoptions;
		}
		return $metadata;
	}


	/**
	 * Sort a 2D array by a given column.
	 *
	 * @param array $data The input array of rows (each row is an array)
	 * @param string $filterOptions All options
	 * @return array             The sorted array
	 */
	private function sortByColumn(array $data, string $filterOptions): array {
		$filterOptions = json_decode($filterOptions, true);
		if (!isset($filterOptions['sort'])) {
			return $data;
		}

		// Normalize direction
		$direction = strtoupper($filterOptions['sort']['direction']);
		$colIndex = $filterOptions['sort']['dimension'];

		usort($data, function (array $a, array $b) use ($colIndex, $direction) {
			// If either row doesn't have that column, treat it as equal
			if (!isset($a[$colIndex], $b[$colIndex])) {
				return 0;
			}

			// Compare using the spaceship operator (works for strings & numbers)
			$cmp = $a[$colIndex] <=> $b[$colIndex];

			// Flip for descending
			return $direction === 'ASC' ? $cmp : -$cmp;
		});

		return $data;
	}

}