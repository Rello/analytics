<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\ApiController;
use OCA\Analytics\Controller\OutputController;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ApiControllerTest extends TestCase {
	private function controller($reports, $shares, $output): ApiController {
		return new ApiController('analytics', $this->createMock(IRequest::class), $this->createMock(\Psr\Log\LoggerInterface::class), $reports, $shares, $output);
	}

	public function testListIncludesSharedReportsAndOmitsFolders(): void {
		$reports = $this->createMock(ReportService::class);
		$reports->method('index')->willReturn([
			['id' => 1, 'type' => 2], ['id' => 2, 'type' => 0],
			['id' => 3, 'type' => 2, 'isShare' => 1],
		]);
		$result = $this->controller($reports, $this->createMock(ShareService::class), $this->createMock(OutputController::class))->index()->getData();
		$this->assertSame([1, 3], array_column($result, 'id'));
		$this->assertSame([0, 1], array_keys($result));
	}

	public function testSharedMetadataDoesNotExposeConnectionOrShareSecrets(): void {
		$reports = $this->createMock(ReportService::class);
		$reports->method('read')->with(3)->willReturn([]);
		$shares = $this->createMock(ShareService::class);
		$shares->method('getSharedReport')->with(3)->willReturn([
			'id' => 3, 'type' => 2, 'name' => 'Shared', 'filteroptions' => '{"filter":[]}',
			'link' => 'credentials', 'password' => 'hash', 'token' => 'secret', 'user_id' => 'owner',
		]);
		$result = $this->controller($reports, $shares, $this->createMock(OutputController::class))->read(3)->getData();
		$this->assertSame(['id', 'type', 'name', 'filteroptions'], array_keys($result));
	}

	public function testDataUsesViewerPipelineWithSavedSharedFilters(): void {
		$metadata = ['id' => 3, 'type' => 2, 'filteroptions' => '{"filter":{"dimension1":"saved"}}'];
		$reports = $this->createMock(ReportService::class);
		$reports->method('read')->willReturn([]);
		$shares = $this->createMock(ShareService::class);
		$shares->method('getSharedReport')->with(3)->willReturn($metadata);
		$output = $this->createMock(OutputController::class);
		$output->expects($this->once())->method('getData')->with($metadata)->willReturn([
			'header' => ['Label', 'Value'], 'data' => [['saved', 42]],
			'filterApplied' => $metadata['filteroptions'], 'options' => $metadata + ['token' => 'secret'],
			'cache' => ['key' => 'internal'],
		]);
		$result = $this->controller($reports, $shares, $output)->data(3)->getData();
		$this->assertSame([['saved', 42]], $result['data']);
		$this->assertSame($metadata['filteroptions'], $result['filterApplied']);
		$this->assertArrayNotHasKey('cache', $result);
		$this->assertArrayNotHasKey('token', $result['options']);
	}

	public function testInaccessibleReportNeverExecutesDatasource(): void {
		$reports = $this->createMock(ReportService::class);
		$reports->method('read')->willReturn([]);
		$shares = $this->createMock(ShareService::class);
		$shares->method('getSharedReport')->willReturn([]);
		$output = $this->createMock(OutputController::class);
		$output->expects($this->never())->method('getData');
		$this->expectException(OCSNotFoundException::class);
		$this->controller($reports, $shares, $output)->data(99);
	}

	public function testOwnedMetadataDoesNotRequireShare(): void {
		$reports = $this->createMock(ReportService::class);
		$reports->method('read')->with(1)->willReturn(['id' => 1, 'type' => 2]);
		$shares = $this->createMock(ShareService::class);
		$shares->expects($this->never())->method('getSharedReport');
		$this->assertSame(1, $this->controller($reports, $shares, $this->createMock(OutputController::class))->read(1)->getData()['id']);
	}
}
