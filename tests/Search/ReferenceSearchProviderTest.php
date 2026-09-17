<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Search;

use OCA\Analytics\Search\ReferenceSearchProvider;
use OCA\Analytics\Search\SearchProvider;
use OCA\Analytics\Service\PanoramaService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\TestCase;

class ReferenceSearchProviderTest extends TestCase {
	private function search(array $reports, array $panoramas = [], bool $picker = true, bool $enabled = true): array {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn($enabled);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')->willReturnCallback(static function ($route, $parameters) {
			return 'https://cloud.example.com/nc/index.php/apps/analytics/'
				. ($route === 'analytics.page.report' ? 'r/' : 'pa/') . $parameters['id'];
		});
		$urlGenerator->method('imagePath')->willReturnCallback(static fn($app, $file) => '/img/' . $file);
		$urlGenerator->method('getAbsoluteURL')->willReturnCallback(static fn($path) => 'https://cloud.example.com' . $path);
		$reportService = $this->createMock(ReportService::class);
		$reportService->expects($enabled ? $this->once() : $this->never())->method('search')->with('Demo')->willReturn($reports);
		$panoramaService = $this->createMock(PanoramaService::class);
		$panoramaService->expects($enabled ? $this->once() : $this->never())->method('search')->with('Demo')->willReturn($panoramas);
		$providerClass = $picker ? ReferenceSearchProvider::class : SearchProvider::class;
		$provider = new $providerClass($appManager, new FakeL10N(), $urlGenerator, $reportService, $panoramaService);
		$this->assertSame($picker ? 'analytics-reference' : 'analytics', $provider->getId());
		$this->assertSame($picker ? null : 10, $provider->getOrder('', []));
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('Demo');
		return json_decode(json_encode($provider->search($this->createMock(IUser::class), $query)), true)['entries'];
	}

	public function testCombinedReportOffersAllModes(): void {
		$entries = $this->search([['id' => 4, 'name' => 'Demo: Finance', 'type' => 2, 'visualization' => 'ct']]);
		$this->assertSame(['Link', 'Chart and table', 'Chart only', 'Table only'], array_column($entries, 'subline'));
		$this->assertSame(array_fill(0, 4, 'Demo: Finance'), array_column($entries, 'title'));
		$this->assertSame([
			'https://cloud.example.com/nc/index.php/apps/analytics/r/4',
			'https://cloud.example.com/nc/index.php/apps/analytics/r/4/content',
			'https://cloud.example.com/nc/index.php/apps/analytics/r/4/chart',
			'https://cloud.example.com/nc/index.php/apps/analytics/r/4/table',
		], array_column($entries, 'resourceUrl'));
	}

	public function testSingleViewReportsPinTheirDisplayMode(): void {
		foreach (['chart' => 'Chart only', 'table' => 'Table only'] as $visualization => $label) {
			$entries = $this->search([['id' => 4, 'name' => 'Demo', 'type' => 2, 'visualization' => $visualization]]);
			$this->assertSame(['Link', $label], array_column($entries, 'subline'));
			$this->assertStringEndsWith('/r/4/' . $visualization, $entries[1]['resourceUrl']);
		}
	}

	public function testUnknownVisualizationOffersOnlyLinkAndGroupsAreOmitted(): void {
		$entries = $this->search([
			['id' => 4, 'name' => 'Demo', 'type' => 2],
			['id' => 5, 'name' => 'Demo folder', 'type' => 0, 'visualization' => 'ct'],
		]);
		$this->assertSame(['Link'], array_column($entries, 'subline'));
	}

	public function testPanoramaOffersOnlyLinkAndContent(): void {
		$entries = $this->search([], [['id' => 7, 'name' => 'Demo panorama']]);
		$this->assertSame(['Link', 'Content'], array_column($entries, 'subline'));
		$this->assertStringEndsWith('/pa/7', $entries[0]['resourceUrl']);
		$this->assertStringEndsWith('/pa/7/content', $entries[1]['resourceUrl']);
	}

	public function testGlobalSearchStillReturnsOnePlainLinkPerItem(): void {
		$entries = $this->search(
			[['id' => 4, 'name' => 'Demo report', 'type' => 2, 'visualization' => 'ct']],
			[['id' => 7, 'name' => 'Demo panorama']],
			false
		);
		$this->assertCount(2, $entries);
		$this->assertSame(['', ''], array_column($entries, 'subline'));
		$this->assertStringEndsWith('/r/4', $entries[0]['resourceUrl']);
		$this->assertStringEndsWith('/pa/7', $entries[1]['resourceUrl']);
	}

	public function testDisabledAppReturnsNoResults(): void {
		$this->assertSame([], $this->search([], [], true, false));
	}
}
