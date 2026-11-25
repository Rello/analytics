<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Service\FlexibleStorageService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReportServiceTest extends TestCase {
	public function testImportKeepsReportWhenFavoriteMarkingFails(): void {
		$favoriteHandler = new class {
			public function addToFavorites(int $reportId): bool {
				throw new \RuntimeException('node with id ' . $reportId . ' not found');
			}
		};

		$tagManager = $this->createMock(\OCP\ITagManager::class);
		$tagManager->expects($this->once())
			->method('load')
			->with('analytics')
			->willReturn($favoriteHandler);

		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->expects($this->once())
			->method('create')
			->willReturn(71);
		$reportMapper->expects($this->once())
			->method('readOwn')
			->with(71)
			->willReturn(['dataset' => 11]);

		$dataloadMapper = $this->createMock(DataloadMapper::class);
		$dataloadMapper->expects($this->once())->method('beginTransaction');
		$dataloadMapper->expects($this->once())->method('commit');

		$service = new ReportService(
			'u1',
			new FakeL10N(),
			$this->createMock(LoggerInterface::class),
			$tagManager,
			$this->createMock(ShareService::class),
			$this->createMock(DatasetService::class),
			$this->createMock(StorageMapper::class),
			$reportMapper,
			$this->createMock(ThresholdMapper::class),
			$dataloadMapper,
			$this->createMock(ActivityManager::class),
			$this->createMock(\OCP\Files\IRootFolder::class),
			$this->createMock(\OCP\IConfig::class),
				$this->createMock(VariableService::class),
				$this->createMock(FlexibleStorageService::class),
				$this->createMock(DatasourceController::class)
		);

		$payload = json_encode([
			'report' => [
				'name' => 'Demo: Population Data',
				'type' => 4,
				'link' => '{}',
				'visualization' => 'ct',
				'chart' => 'line',
				'dimension1' => '',
				'dimension2' => '',
				'value' => '',
			],
			'dataload' => [],
			'threshold' => [],
			'favorite' => 'true',
			'data' => [],
		], JSON_THROW_ON_ERROR);

		$result = $service->import(null, $payload);

		$this->assertSame(71, $result);
	}

	public function testImportRejectsInvalidLoadBeforeCreatingAnyRecords(): void {
		$datasets = $this->createMock(DatasetService::class);
		$datasets->expects($this->never())->method('create');
		$reports = $this->createMock(ReportMapper::class);
		$reports->expects($this->never())->method('create');
		$datasources = $this->createMock(DatasourceController::class);
		$datasources->expects($this->exactly(2))->method('encodeOptionsForStorage')->withConsecutive(
			[1, '{"hasHeader":"true"}', true],
			[1, '{"hasHeader":"stale"}', true],
		)->willReturnOnConsecutiveCalls('{"hasHeader":"true"}', $this->throwException(new \InvalidArgumentException('Invalid source choice')));

		$service = $this->createReportService($reports, $datasets, $datasources);
		$this->expectExceptionMessage('Invalid source choice');
		$service->import(null, json_encode([
			'report' => ['type' => 2],
			'dataload' => [
				['datasource' => '1', 'option' => '{"hasHeader":"true"}'],
				['datasource' => 1, 'option' => '{"hasHeader":"stale"}'],
			],
		], JSON_THROW_ON_ERROR));
	}

	public function testImportWritesValidatedDataLoadOptions(): void {
		$reports = $this->createMock(ReportMapper::class);
		$reports->method('create')->willReturn(71);
		$reports->method('readOwn')->willReturn(['dataset' => 11]);
		$datasets = $this->createMock(DatasetService::class);
		$datasets->method('create')->willReturn(11);
		$datasets->method('readOwn')->willReturn(['id' => 11]);

		$options = '{"hasHeader":"true","link":"A & B.csv"}';
		$encoded = '{"hasHeader":"true","link":"A &amp; B.csv"}';
		$datasources = $this->createMock(DatasourceController::class);
		$datasources->method('encodeOptionsForStorage')->willReturnMap([
			[1, $options, true, $encoded],
			[2, '{}', false, '{}'],
		]);
		$loads = $this->createMock(DataloadMapper::class);
		$loads->expects($this->once())->method('create')->with(11, 1)->willReturn(12);
		$loads->expects($this->once())->method('update')->with(12, 'Local CSV', $encoded, null, null);
		$service = $this->createReportService($reports, $datasets, $datasources, $loads);
		$this->assertSame(71, $service->import(null, json_encode([
			'report' => ['type' => 2, 'link' => '{}'],
			'dataload' => [['datasource' => 1, 'name' => 'Local CSV', 'option' => $options]],
		], JSON_THROW_ON_ERROR)));
	}

	public function testUnsetFavoriteHandlesMissingTagNode(): void {
		$favoriteHandler = new class {
			public function getFavorites(): array {
				return [81];
			}

			public function removeFromFavorites(int $reportId): bool {
				throw new \OCP\DB\Exception('Failed to unfavorite: node with id ' . $reportId . ' not found');
			}
		};

		$tagManager = $this->createMock(\OCP\ITagManager::class);
		$tagManager->expects($this->exactly(2))
			->method('load')
			->with('analytics')
			->willReturn($favoriteHandler);

		$service = new ReportService(
			'u1',
			new FakeL10N(),
			$this->createMock(LoggerInterface::class),
			$tagManager,
			$this->createMock(ShareService::class),
			$this->createMock(DatasetService::class),
			$this->createMock(StorageMapper::class),
			$this->createMock(ReportMapper::class),
			$this->createMock(ThresholdMapper::class),
			$this->createMock(DataloadMapper::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(\OCP\Files\IRootFolder::class),
			$this->createMock(\OCP\IConfig::class),
				$this->createMock(VariableService::class),
				$this->createMock(FlexibleStorageService::class),
				$this->createMock(DatasourceController::class)
		);

		$this->assertTrue($service->setFavorite(81, 'false'));
	}

	public function testCreateRejectsUnownedInternalDataset(): void {
		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->expects($this->once())
			->method('readOwn')
			->with(77)
			->willReturn(false);

		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->expects($this->never())->method('create');

		$service = new ReportService(
			'u1',
			new FakeL10N(),
			$this->createMock(LoggerInterface::class),
			$this->createMock(\OCP\ITagManager::class),
			$this->createMock(ShareService::class),
			$datasetService,
			$this->createMock(StorageMapper::class),
			$reportMapper,
			$this->createMock(ThresholdMapper::class),
			$this->createMock(DataloadMapper::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(\OCP\Files\IRootFolder::class),
			$this->createMock(\OCP\IConfig::class),
				$this->createMock(VariableService::class),
				$this->createMock(FlexibleStorageService::class),
				$this->createMock(DatasourceController::class)
		);

		$result = $service->create('Report', '', 0, 2, 77, '{}', 'table', 'table', 'A', 'B', 'Value');

		$this->assertSame(0, $result);
	}
	public function testCreateRejectsNonInternalDatasetReference(): void {
		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->expects($this->never())->method('create');
		$service = $this->createReportService($reportMapper, $this->createMock(DatasetService::class));
		$this->assertSame(0, $service->create('External', '', 0, 4, 77, '{}', 'table', 'table', '', '', ''));
	}

	public function testReportUpdateValidatesOptionsForTheStoredDatasourceBeforeWriting(): void {
		$options = '{"mode":"unavailable"}';
		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->method('readOwn')->with(42)->willReturn(['type' => DatasourceController::DATASET_TYPE_LOCAL_CSV]);
		$reportMapper->expects($this->never())->method('update');
		$datasourceController = $this->createMock(DatasourceController::class);
		$datasourceController->expects($this->once())->method('encodeOptionsForStorage')
			->with(DatasourceController::DATASET_TYPE_LOCAL_CSV, $options)
			->willThrowException(new \InvalidArgumentException('Invalid source choice'));
		$service = $this->createReportService($reportMapper, $this->createMock(DatasetService::class), $datasourceController);

		$this->expectExceptionMessage('Invalid source choice');
		$service->update(42, 'Report', '', 0, $options, 'table', 'table', '{}', '{}');
	}

	public function testReadOwnDatasetReportRejectsExistingForeignAssociation(): void {
		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->method('readOwn')->with(42)->willReturn(['type' => 2, 'dataset' => 77]);
		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->method('readOwn')->with(77)->willReturn(false);
		$service = $this->createReportService($reportMapper, $datasetService);
		$this->assertSame([], $service->readOwnDatasetReport(42));
	}

	public function testReadOwnDatasetReportAllowsOwnedInternalDataset(): void {
		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->method('readOwn')->with(42)->willReturn(['type' => 2, 'dataset' => 77]);
		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->method('readOwn')->with(77)->willReturn(['id' => 77]);
		$service = $this->createReportService($reportMapper, $datasetService);
		$this->assertSame(['type' => 2, 'dataset' => 77], $service->readOwnDatasetReport(42));
	}

	private function createReportService(ReportMapper $reportMapper, DatasetService $datasetService, ?DatasourceController $datasourceController = null, ?DataloadMapper $loads = null): ReportService {
		return new ReportService(
			'u1', new FakeL10N(), $this->createMock(LoggerInterface::class),
			$this->createMock(\OCP\ITagManager::class), $this->createMock(ShareService::class),
			$datasetService, $this->createMock(StorageMapper::class), $reportMapper,
			$this->createMock(ThresholdMapper::class), $loads ?? $this->createMock(DataloadMapper::class),
			$this->createMock(ActivityManager::class), $this->createMock(\OCP\Files\IRootFolder::class),
			$this->createMock(\OCP\IConfig::class), $this->createMock(VariableService::class),
			$this->createMock(FlexibleStorageService::class), $datasourceController ?? $this->createMock(DatasourceController::class)
		);
	}

}
