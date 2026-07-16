<?php
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Notification\NotificationManager;
use OCA\Analytics\Service\DataloadService;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\StorageService;
use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCP\AppFramework\Http\NotFoundResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DataloadServiceTest extends TestCase {
	public function testGetDataFromDatasourceDisablesCacheValidation(): void {
		$datasourceController = $this->createMock(DatasourceController::class);
		$datasourceController->expects($this->once())
			->method('read')
			->with(
				DatasourceController::DATASET_TYPE_EXTERNAL_CSV,
				$this->callback(function ($metadata) {
					$this->assertSame('u1', $metadata['user_id']);
					$this->assertArrayNotHasKey('cacheKey', $metadata);
					$this->assertSame('persisted-cache', json_decode($metadata['link'], true)['cacheKey']);
					return true;
				}),
				false
			)
			->willReturn([
				'header' => ['d1', 'd2', 'value'],
				'dimensions' => ['d1', 'd2'],
				'data' => [['x', 'y', 1]],
				'error' => 0,
			]);

		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->once())
			->method('readOwnById')
			->with(42)
			->willReturn([
				'datasource' => DatasourceController::DATASET_TYPE_EXTERNAL_CSV,
				'dataset' => 11,
				'user_id' => 'u1',
				'option' => '{"link":"https://example.org/data.csv","cacheKey":"persisted-cache"}',
				'cacheKey' => 'request-key-should-not-be-used',
			]);

		$service = $this->createService($datasourceController, $dataloadMapper);
		$result = $service->getDataFromDatasource(42);

		$this->assertSame(11, $result['datasetId']);
		$this->assertSame(0, $result['error']);
	}

	public function testGetDataFromDatasourceOverridesFileForFlowExecution(): void {
		$datasourceController = $this->createMock(DatasourceController::class);
		$datasourceController->expects($this->once())
			->method('read')
			->with(
				DatasourceController::DATASET_TYPE_LOCAL_JSON,
				$this->callback(function ($metadata) {
					$option = json_decode($metadata['link'], true);
					$this->assertSame('/flow/import.json', $option['link']);
					$this->assertSame('/flow/import.json', $option['file']);
					$this->assertSame('items/{name,value}', $option['path']);
					return true;
				}),
				false
			)
			->willReturn([
				'header' => ['d1', 'd2', 'value'],
				'dimensions' => ['d1', 'd2'],
				'data' => [['x', 'y', 1]],
				'error' => 0,
			]);

		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->once())
			->method('readOwnById')
			->with(42)
			->willReturn([
				'datasource' => DatasourceController::DATASET_TYPE_LOCAL_JSON,
				'dataset' => 11,
				'user_id' => 'u1',
				'option' => '{"link":"/configured/import.json","path":"items/{name,value}"}',
			]);

		$service = $this->createService($datasourceController, $dataloadMapper);
		$result = $service->getDataFromDatasource(42, '/flow/import.json');

		$this->assertSame(11, $result['datasetId']);
		$this->assertSame(0, $result['error']);
	}

	public function testCreateRejectsUnownedDataset(): void {
		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->expects($this->once())
			->method('readOwn')
			->with(77)
			->willReturn(false);

		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->never())->method('create');

		$service = $this->createService(
			$this->createMock(DatasourceController::class),
			$dataloadMapper,
			$datasetService
		);

		$this->assertSame(0, $service->create(77, 0));
	}

	public function testUpdateDataRejectsUnownedDataset(): void {
		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->expects($this->once())
			->method('readOwn')
			->with(77)
			->willReturn(false);

		$storageService = $this->createMock(StorageService::class);
		$storageService->expects($this->never())->method('update');

		$service = $this->createService(
			$this->createMock(DatasourceController::class),
			$this->createMock(DataloadMapper::class),
			$datasetService,
			null,
			$storageService
		);

		$this->assertFalse($service->updateData(77, 'a', 'b', '1', true));
	}

	public function testGetDataFromDatasourceRejectsUnownedDataload(): void {
		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->once())
			->method('readOwnById')
			->with(42)
			->willReturn(false);
		$dataloadMapper->expects($this->never())->method('getDataloadById');

		$datasourceController = $this->createMock(DatasourceController::class);
		$datasourceController->expects($this->never())->method('read');

		$service = $this->createService($datasourceController, $dataloadMapper);

		$this->assertInstanceOf(NotFoundResponse::class, $service->getDataFromDatasource(42));
	}

	public function testExecuteRejectsUnownedDataload(): void {
		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->once())
			->method('readOwnById')
			->with(42)
			->willReturn(false);

		$service = $this->createService(
			$this->createMock(DatasourceController::class),
			$dataloadMapper
		);

		$result = $service->execute(42);

		$this->assertSame(1, $result['error']);
		$this->assertSame(0, $result['insert']);
		$this->assertSame(0, $result['update']);
		$this->assertSame(0, $result['delete']);
	}

	public function testExecuteNormalizesTwoColumnRowsForStorage(): void {
		$metadata = [
			'id' => 42,
			'datasource' => DatasourceController::DATASET_TYPE_LOCAL_CSV,
			'dataset' => 11,
			'user_id' => 'u1',
			'option' => '{"link":"/EXPENDITURE.CATEGORIES.csv"}',
		];
		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->exactly(2))
			->method('readOwnById')
			->with(42)
			->willReturn($metadata);

		$datasourceController = $this->createMock(DatasourceController::class);
		$datasourceController->expects($this->once())
			->method('read')
			->with(
				DatasourceController::DATASET_TYPE_LOCAL_CSV,
				$this->callback(function (array $dataloadMetadata): bool {
					$this->assertSame('u1', $dataloadMetadata['user_id']);
					return true;
				}),
				false
			)
			->willReturn([
				'header' => ['CATEGORY', 'EURO'],
				'dimensions' => ['CATEGORY'],
				'data' => [['ENTERTAINMENT', '9798']],
				'error' => 0,
			]);

		$storageService = $this->createMock(StorageService::class);
		$storageService->expects($this->once())
			->method('getRecordCount')
			->with(11, 'u1')
			->willReturn(['count' => 1]);
		$storageService->expects($this->once())
			->method('update')
			->with(11, 'ENTERTAINMENT', null, '9798', 'u1', null, null, false)
			->willReturn(['insert' => 0, 'update' => 0, 'error' => 0]);

		$service = $this->createService(
			$datasourceController,
			$dataloadMapper,
			null,
			null,
			$storageService
		);

		$result = $service->execute(42);

		$this->assertSame(0, $result['error']);
	}

	public function testScheduledDataloadDoesNotNotifyForPartialSuccess(): void {
		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->once())
			->method('getDataloadBySchedule')
			->with('hourly')
			->willReturn([[
				'id' => 42,
				'dataset' => 11,
				'name' => 'Partial load',
				'user_id' => 'u1',
			]]);

		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->expects($this->never())->method('read');

		$notificationManager = $this->createMock(NotificationManager::class);
		$notificationManager->expects($this->never())->method('triggerNotification');

		$service = $this->createScheduledServiceMock(
			['insert' => 1, 'update' => 0, 'delete' => 0, 'error' => 1],
			$dataloadMapper,
			$datasetService,
			$notificationManager
		);

		$service->executeBySchedule('hourly');
	}

	public function testScheduledDataloadNotifiesForCompleteFailure(): void {
		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->once())
			->method('getDataloadBySchedule')
			->with('daily')
			->willReturn([[
				'id' => 42,
				'dataset' => 11,
				'name' => 'Failed load',
				'user_id' => 'u1',
			]]);

		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->expects($this->once())
			->method('read')
			->with(11)
			->willReturn(['name' => 'Dataset']);

		$notificationManager = $this->createMock(NotificationManager::class);
		$notificationManager->expects($this->once())
			->method('triggerNotification')
			->with(
				NotificationManager::DATALOAD_ERROR,
				11,
				42,
				['dataloadName' => 'Failed load', 'datasetName' => 'Dataset'],
				'u1'
			);

		$service = $this->createScheduledServiceMock(
			['insert' => 0, 'update' => 0, 'delete' => 0, 'error' => 3],
			$dataloadMapper,
			$datasetService,
			$notificationManager
		);

		$service->executeBySchedule('daily');
	}

	private function createScheduledServiceMock(
		array $result,
		DataloadMapper $dataloadMapper,
		DatasetService $datasetService,
		NotificationManager $notificationManager
	) {
		$service = $this->getMockBuilder(DataloadService::class)
			->setConstructorArgs([
				'u1',
				new FakeL10N(),
				new NullLogger(),
				$this->createMock(ActivityManager::class),
				$this->createMock(DatasourceController::class),
				$this->createMock(ReportService::class),
				$datasetService,
				$this->createMock(StorageService::class),
				$this->createMock(VariableService::class),
				$notificationManager,
				$dataloadMapper,
			])
			->onlyMethods(['execute'])
			->getMock();

		$service->expects($this->once())
			->method('execute')
			->with(42, null, false)
			->willReturn($result);

		return $service;
	}

	private function createService(
		DatasourceController $datasourceController,
		DataloadMapper $dataloadMapper,
		?DatasetService $datasetService = null,
		?ReportService $reportService = null,
		?StorageService $storageService = null,
		?VariableService $variableService = null
	): DataloadService {
		return new DataloadService(
			'u1',
			new FakeL10N(),
			new NullLogger(),
			$this->createMock(ActivityManager::class),
			$datasourceController,
			$reportService ?? $this->createMock(ReportService::class),
			$datasetService ?? $this->createMock(DatasetService::class),
			$storageService ?? $this->createMock(StorageService::class),
			$variableService ?? $this->createMock(VariableService::class),
			$this->createMock(NotificationManager::class),
			$dataloadMapper
		);
	}
}
