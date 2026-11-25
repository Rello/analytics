<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\{ApiConfigurationService, ReportService, DatasetService, DataloadService, PanoramaService};
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Exception\FlexibleStorageException;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class ApiConfigurationServiceTest extends TestCase {
	private $reports;
	private $datasets;
	private $loads;
	private $panoramas;
	private $sources;
	private $db;
	private ApiConfigurationService $api;
	private array $report;

	protected function setUp(): void {
		$this->reports = $this->createMock(ReportService::class);
		$this->datasets = $this->createMock(DatasetService::class);
		$this->loads = $this->createMock(DataloadService::class);
		$this->panoramas = $this->createMock(PanoramaService::class);
		$this->sources = $this->createMock(DatasourceController::class);
		$this->sources->method('index')->willReturn(['datasources' => [6 => 'JSON']]);
		$this->db = $this->getMockBuilder(IDBConnection::class)->addMethods(['beginTransaction', 'commit', 'rollBack'])->getMock();
		$this->api = new ApiConfigurationService($this->reports, $this->datasets, $this->loads, $this->panoramas, $this->sources, $this->db);
		$this->report = ['id' => 1, 'name' => 'Native', 'type' => 6, 'dataset' => 0, 'parent' => 0, 'subheader' => '', 'visualization' => 'ct', 'chart' => 'column', 'refresh' => 0, 'dimension1' => '', 'dimension2' => '', 'value' => '',
			'link' => '{"url":"https://example.test/?token=SECRET","auth":"SECRET","customHeaders":"SECRET","path":"sales","unknown":"PRIVATE"}',
			'chartoptions' => '{"animation":{"duration":0}}', 'dataoptions' => '[{"borderWidth":7}]', 'filteroptions' => '{"aggregate":false}', 'tableoptions' => '{"order":[]}'];
	}

	public function testConfigurationReadPreservesNativeOptionsAndHidesWriteOnlySettings(): void {
		$this->reports->method('read')->with(1, false)->willReturn($this->report + ['user_id' => 'alice']);
		$r = $this->api->report(1);
		$this->assertSame(0, $r['chartoptions']->animation->duration);
		$this->assertSame(['path' => 'sales'], (array)$r['link']);
		$this->assertStringNotContainsString('SECRET', json_encode($r));
		$this->assertArrayNotHasKey('user_id', $r);
	}

	public function testPartialReportUpdatePreservesOmittedOptionsAndSecrets(): void {
		$this->reports->method('read')->willReturn($this->report);
		$this->reports->expects($this->once())->method('update')->willReturnCallback(function ($id, $name, $sub, $parent, $source, $vis, $chart, $chartoptions, $dataoptions) {
			$this->assertSame('Edited', $name);
			$this->assertSame('SECRET', json_decode($source, true)['auth']);
			$this->assertSame('new', json_decode($source, true)['path']);
			$this->assertSame($this->report['chartoptions'], $chartoptions);
			$this->assertSame($this->report['dataoptions'], $dataoptions);
			return true;
		});
		$this->reports->expects($this->once())->method('updateOptions')->with(1, $this->report['chartoptions'], $this->report['dataoptions'], $this->report['filteroptions'], $this->report['tableoptions'])->willReturn(true);
		$this->reports->method('updateRefresh')->willReturn(true);
		$this->db->expects($this->once())->method('commit');
		$this->api->saveReport(['name' => 'Edited', 'link' => ['path' => 'new']], 1);
	}

	public function testNullClearsOptionAndSourceWhileUnrelatedFieldsRemain(): void {
		$this->reports->method('read')->willReturn($this->report);
		$this->reports->expects($this->once())->method('update')->with(1, 'Native', '', 0, '{}', 'ct', 'column', null, $this->report['dataoptions'], '', '', '')->willReturn(true);
		$this->reports->expects($this->once())->method('updateOptions')->with(1, null, $this->report['dataoptions'], $this->report['filteroptions'], $this->report['tableoptions'])->willReturn(true);
		$this->reports->method('updateRefresh')->willReturn(true);
		$this->api->saveReport(['chartoptions' => null, 'link' => null], 1);
	}

	/** @dataProvider invalidReports */
	public function testInvalidReportFailsBeforeAnyWrite(array $input): void {
		$this->reports->method('read')->willReturn($this->report);
		$this->reports->expects($this->never())->method('update');
		$this->db->expects($this->never())->method('beginTransaction');
		$this->expectException(\InvalidArgumentException::class);
		$this->api->saveReport($input, 1);
	}

	public static function invalidReports(): array {
		return [[['name' => []]], [['chartoptions' => 'function(){}']], [['type' => 0]], [['dataset' => 77]], [['chart' => []]], [['refresh' => -1]], [['link' => ['auth' => ['nested']]]], [['unknown' => true]]];
	}

	/** @dataProvider unsupportedCharts */
	public function testUnsupportedChartFailsBeforeCreateOrUpdate($chart, ?int $id): void {
		$this->reports->method('read')->willReturn($this->report);
		$this->reports->expects($this->never())->method('create');
		$this->reports->expects($this->never())->method('update');
		$this->db->expects($this->never())->method('beginTransaction');
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid chart:');
		$this->api->saveReport(['name' => 'Invalid chart', 'chart' => $chart], $id);
	}

	public static function unsupportedCharts(): array {
		$cases = [];
		foreach (['pie', 'bar', 'table', 'unknown', 'Column', null, 1, []] as $chart) {
			foreach ([null, 1] as $id) $cases[] = [$chart, $id];
		}
		return $cases;
	}

	/** @dataProvider supportedCharts */
	public function testSupportedChartCanBeSaved(string $chart): void {
		$this->report['chart'] = $chart;
		$this->reports->method('read')->willReturn($this->report);
		$this->reports->expects($this->once())->method('update')->with(1, 'Native', '', 0, json_encode(json_decode($this->report['link'])), 'ct', $chart, $this->report['chartoptions'], $this->report['dataoptions'], '', '', '')->willReturn(true);
		$this->reports->method('updateOptions')->willReturn(true);
		$this->reports->method('updateRefresh')->willReturn(true);
		$this->assertSame($chart, $this->api->saveReport(['chart' => $chart], 1)['chart']);
	}

	public static function supportedCharts(): array {
		return array_map(static fn ($chart) => [$chart], ['', 'column', 'columnSt', 'columnSt100', 'line', 'datetime', 'area', 'doughnut', 'funnel']);
	}

	public function testSharedReportCannotBeReadForEditing(): void {
		$this->reports->method('read')->willReturn([]);
		$this->expectException(FlexibleStorageException::class);
		$this->api->report(99);
	}

	public function testUnownedDatasetCannotBeReferencedByStoredReport(): void {
		$this->reports->expects($this->once())->method('create')->willReturn(0);
		$this->expectException(\InvalidArgumentException::class);
		$this->api->saveReport(['name' => 'Denied', 'dataset' => 2]);
	}

	public function testFailedCreateConfigurationRollsBackItsOwnCall(): void {
		$this->reports->method('create')->willReturn(12);
		$this->reports->method('updateOptions')->willReturn(false);
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');
		$this->expectException(\InvalidArgumentException::class);
		$this->api->saveReport(['name' => 'Test', 'type' => 6]);
	}

	public function testDataloadRoundTripPreservesSecretAndMapping(): void {
		$this->datasets->method('readOwn')->willReturn(['id' => 7, 'type' => 2]);
		$this->loads->method('readOwnConfiguration')->willReturn(['id' => '8', 'dataset' => '7', 'datasource' => '6', 'name' => 'Load', 'schedule' => 'h', 'option' => '{"path":"sales","auth":"SECRET"}', 'storage_mapping' => '{"columns":[]}']);
		$this->loads->expects($this->once())->method('update')->with(8, 'Changed', '{"auth":"SECRET","path":"orders"}', 'h', '{"columns":[]}')->willReturn(true);
		$result = $this->api->saveLoad(['name' => 'Changed', 'option' => ['path' => 'orders']], 8);
		$this->assertStringNotContainsString('SECRET', json_encode($result));
	}

	public function testProviderBooleanSettingsUseNativeTrueFalseStrings(): void {
		$this->reports->method('read')->willReturn($this->report);
		$this->reports->expects($this->once())->method('update')->willReturnCallback(function ($id, $name, $sub, $parent, $source) {
			$this->assertSame('true', json_decode($source, true)['timestamp']);
			return true;
		});
		$this->reports->method('updateOptions')->willReturn(true);
		$this->reports->method('updateRefresh')->willReturn(true);
		$this->api->saveReport(['link' => ['timestamp' => true]], 1);
	}

	public function testLegacyDatasetUpdatePreservesAiIndexAndLabels(): void {
		$this->datasets->method('readOwn')->willReturn(['id' => 7, 'type' => 2, 'name' => 'Before', 'dimension1' => 'A', 'dimension2' => 'B', 'value' => 'C', 'ai_index' => 1]);
		$this->datasets->expects($this->once())->method('update')->with(7, 'After', '', 'A', 'B', 'C', 1)->willReturn(true);
		$this->api->saveDataset(['name' => 'After'], 7);
	}

	public function testLegacyCreateSavesSubtitleInsideItsOwnTransaction(): void {
		$this->datasets->method('create')->with('New', 'Object', 'Date', 'Value')->willReturn(7);
		$this->datasets->method('readOwn')->willReturn(['id' => 7, 'name' => 'New', 'type' => 2]);
		$this->datasets->expects($this->once())->method('update')->with(7, 'New', 'Subtitle', 'Object', 'Date', 'Value', 0)->willReturn(true);
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->assertSame(7, $this->api->saveDataset(['name' => 'New', 'subheader' => 'Subtitle'])['id']);
	}

	public function testFlexibleSchemaRetainsExistingVersionContract(): void {
		$this->datasets->method('readOwn')->willReturn(['id' => 7, 'name' => 'Flexible', 'storageMode' => 'flexible_shared', 'schemaVersion' => 3, 'columns' => [['id' => 1]]]);
		$this->datasets->expects($this->once())->method('updateFlexibleSchema')->with(7, 3, [['id' => 1]], 'New')->willReturn([]);
		$this->api->saveDataset(['name' => 'New', 'expectedSchemaVersion' => 3], 7);
	}

	public function testPanoramaValidationIsDelegatedToExistingService(): void {
		$this->panoramas->method('create')->willReturn(4);
		$pages = [['name' => 'Page', 'layoutId' => 6, 'reports' => [['type' => 0, 'value' => 42]]]];
		$this->panoramas->expects($this->once())->method('update')->with(4, 'Native', 1, 0, $pages, [])->willReturn(false);
		$this->db->expects($this->once())->method('rollBack');
		$this->expectException(\InvalidArgumentException::class);
		$this->api->savePanorama(['name' => 'Native', 'pages' => $pages]);
	}

	public function testSourceCreationUsesExistingCreateThenUpdateWithoutChangingServices(): void {
		$this->reports->expects($this->once())->method('create')->with('Native', '', 0, 6, 0, '{}', 'ct', 'column', 'Object', 'Date', 'Revenue')->willReturn(1);
		$this->reports->expects($this->once())->method('update')->with(1, 'Native', '', 0, '{"auth":"PRIVATE"}', 'ct', 'column', null, null, 'Object', 'Date', 'Revenue')->willReturn(true);
		$this->reports->method('updateOptions')->willReturn(true);
		$this->reports->method('updateRefresh')->willReturn(true);
		$this->reports->method('read')->willReturn($this->report);
		$this->api->saveReport(['name' => 'Native', 'type' => 6, 'value' => 'Revenue', 'link' => ['auth' => 'PRIVATE']]);
	}

	public function testReportCreationKeepsExistingImplicitDatasetBehaviour(): void {
		$this->datasets->expects($this->never())->method('readOwn');
		$this->reports->expects($this->once())->method('create')->with('Native', '', 0, 2, 0, '{}', 'ct', 'column', 'Object', 'Date', 'Value')->willReturn(1);
		$this->reports->method('updateOptions')->willReturn(true);
		$this->reports->method('updateRefresh')->willReturn(true);
		$this->reports->method('read')->willReturn(array_replace($this->report, ['type' => 2, 'dataset' => 7]));
		$this->assertSame(7, $this->api->saveReport(['name' => 'Native'])['dataset']);
	}

	public function testPanoramaDoesNotAddTilePermissionsOrRewriteNativePages(): void {
		$pages = [['name' => 'Native', 'page' => 17, 'layoutId' => 6, 'reports' => [['type' => 0, 'value' => 42, 'options' => ['future' => true]]]]];
		$this->reports->expects($this->never())->method('read');
		$this->panoramas->method('create')->willReturn(4);
		$this->panoramas->expects($this->once())->method('update')->with(4, 'Native', 1, 0, $pages, [])->willReturn(true);
		$this->panoramas->method('read')->willReturn(['id' => 4, 'type' => 1, 'name' => 'Native', 'pages' => json_encode($pages)]);
		$this->assertSame($pages, $this->api->savePanorama(['name' => 'Native', 'pages' => $pages])['pages']);
	}

	public function testPanoramaPartialUpdatePreservesPagesAndFilters(): void {
		$this->panoramas->method('isOwn')->willReturn(true);
		$this->panoramas->method('read')->willReturn(['id' => 4, 'type' => 1, 'name' => 'Before', 'parent' => '0', 'pages' => '[{"name":"Page","page":0,"layoutId":6,"reports":[{"type":1,"value":"Hello"}]}]', 'filters' => '[]']);
		$this->panoramas->expects($this->once())->method('update')->with(4, 'After', 1, 0, [['name' => 'Page', 'page' => 0, 'layoutId' => 6, 'reports' => [['type' => 1, 'value' => 'Hello']]]], null)->willReturn(true);
		$this->api->savePanorama(['name' => 'After'], 4);
	}
}
