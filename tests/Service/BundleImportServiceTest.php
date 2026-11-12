<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Service\BundleImportService;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class BundleImportServiceTest extends TestCase {
	public function testFailureRollsBackObjectsCreatedEarlierInTheBundle(): void {
		$db = new class implements IDBConnection {
			public array $calls = [];
			public function beginTransaction(): void { $this->calls[] = 'begin'; }
			public function commit(): void { $this->calls[] = 'commit'; }
			public function rollBack(): void { $this->calls[] = 'rollback'; }
		};
		$datasets = $this->createMock(DatasetService::class);
		$datasets->expects($this->once())->method('create')->willReturn(11);
		$storage = $this->createMock(StorageMapper::class);
		$storage->expects($this->once())->method('create')->with(11, 'Metric', '2026', '42');
		$reports = $this->createMock(ReportService::class);
		$reports->expects($this->once())->method('create')->willReturn(21);
		$reports->method('updateOptions')->willThrowException(new \RuntimeException('Injected write failure'));
		$service = (new \ReflectionClass(BundleImportService::class))->newInstanceWithoutConstructor();
		foreach (compact('db', 'datasets', 'storage', 'reports') as $name => $dependency) {
			$property = new \ReflectionProperty(BundleImportService::class, $name);
			$property->setAccessible(true);
			$property->setValue($service, $dependency);
		}
		try {
			$service->importBundle(['format' => 'analytics-bundle', 'version' => 1,
				'datasets' => [['key' => 'data', 'name' => 'Data', 'dimension1' => 'Metric', 'dimension2' => 'Year', 'value' => 'Value', 'data' => [['Metric', '2026', '42']]]],
				'reports' => [['key' => 'report', 'name' => 'Report', 'dataset' => 'data', 'visualization' => 'table', 'chart' => '']],
			]);
			$this->fail('Expected injected failure');
		} catch (\RuntimeException $e) {
			$this->assertSame('Injected write failure', $e->getMessage());
		}
		$this->assertSame(['begin', 'rollback'], $db->calls);
	}
}
