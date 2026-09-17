<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\ShareService;
use PHPUnit\Framework\TestCase;

class ShareServiceSharedPanoramaTest extends TestCase {
	private function buildShareService(array $sharedItems) {
		$shareService = $this->getMockBuilder(ShareService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getSharedItems'])
			->getMock();
		$shareService->method('getSharedItems')
			->with(ShareService::SHARE_ITEM_TYPE_PANORAMA)
			->willReturn($sharedItems);
		return $shareService;
	}

	public function testGetSharedPanoramaReturnsMatch(): void {
		$shareService = $this->buildShareService([
			['id' => 3, 'name' => 'Other'],
			['id' => 7, 'name' => 'Shared Panorama'],
		]);

		$result = $shareService->getSharedPanorama(7);

		$this->assertSame(7, $result['id']);
		$this->assertSame('Shared Panorama', $result['name']);
	}

	public function testGetSharedPanoramaReturnsEmptyWhenNotShared(): void {
		$shareService = $this->buildShareService([
			['id' => 3, 'name' => 'Other'],
		]);

		$this->assertSame([], $shareService->getSharedPanorama(99));
	}
}
