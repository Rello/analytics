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
			->method('getDataloadById')
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

	private function createService(DatasourceController $datasourceController, DataloadMapper $dataloadMapper): DataloadService {
		return new DataloadService(
			'u1',
			new FakeL10N(),
			new NullLogger(),
			$this->createMock(ActivityManager::class),
			$datasourceController,
			$this->createMock(ReportService::class),
			$this->createMock(DatasetService::class),
			$this->createMock(StorageService::class),
			$this->createMock(VariableService::class),
			$this->createMock(NotificationManager::class),
			$dataloadMapper
		);
	}
}
