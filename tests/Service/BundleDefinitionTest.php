<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\BundleDefinition;
use PHPUnit\Framework\TestCase;

class BundleDefinitionTest extends TestCase {
	private function bundle(): array {
		return json_decode(file_get_contents(__DIR__ . '/../../resources/demo.json'), true, 512, JSON_THROW_ON_ERROR);
	}

	public function testBundledDemoUsesSharedDatasetsAndPortableReportReferences(): void {
		$bundle = $this->bundle();
		BundleDefinition::validate($bundle);
		$this->assertSame($bundle['reports'][0]['dataset'], $bundle['reports'][1]['dataset']);
		$this->assertSame($bundle['reports'][5]['dataset'], $bundle['reports'][6]['dataset']);
		$this->assertCount(6, $bundle['panoramas'][0]['pages'][0]['reports']);
		$this->assertSame('sales-ranking', $bundle['panoramas'][0]['pages'][0]['reports'][5]['value']);
	}

	public function testStructureOnlyBundleIsValid(): void {
		$bundle = $this->bundle();
		foreach ($bundle['datasets'] as &$dataset) unset($dataset['data']);
		BundleDefinition::validate($bundle);
		$this->addToAssertionCount(1);
	}

	/** @dataProvider invalidCases */
	public function testInvalidBundlesAreRejectedBeforeCreation(string $case): void {
		$bundle = $this->bundle();
		switch ($case) {
			case 'version': $bundle['version'] = 2; break;
			case 'duplicate': $bundle['reports'][] = $bundle['reports'][0]; break;
			case 'dataset-id': $bundle['reports'][0]['dataset'] = 88; break;
			case 'unknown-dataset': $bundle['reports'][0]['dataset'] = 'foreign'; break;
			case 'folder': $bundle['reports'][0]['folder'] = 'demo-datasets'; break;
			case 'tile-id': $bundle['panoramas'][0]['pages'][0]['reports'][0]['value'] = 156; break;
			case 'filter-id': $bundle['panoramas'][0]['filters'][0]['mappings'][0]['reportId'] = 'foreign'; break;
			case 'picture': $bundle['panoramas'][0]['pages'][0]['reports'][0] = ['type' => 2, 'value' => '42']; break;
			case 'precision': $bundle['datasets'][0]['data'][0][2] = '0.3098'; break;
			case 'row-shape': $bundle['datasets'][0]['data'][0] = ['only one']; break;
			case 'row-duplicate': $bundle['datasets'][0]['data'][] = $bundle['datasets'][0]['data'][0]; break;
			case 'nested-folder': $bundle['folders'][0]['parent'] = 'other'; break;
			case 'notification': $bundle['reports'][0]['thresholds'][0]['severity'] = 1; break;
			case 'external': $bundle['reports'][0]['type'] = 4; break;
			case 'flexible': $bundle['datasets'][0]['storageMode'] = 'flexible_shared'; break;
		}
		$this->expectException(\InvalidArgumentException::class);
		BundleDefinition::validate($bundle);
	}

	public static function invalidCases(): array {
		return array_map(static fn($case) => [$case], ['version', 'duplicate', 'dataset-id', 'unknown-dataset', 'folder', 'tile-id', 'filter-id', 'picture', 'precision', 'row-shape', 'row-duplicate', 'nested-folder', 'notification', 'external', 'flexible']);
	}
}
