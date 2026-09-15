<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Reference;

require_once __DIR__ . '/../Stubs/OC/Collaboration/Reference/ReferenceManager.php';

use OC\Collaboration\Reference\ReferenceManager;
use OCA\Analytics\Reference\ReferenceProvider;
use OCA\Analytics\Service\PanoramaService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCP\Collaboration\Reference\IReference;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ReferenceProviderTest extends TestCase {
	private $config;
	private $urlGenerator;
	private $reportService;
	private $panoramaService;
	private $shareService;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')->willReturn('1');

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('imagePath')
			->willReturnCallback(function ($app, $file) {
				return '/apps/analytics/img/' . $file;
			});
		$this->urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(function ($url) {
				return 'https://cloud.example.com' . $url;
			});

		$this->reportService = $this->createMock(ReportService::class);
		$this->panoramaService = $this->createMock(PanoramaService::class);
		$this->shareService = $this->createMock(ShareService::class);
	}

	private function buildProvider(): ReferenceProvider {
		return new ReferenceProvider(
			$this->config,
			new NullLogger(),
			new FakeL10N(),
			$this->urlGenerator,
			$this->createMock(ReferenceManager::class),
			$this->reportService,
			$this->panoramaService,
			$this->shareService,
			'testUser'
		);
	}

	public function testMatchReference(): void {
		$provider = $this->buildProvider();

		$this->assertTrue($provider->matchReference('https://cloud.example.com/apps/analytics/r/5'));
		$this->assertTrue($provider->matchReference('https://cloud.example.com/apps/analytics/pa/7'));
		$this->assertFalse($provider->matchReference('https://cloud.example.com/apps/files/'));
		$this->assertFalse($provider->matchReference('https://cloud.example.com/apps/analytics/'));
	}

	public function testMatchReferenceRespectsAdminDisable(): void {
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')
			->with('analytics', 'link_preview_enabled', '1')
			->willReturn('0');
		$provider = $this->buildProvider();

		$this->assertFalse($provider->matchReference('https://cloud.example.com/apps/analytics/r/5'));
	}

	public function testResolveOwnReport(): void {
		$this->reportService->expects($this->once())
			->method('read')
			->with(5)
			->willReturn(['id' => 5, 'name' => 'My Report']);
		$this->shareService->expects($this->never())
			->method('getSharedReport');

		$reference = $this->buildProvider()->resolveReference('https://cloud.example.com/apps/analytics/r/5');

		$this->assertInstanceOf(IReference::class, $reference);
		$richObject = $reference->getRichObject();
		$this->assertSame(5, $richObject['id']);
		$this->assertSame('report', $richObject['item_type']);
		$this->assertTrue($richObject['found']);
		$this->assertSame('My Report', $richObject['subheader']);
	}

	public function testResolveSharedReportFallsBackToShareService(): void {
		$this->reportService->expects($this->once())
			->method('read')
			->with(5)
			->willReturn([]);
		$this->shareService->expects($this->once())
			->method('getSharedReport')
			->with(5)
			->willReturn(['id' => 5, 'name' => 'Shared Report']);

		$reference = $this->buildProvider()->resolveReference('https://cloud.example.com/apps/analytics/r/5');

		$richObject = $reference->getRichObject();
		$this->assertTrue($richObject['found']);
		$this->assertSame('Shared Report', $richObject['subheader']);
	}

	public function testResolveMissingReport(): void {
		$this->reportService->method('read')->willReturn([]);
		$this->shareService->method('getSharedReport')->willReturn([]);

		$reference = $this->buildProvider()->resolveReference('https://cloud.example.com/apps/analytics/r/99');

		$richObject = $reference->getRichObject();
		$this->assertFalse($richObject['found']);
		$this->assertSame('Report not found', $richObject['name']);
	}

	public function testResolvePanorama(): void {
		// PanoramaService->read() contains the shared-panorama fallback itself
		$this->panoramaService->expects($this->once())
			->method('read')
			->with(7)
			->willReturn(['id' => 7, 'name' => 'My Panorama']);
		$this->reportService->expects($this->never())
			->method('read');

		$reference = $this->buildProvider()->resolveReference('https://cloud.example.com/apps/analytics/pa/7');

		$richObject = $reference->getRichObject();
		$this->assertSame(7, $richObject['id']);
		$this->assertSame('panorama', $richObject['item_type']);
		$this->assertTrue($richObject['found']);
		$this->assertSame('My Panorama', $richObject['subheader']);
	}

	public function testResolveWithoutTrailingIntegerReturnsNotFound(): void {
		$this->reportService->expects($this->never())->method('read');
		$this->shareService->expects($this->never())->method('getSharedReport');

		$reference = $this->buildProvider()->resolveReference('https://cloud.example.com/apps/analytics/r/');

		$this->assertInstanceOf(IReference::class, $reference);
		$richObject = $reference->getRichObject();
		$this->assertSame(0, $richObject['id']);
		$this->assertFalse($richObject['found']);
	}

	public function testResolveUnmatchedUrlReturnsNull(): void {
		$this->assertNull($this->buildProvider()->resolveReference('https://cloud.example.com/apps/files/'));
	}
}
