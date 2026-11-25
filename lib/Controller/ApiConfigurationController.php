<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ApiConfigurationService;
use OCA\Analytics\Exception\FlexibleStorageException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/** Individual native configuration operations; no cross-request transaction. */
class ApiConfigurationController extends OCSController {
	public function __construct(string $appName, IRequest $request, private ApiConfigurationService $configuration, private DatasetController $datasetController) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/datasources')]
	public function providers(): DataResponse {
		return $this->respond(fn() => $this->configuration->providers(), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/reports/{reportId}/configuration')]
	public function report(int $reportId): DataResponse {
		return $this->respond(fn() => $this->configuration->report($reportId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/reports')]
	public function createReport(): DataResponse {
		return $this->respond(fn() => $this->configuration->saveReport($this->body()), 201);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/reports/{reportId}')]
	public function updateReport(int $reportId): DataResponse {
		return $this->respond(fn() => $this->configuration->saveReport($this->body(['reportId']), $reportId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/datasets/{datasetId}')]
	public function dataset(int $datasetId): DataResponse {
		return $this->respond(fn() => $this->configuration->dataset($datasetId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/datasets')]
	public function createDataset(): DataResponse {
		return $this->respond(fn() => $this->configuration->saveDataset($this->body()), 201);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/datasets/{datasetId}')]
	public function updateDataset(int $datasetId): DataResponse {
		return $this->respond(fn() => $this->configuration->saveDataset($this->body(['datasetId']), $datasetId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/datasets/{datasetId}/dataloads')]
	public function loads(int $datasetId): DataResponse {
		return $this->respond(fn() => $this->configuration->loads($datasetId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/dataloads/{dataloadId}')]
	public function load(int $dataloadId): DataResponse {
		return $this->respond(fn() => $this->configuration->load($dataloadId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/dataloads')]
	public function createLoad(): DataResponse {
		return $this->respond(fn() => $this->configuration->saveLoad($this->body()), 201);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/dataloads/{dataloadId}')]
	public function updateLoad(int $dataloadId): DataResponse {
		return $this->respond(fn() => $this->configuration->saveLoad($this->body(['dataloadId']), $dataloadId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/panoramas')]
	public function panoramas(): DataResponse {
		return $this->respond(fn() => $this->configuration->panoramaIndex(), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/panoramas/{panoramaId}')]
	public function panorama(int $panoramaId): DataResponse {
		return $this->respond(fn() => $this->configuration->panorama($panoramaId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/panoramas')]
	public function createPanorama(): DataResponse {
		return $this->respond(fn() => $this->configuration->savePanorama($this->body()), 201);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/panoramas/{panoramaId}')]
	public function updatePanorama(int $panoramaId): DataResponse {
		return $this->respond(fn() => $this->configuration->savePanorama($this->body(['panoramaId']), $panoramaId), 200);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/datasets/{datasetId}/records')]
	public function upsertRecords(int $datasetId, int $schemaVersion, array $records): DataResponse {
		return $this->datasetController->upsertRecords($datasetId, $schemaVersion, $records);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/datasets/{datasetId}/records/{recordId}')]
	public function updateRecord(int $datasetId, int $recordId, int $schemaVersion, array $values): DataResponse {
		return $this->datasetController->updateRecord($datasetId, $recordId, $schemaVersion, $values);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/datasets/{datasetId}/records/{recordId}')]
	public function deleteRecord(int $datasetId, int $recordId): DataResponse {
		return $this->datasetController->deleteRecord($datasetId, $recordId);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/datasets/{datasetId}/query')]
	public function query(int $datasetId): DataResponse {
		return $this->datasetController->query($datasetId);
	}

	private function body(array $routeKeys = []): array {
		$params = $this->request->getParams();
		// Framework routing/OCS parameters are not configuration fields.
		foreach (array_merge($routeKeys, ['_route', 'format']) as $key) unset($params[$key]);
		return $params;
	}

	private function respond(callable $operation, int $status = 200): DataResponse {
		try {
			return new DataResponse($operation(), $status);
		} catch (FlexibleStorageException $e) {
			return new DataResponse($e->toResponse(), $e->getHttpStatus());
		} catch (\InvalidArgumentException|\JsonException $e) {
			return new DataResponse(['error' => ['code' => 'INVALID_CONFIGURATION', 'message' => $e->getMessage()]], 400);
		}
	}
}
