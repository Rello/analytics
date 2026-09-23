<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\ApiDataController;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\StorageService;
use OCA\Analytics\Service\VariableService;
use OCP\IDateTimeFormatter;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ApiDataControllerTest extends TestCase {
	public function testDeletionFilterRejectsUnknownColumns(): void {
		$controller = $this->createController(
			$this->createMock(IRequest::class),
			$this->createMock(DatasetService::class),
			$this->createMock(StorageService::class),
			$this->createMock(VariableService::class)
		);
		$method = new \ReflectionMethod(ApiDataController::class, 'normalizeDeletionFilter');

		$result = $method->invoke($controller, [
			'not-a-dataset-column' => ['option' => 'LT', 'value' => '%last 5 days%'],
		], [
			'dimension1' => 'Reading date',
			'dimension2' => 'Source',
		]);

		$this->assertNull($result);
	}

	public function testDeleteDataV4ResolvesDateVariablesAndReturnsDeletionCount(): void {
		$request = $this->getMockBuilder(IRequest::class)
			->addMethods(['getParams'])
			->getMock();
		$request->method('getParams')->willReturn([
			'filter' => [
				'Reading date' => ['option' => 'LT', 'value' => '%last 5 days%'],
			],
		]);
		$datasetService = $this->createMock(DatasetService::class);
		$datasetService->method('readOwn')->with(42)->willReturn([
			'id' => 42,
			'storageMode' => 'legacy',
			'dimension1' => 'Reading date',
			'dimension2' => 'Source',
		]);
		$datasetService->expects($this->once())->method('provider')->with(42);
		$variableService = $this->createMock(VariableService::class);
		$variableService->expects($this->once())
			->method('replaceFilterVariables')
			->willReturn([
				'filteroptions' => '{"filter":{"dimension1":{"option":"LT","value":"2026-09-10"}}}',
			]);
		$storageService = $this->createMock(StorageService::class);
		$storageService->expects($this->once())
			->method('deleteWithFilter')
			->with(42, [
				'filter' => [
					'dimension1' => ['option' => 'LT', 'value' => '2026-09-10'],
				],
			])
			->willReturn(7);

		$response = $this->createController($request, $datasetService, $storageService, $variableService)
			->deleteDataV4(42);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame([
			'success' => true,
			'message' => 'Data deleted',
			'delete' => 7,
		], $response->getData());
	}

	public function testDataGetV3RejectsForgedReportBeforeStorageRead(): void {
		$request = $this->getMockBuilder(IRequest::class)->addMethods(['getParams'])->getMock();
		$request->method('getParams')->willReturn([]);
		$reportService = $this->createMock(ReportService::class);
		$reportService->expects($this->once())->method('readOwnDatasetReport')->with(42)->willReturn([]);
		$storageService = $this->createMock(StorageService::class);
		$storageService->expects($this->never())->method('read');
		$response = $this->createController($request, $this->createMock(DatasetService::class),
			$storageService, $this->createMock(VariableService::class), $reportService)->dataGetV3(42);
		$this->assertSame(['message' => 'No data available for given report id'], $response->getData());
	}

	public function testDataGetV3ReadsAuthorizedReport(): void {
		$request = $this->getMockBuilder(IRequest::class)->addMethods(['getParams'])->getMock();
		$request->method('getParams')->willReturn([]);
		$metadata = ['type' => 2, 'dataset' => 77, 'filteroptions' => '{}'];
		$reportService = $this->createMock(ReportService::class);
		$reportService->method('readOwnDatasetReport')->with(42)->willReturn($metadata);
		$storageService = $this->createMock(StorageService::class);
		$storageService->expects($this->once())->method('read')->with(77, $metadata)
			->willReturn(['data' => [['dimension1' => 'safe']]]);
		$response = $this->createController($request, $this->createMock(DatasetService::class),
			$storageService, $this->createMock(VariableService::class), $reportService)->dataGetV3(42);
		$this->assertSame([['dimension1' => 'safe']], $response->getData());
	}

	private function createController(
		IRequest $request,
		DatasetService $datasetService,
		StorageService $storageService,
		VariableService $variableService,
		?ReportService $reportService = null
	): ApiDataController {
		return new ApiDataController(
			'analytics',
			$request,
			$this->createMock(LoggerInterface::class),
			$datasetService,
			$reportService ?? $this->createMock(ReportService::class),
			$storageService,
			$this->createMock(StorageMapper::class),
			$this->createMock(IDateTimeFormatter::class),
			$variableService
		);
	}
}
