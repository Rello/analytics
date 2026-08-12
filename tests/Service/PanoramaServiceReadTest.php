<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\PanoramaMapper;
use OCA\Analytics\Service\PanoramaService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCP\IConfig;
use OCP\ITagManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PanoramaServiceReadTest extends TestCase {
	private $panoramaMapper;
	private $shareService;

	protected function setUp(): void {
		$this->panoramaMapper = $this->createMock(PanoramaMapper::class);
		$this->shareService = $this->createMock(ShareService::class);
	}

	private function buildService(): PanoramaService {
		return new PanoramaService(
			'testUser',
			new FakeL10N(),
			new NullLogger(),
			$this->createMock(ITagManager::class),
			$this->shareService,
			$this->panoramaMapper,
			$this->createMock(IConfig::class),
			$this->createMock(VariableService::class),
			$this->createMock(ActivityManager::class)
		);
	}

	public function testReadReturnsOwnPanorama(): void {
		$own = ['id' => 7, 'name' => 'Own Panorama', 'pages' => '[]'];
		$this->panoramaMapper->expects($this->once())
			->method('readOwn')
			->with(7)
			->willReturn($own);
		$this->shareService->expects($this->never())
			->method('getSharedPanorama');

		$this->assertSame($own, $this->buildService()->read(7));
	}

	public function testReadFallsBackToSharedPanorama(): void {
		$this->panoramaMapper->expects($this->once())
			->method('readOwn')
			->with(7)
			->willReturn([]);
		$this->shareService->expects($this->once())
			->method('getSharedPanorama')
			->with(7)
			->willReturn([
				'id' => 7,
				'name' => 'Shared Panorama',
				'pages' => '[]',
				'type' => 99,
				'parent' => 0,
				'user_id' => 'someoneElse',
				'password' => 'secret-hash',
			]);

		$result = $this->buildService()->read(7);

		$this->assertSame(7, $result['id']);
		$this->assertSame('Shared Panorama', $result['name']);
		$this->assertSame(\OCP\Constants::PERMISSION_READ, $result['permissions']);
		// sensitive / internal share fields must not leak
		$this->assertArrayNotHasKey('user_id', $result);
		$this->assertArrayNotHasKey('password', $result);
	}

	public function testReadReturnsEmptyWhenNotAvailable(): void {
		$this->panoramaMapper->method('readOwn')->willReturn([]);
		$this->shareService->method('getSharedPanorama')->willReturn([]);

		$this->assertSame([], $this->buildService()->read(42));
	}
}
