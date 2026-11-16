<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Renderer;

use PhpOffice\WMF\Renderer\Rasterizer;
use PHPUnit\Framework\TestCase;

class RasterizerTest extends TestCase
{
    public function testRasterizeRectangle(): void
    {
        $spans = Rasterizer::rasterize([[[1, 1], [4, 1], [4, 3], [1, 3]]], false, 10, 10);

        // Pixels whose center is inside the rectangle
        $this->assertEquals([
            1 => [[1, 3]],
            2 => [[1, 3]],
        ], $spans);
    }

    public function testRasterizeClampedToCanvas(): void
    {
        $spans = Rasterizer::rasterize([[[-5, -5], [20, -5], [20, 2], [-5, 2]]], false, 10, 10);

        $this->assertEquals([
            0 => [[0, 9]],
            1 => [[0, 9]],
        ], $spans);
    }

    public function testRasterizeFillRules(): void
    {
        // Two overlapping squares with the same orientation
        $polygons = [
            [[0, 0], [4, 0], [4, 1], [0, 1]],
            [[2, 0], [6, 0], [6, 1], [2, 1]],
        ];

        // Even-odd : the intersection is a hole
        $this->assertEquals([0 => [[0, 1], [4, 5]]], Rasterizer::rasterize($polygons, false, 10, 10));
        // Nonzero : the union
        $this->assertEquals([0 => [[0, 5]]], Rasterizer::rasterize($polygons, true, 10, 10));
    }

    /**
     * @return array<array<mixed>>
     */
    public static function dataProviderCombineRow(): array
    {
        $first = [[0, 4], [8, 9]];
        $second = [[2, 8]];

        return [
            [$first, $second, Rasterizer::RGN_AND, [[2, 4], [8, 8]]],
            [$first, $second, Rasterizer::RGN_OR, [[0, 9]]],
            [$first, $second, Rasterizer::RGN_XOR, [[0, 1], [5, 7], [9, 9]]],
            [$first, $second, Rasterizer::RGN_DIFF, [[0, 1], [9, 9]]],
            [$first, $second, Rasterizer::RGN_COPY, [[2, 8]]],
            [$first, [], Rasterizer::RGN_AND, []],
        ];
    }

    /**
     * @dataProvider dataProviderCombineRow
     *
     * @param array<array<int>> $first
     * @param array<array<int>> $second
     * @param array<array<int>> $expected
     */
    public function testCombineRow(array $first, array $second, int $mode, array $expected): void
    {
        $this->assertEquals($expected, Rasterizer::combineRow($first, $second, $mode));
    }

    public function testCombineSpans(): void
    {
        $first = [0 => [[0, 4]], 1 => [[0, 4]]];
        $second = [1 => [[2, 6]], 2 => [[2, 6]]];

        $this->assertEquals([1 => [[2, 4]]], Rasterizer::combineSpans($first, $second, Rasterizer::RGN_AND));
        $this->assertEquals([0 => [[0, 4]], 1 => [[0, 6]], 2 => [[2, 6]]], Rasterizer::combineSpans($first, $second, Rasterizer::RGN_OR));
        $this->assertEquals([0 => [[0, 4]], 1 => [[0, 1]]], Rasterizer::combineSpans($first, $second, Rasterizer::RGN_DIFF));
    }

    public function testStrokePolygons(): void
    {
        // A segment with flat end caps is a rectangle
        $polygons = Rasterizer::getStrokePolygons([[0, 5], [10, 5]], false, 2, 0x0200, 0x0000, 10);
        $this->assertCount(1, $polygons);
        $this->assertEquals([3 => [[0, 9]], 4 => [[0, 9]], 5 => [[0, 9]], 6 => [[0, 9]]], Rasterizer::rasterize($polygons, true, 20, 20));

        // Round end caps add discs
        $this->assertCount(3, Rasterizer::getStrokePolygons([[0, 5], [10, 5]], false, 2, 0x0000, 0x0000, 10));
        // A single point is a disc
        $this->assertCount(1, Rasterizer::getStrokePolygons([[5, 5], [5, 5]], false, 2, 0x0000, 0x0000, 10));
        // All polygons have the same orientation
        foreach (Rasterizer::getStrokePolygons([[0, 0], [10, 0], [10, 10], [0, 10]], true, 2, 0x0000, 0x2000, 10) as $polygon) {
            $this->assertGreaterThanOrEqual(0, Rasterizer::getSignedArea($polygon));
        }
    }

    public function testMiterJoin(): void
    {
        $normalIn = [0, 1];
        $normalOut = [-1, 0];

        // Square corner : the miter point is at the corner of the offset lines
        $this->assertEquals([[10, 0], [10, -1], [11, -1], [11, 0]], Rasterizer::getJoinPolygon([10, 0], $normalIn, $normalOut, 1, 0x2000, 10));
        // Miter limit exceeded : bevel
        $this->assertCount(3, Rasterizer::getJoinPolygon([10, 0], $normalIn, $normalOut, 1, 0x2000, 1));
        // Bevel
        $this->assertCount(3, Rasterizer::getJoinPolygon([10, 0], $normalIn, $normalOut, 1, 0x1000, 10));
    }

    public function testFlattenBeziers(): void
    {
        // A straight Bézier curve
        $points = Rasterizer::flattenBeziers([[0, 0], [10, 0], [20, 0], [30, 0]]);

        $this->assertEquals([0, 0], $points[0]);
        $this->assertEquals([30, 0], end($points));
        foreach ($points as $point) {
            $this->assertEqualsWithDelta(0, $point[1], 0.0001);
        }
        $this->assertEquals([], Rasterizer::flattenBeziers([]));
    }
}
