<?php

namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\PanoramaController;
use OCA\Analytics\Service\PanoramaService;
use OCP\AppFramework\Http\DataResponse;
use PHPUnit\Framework\TestCase;

class PanoramaControllerTest extends TestCase {
	public function testUpdateRejectsUnauthorizedPanorama(): void {
		$panoramaService = $this->createMock(PanoramaService::class);
		$panoramaService->expects($this->once())
			->method('isOwn')
			->with(99)
			->willReturn(false);
		$panoramaService->expects($this->never())
			->method('update');

		$controller = new PanoramaController(
			'analytics',
			$this->createMock(\OCP\IRequest::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$panoramaService
		);

		$response = $controller->update(99, 'Blocked', 1, 0, [['name' => 'page']]);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
		$this->assertFalse($response->getData());
	}

	public function testDeleteRejectsUnauthorizedPanorama(): void {
		$panoramaService = $this->createMock(PanoramaService::class);
		$panoramaService->expects($this->once())
			->method('isOwn')
			->with(77)
			->willReturn(false);
		$panoramaService->expects($this->never())
			->method('delete');

		$controller = new PanoramaController(
			'analytics',
			$this->createMock(\OCP\IRequest::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$panoramaService
		);

		$response = $controller->delete(77);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(403, $response->getStatus());
		$this->assertFalse($response->getData());
	}

	public function testUpdateEncodesPagesForAuthorizedPanorama(): void {
		$panoramaService = $this->createMock(PanoramaService::class);
		$panoramaService->expects($this->once())
			->method('isOwn')
			->with(5)
			->willReturn(true);
		$panoramaService->expects($this->once())
			->method('update')
			->with(
				5,
				'Allowed',
				1,
				0,
				json_encode([['title' => 'Page 1']])
			)
			->willReturn(true);

		$controller = new PanoramaController(
			'analytics',
			$this->createMock(\OCP\IRequest::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$panoramaService
		);

		$response = $controller->update(5, 'Allowed', 1, 0, [['title' => 'Page 1']]);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(200, $response->getStatus());
		$this->assertTrue($response->getData());
	}
}
