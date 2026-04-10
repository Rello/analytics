<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Service\VariableService;
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
			$this->createMock(VariableService::class)
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
			$this->createMock(VariableService::class)
		);

		$this->assertTrue($service->setFavorite(81, 'false'));
	}
}
