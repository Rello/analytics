<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\{ApiConfigurationController, DatasetController};
use OCA\Analytics\Service\ApiConfigurationService;
use OCA\Analytics\Exception\FlexibleStorageException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ApiConfigurationControllerTest extends TestCase {
	private function controller($service, array $params = []): ApiConfigurationController {
		$request = $this->getMockBuilder(IRequest::class)->addMethods(['getParams'])->getMock();
		$request->method('getParams')->willReturn($params);
		return new ApiConfigurationController('analytics', $request, $service, $this->createMock(DatasetController::class));
	}
	public function testUrlIdentityWinsAndNativeObjectsArePassedUnmodified(): void {
		$api = $this->createMock(ApiConfigurationService::class);
		$options = ['elements' => ['line' => ['tension' => 0.37]]];
		$api->expects($this->once())->method('saveReport')->with(['chartoptions' => $options], 7)->willReturn(['id' => 7]);
		$this->assertSame(['id' => 7], $this->controller($api, ['reportId' => 999, 'format' => 'json', '_route' => 'ignored', 'chartoptions' => $options])->updateReport(7)->getData());
	}
	public function testCreationReturns201WithResult(): void {
		$api = $this->createMock(ApiConfigurationService::class);
		$api->method('savePanorama')->willReturn(['id' => 5]);
		$this->assertSame(201, $this->controller($api)->createPanorama()->getStatus());
	}
	public function testValidationFailureIsNotTransportSuccess(): void {
		$api = $this->createMock(ApiConfigurationService::class);
		$api->method('saveDataset')->willThrowException(new \InvalidArgumentException('Invalid columns'));
		$r = $this->controller($api)->createDataset();
		$this->assertSame(400, $r->getStatus());
		$this->assertSame('INVALID_CONFIGURATION', $r->getData()['error']['code']);
	}
	public function testFlexibleConflictPreservesExistingStatusAndCode(): void {
		$api = $this->createMock(ApiConfigurationService::class);
		$api->method('saveDataset')->willThrowException(new FlexibleStorageException('stale_schema', 'Schema changed', [], 409));
		$r = $this->controller($api)->updateDataset(7);
		$this->assertSame(409, $r->getStatus());
		$this->assertSame('stale_schema', $r->getData()['error']['code']);
	}
}
