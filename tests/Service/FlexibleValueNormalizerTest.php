<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Exception\FlexibleStorageException;
use OCA\Analytics\Storage\FlexibleValueNormalizer;
use PHPUnit\Framework\TestCase;

class FlexibleValueNormalizerTest extends TestCase {
	private FlexibleValueNormalizer $normalizer;

	protected function setUp(): void {
		$this->normalizer = new FlexibleValueNormalizer();
	}

	public function testPreservesNullEmptyZeroAndFalseAsDistinctValues(): void {
		$text = $this->column(1, 'text', true);
		$decimal = $this->column(2, 'decimal', true);
		$boolean = $this->column(3, 'boolean', true);

		$this->assertNull($this->normalizer->normalizeValue($text, null, 0));
		$this->assertSame('', $this->normalizer->normalizeValue($text, '', 0)['text_value']);
		$this->assertSame('0', $this->normalizer->normalizeValue($decimal, '0.000', 0)['decimal_value']);
		$this->assertSame('0', $this->normalizer->normalizeValue($boolean, false, 0)['decimal_value']);
	}

	public function testRejectsNonZeroFractionalPrecisionBeyondScale(): void {
		$this->expectException(FlexibleStorageException::class);
		$this->expectExceptionMessage('more than 10 non-zero fractional digits');

		$this->normalizer->normalizeValue($this->column(1, 'decimal', false), '1.12345678901', 0);
	}

	public function testAcceptsExcessTrailingZeroPrecisionWithoutRounding(): void {
		$value = $this->normalizer->normalizeValue($this->column(1, 'decimal', false), '1.230000000000', 0);

		$this->assertSame('1.23', $value['canonical']);
		$this->assertSame('1.23', $value['decimal_value']);
	}

	public function testRejectsFloatDecimalInput(): void {
		$this->expectException(FlexibleStorageException::class);
		$this->normalizer->normalizeValue($this->column(1, 'decimal', false), 0.1, 0);
	}

	public function testNormalizesDatetimeToUtc(): void {
		$value = $this->normalizer->normalizeValue($this->column(1, 'datetime', false), '2026-09-06T14:30:00+02:00', 0);

		$this->assertSame('2026-09-06 12:30:00', $value['datetime_value']);
		$this->assertSame('2026-09-06 12:30:00Z', $value['canonical']);
	}

	public function testRejectsRelativeDatetime(): void {
		$this->expectException(FlexibleStorageException::class);
		$this->normalizer->normalizeValue($this->column(1, 'datetime', false), 'tomorrow', 0);
	}

	public function testNullableSchemaFlagMustBeBoolean(): void {
		$this->expectException(FlexibleStorageException::class);
		$this->normalizer->normalizeColumn([
			'name' => 'Amount',
			'type' => 'decimal',
			'role' => 'measure',
			'nullable' => 'yes',
		], 0);
	}

	public function testRejectsInvalidCalendarDate(): void {
		$this->expectException(FlexibleStorageException::class);
		$this->normalizer->normalizeValue($this->column(1, 'date', false), '2026-02-30', 0);
	}

	public function testDimensionIdentityUsesStableIdOrderNotDisplayOrder(): void {
		$first = $this->column(9, 'text', false);
		$first['position'] = 0;
		$second = $this->column(2, 'decimal', false);
		$second['position'] = 1;
		$values = [
			9 => $this->normalizer->normalizeValue($first, 'North', 0),
			2 => $this->normalizer->normalizeValue($second, '12.50', 0),
		];

		$this->assertSame(
			$this->normalizer->dimensionKey([$first, $second], $values),
			$this->normalizer->dimensionKey([$second, $first], $values)
		);
	}

	/** @return array<string,mixed> */
	private function column(int $id, string $type, bool $nullable): array {
		return [
			'id' => $id,
			'ref' => 'c_' . $id,
			'name' => 'Column ' . $id,
			'type' => $type,
			'role' => 'dimension',
			'position' => 0,
			'nullable' => $nullable,
			'defaultAggregation' => null,
		];
	}
}
