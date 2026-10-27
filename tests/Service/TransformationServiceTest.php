<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\TransformationService;
use PHPUnit\Framework\TestCase;

class TransformationServiceTest extends TestCase {
	private TransformationService $service;

	protected function setUp(): void {
		$this->service = new TransformationService();
	}

	public function testBeforeAndAfterAggregationUseDifferentGrains(): void {
		$source = $this->source([
			['North', 2, 10, 4],
			['North', 3, 20, 15],
			['South', 1, 5, 2],
		]);
		$definition = $this->definition([
			['id' => 'calc:amount', 'name' => 'Amount', 'expression' => '{quantity} * {price}', 'phase' => 'before', 'aggregation' => 'sum'],
			['id' => 'calc:margin', 'name' => 'Margin', 'expression' => '{profit} / {calc:amount}', 'phase' => 'after', 'aggregation' => 'sum'],
		]);
		$result = $this->service->execute($source, $definition);

		$this->assertSame(['segment', 'quantity', 'price', 'profit', 'calc:amount', 'calc:margin'], $result['columnRefs']);
		$this->assertSame(['North', 5.0, 30.0, 19.0, 80.0, 0.2375], $result['data'][0]);
		$this->assertSame(['South', 1.0, 5.0, 2.0, 5.0, 0.4], $result['data'][1]);
	}

	public function testHiddenInputsRemainAvailableAndSortingPrecedesProjectionAndLimit(): void {
		$source = $this->source([
			['North', 2, 10, 4],
			['South', 1, 5, 3],
			['West', 1, 4, 3],
			['East', 1, 5, 3],
		]);
		$definition = $this->definition([
			['id' => 'calc:ratio', 'name' => 'Ratio', 'expression' => '{profit} / {price}', 'phase' => 'after', 'aggregation' => 'sum'],
		]);
		$definition['hidden'] = ['quantity', 'price', 'profit'];
		$definition['sort'] = [
			['column' => 'calc:ratio', 'direction' => 'DESC'],
			['column' => 'segment', 'direction' => 'ASC'],
		];
		$definition['limit'] = 3;
		$result = $this->service->execute($source, $definition);

		$this->assertSame(['segment', 'calc:ratio'], $result['columnRefs']);
		$this->assertSame([['West', 0.75], ['East', 0.6], ['South', 0.6]], $result['data']);
	}

	public function testRemovedDimensionChangesGrouping(): void {
		$source = $this->source([['North', 2, 10, 4], ['South', 1, 5, 3]]);
		$definition = $this->definition([]);
		$result = $this->service->execute($source, $definition, ['drilldown' => ['segment' => false]]);

		$this->assertSame(['quantity', 'price', 'profit'], $result['columnRefs']);
		$this->assertSame([[3.0, 15.0, 7.0]], $result['data']);
	}

	public function testRejectsCircularCalculations(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('circular reference');
		$this->service->execute($this->source([]), $this->definition([
			['id' => 'calc:a', 'name' => 'A', 'expression' => '{calc:b} + 1', 'phase' => 'after'],
			['id' => 'calc:b', 'name' => 'B', 'expression' => '{calc:a} + 1', 'phase' => 'after'],
		]));
	}

	public function testDivisionByZeroReturnsNull(): void {
		$definition = $this->definition([
			['id' => 'calc:ratio', 'name' => 'Ratio', 'expression' => '{profit} / {price}', 'phase' => 'after'],
		]);
		$result = $this->service->execute($this->source([['North', 1, 0, 5]]), $definition);
		$this->assertNull($result['data'][0][4]);
	}

	public function testRejectsPaginatedSourceBeforeCalculation(): void {
		$source = $this->source([['North', 1, 4, 2]]);
		$source['queryProcessing'] = ['pagination' => true];
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('complete source result');
		$this->service->execute($source, $this->definition([]));
	}

	public function testAllNonePreservesDuplicateSourceRowsAndAfterCalculations(): void {
		$source = $this->source([['North', 2, 10, 4], ['North', 2, 10, 4]]);
		$definition = $this->definition([
			['id' => 'calc:amount', 'name' => 'Amount', 'expression' => '{quantity} * {price}', 'phase' => 'after'],
		]);
		$definition['aggregations'] = ['quantity' => 'none', 'price' => 'none', 'profit' => 'none'];
		$result = $this->service->execute($source, $definition);
		$this->assertSame([['North', 2, 10, 4, 20.0], ['North', 2, 10, 4, 20.0]], $result['data']);
		$this->assertFalse($result['queryProcessing']['aggregation']);
	}

	public function testNoneMeasureDefinesGroupsForCountAndDistinct(): void {
		$source = $this->source([
			['North', 2, 10, 4], ['North', 2, 10, 4], ['North', 2, 20, 5],
			['North', 3, 10, 4], ['North', 3, null, null],
		]);
		$definition = $this->definition([]);
		$definition['aggregations'] = ['quantity' => 'none', 'price' => 'count', 'profit' => 'count_distinct'];
		$result = $this->service->execute($source, $definition);
		$this->assertSame([['North', 2, 3, 2], ['North', 3, 1, 1]], $result['data']);
	}

	public function testBeforeCalculationWithNoneDefinesGroups(): void {
		$source = $this->source([['North', 2, 10, 4], ['North', 2, 10, 4], ['North', 3, 10, 4]]);
		$definition = $this->definition([
			['id' => 'calc:amount', 'name' => 'Amount', 'expression' => '{quantity} * {price}', 'phase' => 'before', 'aggregation' => 'none'],
		]);
		$definition['aggregations'] = ['quantity' => 'count', 'price' => 'count', 'profit' => 'count'];
		$result = $this->service->execute($source, $definition);
		$this->assertSame([['North', 2, 2, 2, 20.0], ['North', 1, 1, 1, 30.0]], $result['data']);
	}

	public function testLegacyAggregateFalseStillPreservesRows(): void {
		$source = $this->source([['North', 2, 10, 4], ['North', 2, 10, 4]]);
		$result = $this->service->execute($source, $this->definition([]), ['aggregate' => false]);
		$this->assertSame($source['data'], $result['data']);
	}

	public function testLookupScoresEachArticleBeforeSumming(): void {
		$source = $this->source([
			['T1', 1, 0, 0], ['T1', 1, 0, 0], ['T2', 1, 0, 0],
			['T3', 1, 0, 0], ['other', 1, 0, 0], [null, 1, 0, 0],
		]);
		$definition = $this->definition([
			['id' => 'calc:score', 'name' => 'Score', 'expression' => "LOOKUP({segment}, 'T1', 3, 'T2', 2, 'T3', 1, 0)", 'phase' => 'before'],
			['id' => 'calc:progress', 'name' => 'Progress', 'expression' => '{calc:score} / 10', 'phase' => 'after'],
		]);
		$result = $this->service->execute($source, $definition, ['drilldown' => ['segment' => false]]);
		$this->assertSame([[6.0, 0.0, 0.0, 9.0, 0.9]], $result['data']);
	}

	public function testLookupExactMatchingEscapesFallbackAndArithmetic(): void {
		$definition = $this->definition([
			['id' => 'calc:score', 'name' => 'Score',
				'expression' => "2 * lookup({segment}, 'T1', -3, 'Editor''s [tier] {unknown}', {profit} + 1, LOOKUP({segment}, '', 4, 7))",
				'phase' => 'before'],
		]);
		$source = $this->source([
			['T1', 1, 0, 2], ['t1', 1, 0, 2], ['T1 ', 1, 0, 2],
			["Editor's [tier] {unknown}", 1, 0, 2], ['', 1, 0, 2], [null, 1, 0, 2],
		]);
		$result = $this->service->execute($source, $definition, ['aggregate' => false]);
		$this->assertSame([-6.0, 14.0, 14.0, 6.0, 8.0, 14.0], array_column($result['data'], 4));
	}

	public function testLookupRejectsMalformedExpressions(): void {
		foreach (["LOOKUP({segment}, 'T1', 3)", "LOOKUP({segment}, 'T1', 3,)", "LOOKUP({segment}, 'T1, 3, 0)", "LOOKUP({missing}, 'T1', 3, 0)", "OTHER(1)", "1 2", "1 +"] as $expression) {
			try {
				$this->service->execute($this->source([]), $this->definition([
					['id' => 'calc:score', 'name' => 'Score', 'expression' => $expression, 'phase' => 'before'],
				]));
				$this->fail('Accepted invalid formula: ' . $expression);
			} catch (\InvalidArgumentException $exception) {
				$this->assertNotSame('', $exception->getMessage());
			}
		}
	}

	public function testTextArithmeticStillReturnsNull(): void {
		$result = $this->service->execute($this->source([['T1', 1, 0, 2]]), $this->definition([
			['id' => 'calc:score', 'name' => 'Score', 'expression' => '{segment} + 1', 'phase' => 'before'],
		]));
		$this->assertNull($result['data'][0][4]);
	}

	private function source(array $rows): array {
		return [
			'header' => ['Segment', 'Quantity', 'Price', 'Profit'],
			'columnRefs' => ['segment', 'quantity', 'price', 'profit'],
			'columns' => [
				['role' => 'dimension', 'type' => 'text'],
				['role' => 'measure', 'type' => 'decimal', 'defaultAggregation' => 'sum'],
				['role' => 'measure', 'type' => 'decimal', 'defaultAggregation' => 'sum'],
				['role' => 'measure', 'type' => 'decimal', 'defaultAggregation' => 'sum'],
			],
			'keyFigures' => ['Quantity', 'Price', 'Profit'],
			'data' => $rows,
			'error' => 0,
		];
	}

	private function definition(array $calculations): array {
		return ['version' => 1, 'calculations' => $calculations, 'hidden' => [], 'sort' => []];
	}
}
