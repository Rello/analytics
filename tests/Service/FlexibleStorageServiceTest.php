<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\FlexibleStorageMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Service\FlexibleStorageService;
use OCA\Analytics\Service\ThresholdService;
use OCA\Analytics\Storage\DatasetStorageResolver;
use OCA\Analytics\Storage\FlexibleValueNormalizer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FlexibleStorageServiceTest extends TestCase {
	public function testTimeAggregationRunsBeforePaginationWithoutFloatConversion(): void {
		$service = $this->createService([
			['c_1' => '2026-01-01 00:00:00', 'c_2' => 'North', 'c_3' => '99999999999999999999.1000000000'],
			['c_1' => '2026-01-15 00:00:00', 'c_2' => 'North', 'c_3' => '0.2000000000'],
			['c_1' => '2026-02-01 00:00:00', 'c_2' => 'South', 'c_3' => '3.0000000000'],
		]);

		$result = $service->query(7, [
			'aggregate' => true,
			'dimensions' => ['c_1', 'c_2'],
			'measures' => [['column' => 'c_3', 'aggregation' => 'sum']],
			'timeAggregation' => ['column' => 'c_1', 'grouping' => 'month', 'mode' => 'summation'],
			'limit' => 1,
		], true);

		$this->assertSame([['2026-01-01', 'North', '99999999999999999999.3']], $result['data']);
		$this->assertSame(2, $result['queryProcessing']['totalAfterProcessing']);
		$this->assertTrue($result['queryProcessing']['timeAggregation']);
	}

	public function testTopNUsesExplicitMeasureAndAggregatesAllOthersMeasures(): void {
		$service = $this->createService([
			['c_1' => '2026-01-01 00:00:00', 'c_2' => 'A', 'c_3' => '10.0', 'c_4' => '1.0'],
			['c_1' => '2026-01-01 00:00:00', 'c_2' => 'B', 'c_3' => '5.0', 'c_4' => '2.0'],
			['c_1' => '2026-01-01 00:00:00', 'c_2' => 'C', 'c_3' => '2.0', 'c_4' => '4.0'],
		]);

		$result = $service->query(7, [
			'aggregate' => true,
			'dimensions' => ['c_1', 'c_2'],
			'measures' => [
				['column' => 'c_3', 'aggregation' => 'sum'],
				['column' => 'c_4', 'aggregation' => 'sum'],
			],
			'topN' => ['dimension' => 'c_2', 'measure' => 'c_3', 'type' => 'top', 'number' => 1, 'others' => true],
		], true);

		$this->assertSame([
			['2026-01-01', 'A', '10.0', '1.0'],
			['2026-01-01', 'others', '7', '6'],
		], $result['data']);
		$this->assertTrue($result['queryProcessing']['topN']);
	}

	public function testReportQueryStartsWithEveryDatasetColumn(): void {
		$storageMapper = $this->createMock(FlexibleStorageMapper::class);
		$storageMapper->expects($this->exactly(2))->method('getColumns')->with(7)->willReturn($this->columns());
		$storageMapper->expects($this->once())->method('query')->with(
			7,
			$this->callback(static function (array $projection): bool {
				return array_column(array_column($projection, 'column'), 'ref') === ['c_1', 'c_2', 'c_3', 'c_4']
					&& array_column($projection, 'aggregation') === ['', '', 'sum', 'sum'];
			}),
			[],
			[],
			true,
			1000,
			0,
		)->willReturn([[
			'c_1' => '2026-01-01 00:00:00',
			'c_2' => 'North',
			'c_3' => '10',
			'c_4' => '4',
		]]);
		$service = $this->createServiceWithMapper($storageMapper, $this->createMock(ReportMapper::class));

		$result = $service->queryForReport(7, [
			'dimension1' => 'c_1',
			'value' => 'c_3',
			'dataoptions' => json_encode([
				'flexibleQuery' => [
					'dimensions' => ['c_1'],
					'measures' => [['column' => 'c_3', 'aggregation' => 'sum']],
				],
			], JSON_THROW_ON_ERROR),
		]);

		$this->assertSame(['Date', 'Region', 'Revenue', 'Cost'], $result['header']);
		$this->assertSame(['c_1', 'c_2', 'c_3', 'c_4'], $result['columnRefs']);
	}

	public function testMappedLoadStoresOnlyMappedColumnsAndLastDuplicateWins(): void {
		$storageMapper = $this->createMock(FlexibleStorageMapper::class);
		$storageMapper->method('getColumns')->with(7)->willReturn($this->columns());
		$storageMapper->expects($this->once())->method('beginTransaction');
		$storageMapper->expects($this->once())->method('commit');
		$storageMapper->expects($this->never())->method('rollBack');
		$storageMapper->expects($this->once())->method('findRecordId')->willReturn(null);
		$storageMapper->expects($this->once())->method('createRecord')->willReturn(99);
		$written = [];
		$storageMapper->expects($this->exactly(4))->method('writeValue')
			->willReturnCallback(function (int $datasetId, int $recordId, int $columnId, ?array $value) use (&$written): void {
				$this->assertSame(7, $datasetId);
				$this->assertSame(99, $recordId);
				$written[$columnId] = $value['canonical'] ?? null;
			});
		$reportMapper = $this->createMock(ReportMapper::class);
		$reportMapper->expects($this->once())->method('increaseVersionByDataset')->with(7);
		$thresholdService = $this->createMock(ThresholdService::class);
		$thresholdService->expects($this->once())->method('validateFlexibleRecords')
			->with(7, [[
				'values' => ['c_1' => '2026-01-01', 'c_2' => 'North', 'c_3' => '12', 'c_4' => '5'],
				'insert' => true,
			]]);
		$service = $this->createServiceWithMapper($storageMapper, $reportMapper, $thresholdService);

		$result = $service->executeMappedLoad(7, [
			'schemaVersion' => 1,
			'sourceHeader' => ['Date', 'Region', 'Revenue', 'Cost', 'Comment', 'Unused'],
			'columns' => [
				['column' => 'c_1', 'sourceIndex' => 0],
				['column' => 'c_2', 'sourceIndex' => 1],
				['column' => 'c_3', 'sourceIndex' => 2],
				['column' => 'c_4', 'sourceIndex' => 3],
			],
		], ['Date', 'Region', 'Revenue', 'Cost', 'Comment', 'Unused'], [
			['2026-01-01', 'North', '10.00', '4.00', 'first', 'ignored'],
			['2026-01-01', 'North', '12.00', '5.00', 'last', 'ignored'],
		], false);

		$this->assertSame(['2026-01-01', 'North', '12', '5'], array_values($written));
		$this->assertSame(1, $result['insert']);
		$this->assertSame(0, $result['update']);
	}

	/** @param list<array<string,mixed>> $rows */
	private function createService(array $rows): FlexibleStorageService {
		$storageMapper = $this->createMock(FlexibleStorageMapper::class);
		$storageMapper->method('getColumns')->with(7)->willReturn($this->columns());
		$storageMapper->method('query')->willReturn($rows);
		return $this->createServiceWithMapper($storageMapper, $this->createMock(ReportMapper::class));
	}

	/** @return list<array<string,mixed>> */
	private function columns(): array {
		return [
			['id' => 1, 'ref' => 'c_1', 'name' => 'Date', 'type' => 'date', 'role' => 'dimension', 'position' => 0, 'nullable' => false, 'defaultAggregation' => null],
			['id' => 2, 'ref' => 'c_2', 'name' => 'Region', 'type' => 'text', 'role' => 'dimension', 'position' => 1, 'nullable' => false, 'defaultAggregation' => null],
			['id' => 3, 'ref' => 'c_3', 'name' => 'Revenue', 'type' => 'decimal', 'role' => 'measure', 'position' => 2, 'nullable' => true, 'defaultAggregation' => 'sum'],
			['id' => 4, 'ref' => 'c_4', 'name' => 'Cost', 'type' => 'decimal', 'role' => 'measure', 'position' => 3, 'nullable' => true, 'defaultAggregation' => 'sum'],
		];
	}

	private function createServiceWithMapper(FlexibleStorageMapper $storageMapper, ReportMapper $reportMapper, ?ThresholdService $thresholdService = null): FlexibleStorageService {
		$resolver = $this->createMock(DatasetStorageResolver::class);
		$resolver->method('resolve')->willReturn([
			'mode' => DatasetStorageResolver::FLEXIBLE_SHARED,
			'dataset' => ['id' => 7, 'schema_version' => 1],
		]);

		return new FlexibleStorageService(
			$this->createMock(DatasetMapper::class),
			$storageMapper,
			$reportMapper,
			$resolver,
			new FlexibleValueNormalizer(),
			$thresholdService ?? $this->createMock(ThresholdService::class),
			new NullLogger(),
		);
	}
}
