<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Renderer;

use GdImage;
use PhpOffice\WMF\Renderer\FontResolver;
use PhpOffice\WMF\Renderer\GD;
use PhpOffice\WMF\Renderer\Rasterizer;
use PHPUnit\Framework\TestCase;

class GDTest extends TestCase
{
    /**
     * @phpstan-ignore-next-line
     *
     * @param GdImage|resource $image
     */
    private function assertColorAt($image, int $x, int $y, int $expected): void
    {
        $this->assertEquals(sprintf('%06X', $expected), sprintf('%06X', imagecolorat($image, $x, $y) & 0xFFFFFF), sprintf('Color at (%d, %d)', $x, $y));
    }

    /**
     * @param array<int> $color
     *
     * @return array<string, mixed>
     */
    private function getBrush(array $color): array
    {
        return ['type' => 'brush', 'style' => 0, 'color' => $color];
    }

    public function testRender(): void
    {
        $image = (new GD(20, 10))->render();

        $this->assertEquals(20, imagesx($image));
        $this->assertEquals(10, imagesy($image));
        $this->assertColorAt($image, 5, 5, 0xFFFFFF);
    }

    public function testBigImageIsScaledDown(): void
    {
        $image = (new GD(8000, 4000))->render();

        $this->assertLessThanOrEqual(16000000, imagesx($image) * imagesy($image));
        $this->assertEqualsWithDelta(2, imagesx($image) / imagesy($image), 0.01);
    }

    public function testRectangle(): void
    {
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject($this->getBrush([255, 0, 0]))
            ->rectangle(5, 5, 15, 15);
        $image = $renderer->render();

        $this->assertColorAt($image, 10, 10, 0xFF0000);
        $this->assertColorAt($image, 2, 2, 0xFFFFFF);
        $this->assertColorAt($image, 17, 17, 0xFFFFFF);
    }

    public function testMapping(): void
    {
        // The window (0, 0) - (200, 100) is mapped to the image, with the y axis going up
        $renderer = new GD(20, 10);
        $renderer->setMapMode(GD::MM_ANISOTROPIC)
            ->setWindowOrg(0, 100)
            ->setWindowExt(200, -100)
            ->setViewportExt(20, 10)
            ->selectObject(GD::getStockObject(8))
            ->selectObject($this->getBrush([0, 0, 255]))
            // Bottom left quarter
            ->rectangle(0, 0, 100, 50);
        $image = $renderer->render();

        $this->assertColorAt($image, 5, 7, 0x0000FF);
        $this->assertColorAt($image, 5, 2, 0xFFFFFF);
        $this->assertColorAt($image, 15, 7, 0xFFFFFF);
    }

    public function testSaveRestoreDC(): void
    {
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject($this->getBrush([255, 0, 0]))
            ->saveDC()
            ->selectObject($this->getBrush([0, 255, 0]))
            ->restoreDC(-1)
            ->rectangle(0, 0, 20, 20);

        $this->assertColorAt($renderer->render(), 10, 10, 0xFF0000);
    }

    public function testClipping(): void
    {
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject($this->getBrush([255, 0, 0]))
            ->intersectClipRect(0, 0, 10, 20)
            ->excludeClipRect(0, 0, 10, 5)
            ->rectangle(0, 0, 20, 20);
        $image = $renderer->render();

        $this->assertColorAt($image, 5, 10, 0xFF0000);
        $this->assertColorAt($image, 5, 2, 0xFFFFFF);
        $this->assertColorAt($image, 15, 10, 0xFFFFFF);
    }

    public function testClipRegionXOR(): void
    {
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject($this->getBrush([255, 0, 0]))
            ->selectClipRegion([[0, 0, 10, 20]], Rasterizer::RGN_COPY, false)
            ->selectClipRegion([[5, 0, 15, 20]], Rasterizer::RGN_XOR, false)
            ->rectangle(0, 0, 20, 20);
        $image = $renderer->render();

        $this->assertColorAt($image, 2, 10, 0xFF0000);
        $this->assertColorAt($image, 7, 10, 0xFFFFFF);
        $this->assertColorAt($image, 12, 10, 0xFF0000);
        $this->assertColorAt($image, 17, 10, 0xFFFFFF);
    }

    public function testPath(): void
    {
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject($this->getBrush([0, 255, 0]))
            ->beginPath()
            ->moveTo(0, 0)
            ->polylineTo([[20, 0], [20, 10]])
            ->closeFigure()
            ->endPath()
            ->fillPath();
        $image = $renderer->render();

        // Triangle above the diagonal
        $this->assertColorAt($image, 18, 2, 0x00FF00);
        $this->assertColorAt($image, 2, 15, 0xFFFFFF);
    }

    public function testStroke(): void
    {
        $renderer = new GD(20, 20);
        $renderer->selectObject(['type' => 'pen', 'style' => 0, 'width' => 4, 'geometric' => true, 'color' => [0, 0, 0]])
            ->moveTo(0, 10)
            ->lineTo(20, 10);
        $image = $renderer->render();

        $this->assertColorAt($image, 10, 9, 0x000000);
        $this->assertColorAt($image, 10, 3, 0xFFFFFF);
    }

    public function testBitmap(): void
    {
        $renderer = new GD(20, 20);
        $renderer->drawBitmap(['width' => 2, 'height' => 1, 'pixels' => [[0xFF0000, 0x0000FF]]], 0, 0, 20, 20, 0, 0, 2, 1);
        $image = $renderer->render();

        $this->assertColorAt($image, 5, 10, 0xFF0000);
        $this->assertColorAt($image, 15, 10, 0x0000FF);
    }

    public function testGradientFill(): void
    {
        $renderer = new GD(20, 10);
        $renderer->gradientFill([[0, 0, [0, 0, 0]], [20, 10, [255, 255, 255]]], [[0, 1]], 0);
        $image = $renderer->render();

        $this->assertLessThan(0x20, imagecolorat($image, 0, 5) & 0xFF);
        $this->assertGreaterThan(0xE0, imagecolorat($image, 19, 5) & 0xFF);
    }

    public function testPatBlt(): void
    {
        $renderer = new GD(20, 20);
        $renderer->patBlt(0, 0, 10, 20, GD::ROP_BLACKNESS)
            ->patBlt(10, 0, 20, 20, GD::ROP_NOP);
        $image = $renderer->render();

        $this->assertColorAt($image, 5, 10, 0x000000);
        $this->assertColorAt($image, 15, 10, 0xFFFFFF);
    }

    public function testFloodFill(): void
    {
        $renderer = new GD(20, 20);
        $renderer->selectObject(['type' => 'pen', 'style' => 0, 'width' => 2, 'geometric' => true, 'color' => [0, 0, 0]])
            ->selectObject(GD::getStockObject(5))
            ->rectangle(2, 2, 18, 18)
            ->selectObject($this->getBrush([255, 0, 0]))
            ->floodFill(10, 10, [0, 0, 0], false);
        $image = $renderer->render();

        $this->assertColorAt($image, 10, 10, 0xFF0000);
        $this->assertColorAt($image, 0, 0, 0xFFFFFF);
    }

    public function testStockObjects(): void
    {
        $this->assertEquals('brush', GD::getStockObject(0)['type']);
        $this->assertEquals('pen', GD::getStockObject(7)['type']);
        $this->assertEquals('font', GD::getStockObject(13)['type']);
        $this->assertNull(GD::getStockObject(9));
    }

    public function testHatchedBrush(): void
    {
        // HS_HORIZONTAL : red lines every 8 pixels on the background color (OPAQUE background mode)
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject(['type' => 'brush', 'style' => 2, 'color' => [255, 0, 0], 'hatch' => 0])
            ->setBkColor([0, 0, 255])
            ->rectangle(0, 0, 20, 20);
        $image = $renderer->render();

        $this->assertColorAt($image, 5, 8, 0xFF0000);
        $this->assertColorAt($image, 5, 10, 0x0000FF);

        // TRANSPARENT background mode
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject(['type' => 'brush', 'style' => 2, 'color' => [255, 0, 0], 'hatch' => 1])
            ->setBkMode(1)
            ->rectangle(0, 0, 20, 20);
        $image = $renderer->render();

        $this->assertColorAt($image, 8, 5, 0xFF0000);
        $this->assertColorAt($image, 10, 5, 0xFFFFFF);
    }

    public function testDashedPen(): void
    {
        // Dashes of 4 pixels, spaces of 4 pixels
        $renderer = new GD(20, 20);
        $renderer->selectObject(['type' => 'pen', 'style' => 0, 'width' => 0, 'geometric' => false, 'color' => [0, 0, 0], 'dashes' => [4, 4]])
            // In the middle of a row of pixels
            ->moveTo(0, 10.5)
            ->lineTo(20, 10.5);
        $image = $renderer->render();

        $this->assertColorAt($image, 1, 10, 0x000000);
        $this->assertColorAt($image, 6, 10, 0xFFFFFF);
        $this->assertColorAt($image, 9, 10, 0x000000);
    }

    public function testPatternBrush(): void
    {
        // Pattern of 2x1 pixels (red & blue) : tiled from the origin of the image
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject(['type' => 'brush', 'style' => 3, 'color' => [128, 0, 128], 'pattern' => ['width' => 2, 'height' => 1, 'pixels' => [[0xFF0000, 0x0000FF]]]])
            ->rectangle(5, 5, 15, 15);
        $image = $renderer->render();

        $this->assertColorAt($image, 6, 10, 0xFF0000);
        $this->assertColorAt($image, 7, 10, 0x0000FF);
        $this->assertColorAt($image, 12, 7, 0xFF0000);
        $this->assertColorAt($image, 2, 2, 0xFFFFFF);
    }

    public function testCompoundPen(): void
    {
        // Two lines : the first quarter & the last quarter of the width
        $renderer = new GD(40, 40);
        $renderer->selectObject(['type' => 'pen', 'style' => 0, 'width' => 12, 'geometric' => true, 'color' => [0, 0, 0], 'compound' => [0, 0.25, 0.75, 1]])
            ->moveTo(0, 20)
            ->lineTo(40, 20);
        $image = $renderer->render();

        $this->assertColorAt($image, 20, 15, 0x000000);
        $this->assertColorAt($image, 20, 20, 0xFFFFFF);
        $this->assertColorAt($image, 20, 24, 0x000000);
        $this->assertColorAt($image, 20, 10, 0xFFFFFF);
    }

    public function testTextOutVerticalAdvances(): void
    {
        $font = ['type' => 'font', 'height' => -10, 'escapement' => 0, 'weight' => 400, 'italic' => false, 'underline' => false, 'strikeOut' => false, 'charset' => 0, 'pitchAndFamily' => 0x22, 'face' => 'Arial'];
        if ((new FontResolver())->resolve($font) === null) {
            $this->markTestSkipped('No font available');
        }
        // Returns the rows containing dark pixels
        $getRows = function (array $dy) use ($font): array {
            $renderer = new GD(60, 60);
            $renderer->selectObject($font)
                ->setBkMode(1)
                ->textOut(10, 30, 'II', [20, 20], 0, [0, 0, 0, 0], $dy);
            $image = $renderer->render();
            $rows = [];
            for ($y = 0; $y < 60; ++$y) {
                for ($x = 0; $x < 60; ++$x) {
                    if ((imagecolorat($image, $x, $y) & 0xFF) < 0x80) {
                        $rows[] = $y;
                        break;
                    }
                }
            }

            return $rows;
        };

        $rows = $getRows([]);
        // ETO_PDY : the second glyph is 20 units higher
        $shiftedRows = $getRows([20, 0]);
        $this->assertNotEmpty($rows);
        $this->assertEquals(min($rows) - 20, min($shiftedRows));
        $this->assertEquals(max($rows), max($shiftedRows));
    }

    public function testRasterOperations(): void
    {
        // SRCAND : the bitmap (red & white) is combined with the green background
        $renderer = new GD(20, 20);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject(['type' => 'brush', 'style' => 0, 'color' => [0, 255, 0]])
            ->rectangle(0, 0, 20, 20)
            ->drawBitmap(['width' => 2, 'height' => 1, 'pixels' => [[0xFF0000, 0xFFFFFF]]], 0, 0, 20, 20, 0, 0, 2, 1, 0x008800C6)
            // DSTINVERT on the bottom
            ->patBlt(0, 15, 20, 20, 0x00550009);
        $image = $renderer->render();

        $this->assertColorAt($image, 5, 5, 0x000000);
        $this->assertColorAt($image, 15, 5, 0x00FF00);
        $this->assertColorAt($image, 15, 18, 0xFF00FF);
    }

    public function testTransparentBackground(): void
    {
        $renderer = new GD(20, 20, 0, 0, 0, 0, null, null);
        $renderer->selectObject(GD::getStockObject(8))
            ->selectObject($this->getBrush([255, 0, 0]))
            ->rectangle(5, 5, 15, 15);
        $image = $renderer->render();

        $this->assertEquals(127, (imagecolorat($image, 1, 1) >> 24) & 0x7F);
        $this->assertEquals(0xFF0000, imagecolorat($image, 10, 10));

        // Raster operations use a white background
        $renderer = new GD(20, 20, 0, 0, 0, 0, null, null);
        $renderer->drawBitmap(['width' => 1, 'height' => 1, 'pixels' => [[0xFF0000]]], 0, 0, 20, 20, 0, 0, 1, 1, 0x008800C6);
        $this->assertEquals(0xFF0000, imagecolorat($renderer->render(), 10, 10));
    }

    public function testApplyRop(): void
    {
        $this->assertEquals(0x00FF00 & 0x0F0F0F, GD::applyRop(0x008800C6, 0, 0x0F0F0F, 0x00FF00));
        $this->assertEquals(0xFF00FF, GD::applyRop(0x00550009, 0, 0, 0x00FF00));
        // PSDPxax (generic evaluation) : D ^ (S & (P ^ D))
        $this->assertEquals(0x00FF00 ^ (0x0F0F0F & (0xFF0000 ^ 0x00FF00)), GD::applyRop(0x00E20746, 0xFF0000, 0x0F0F0F, 0x00FF00));

        $this->assertFalse(GD::isRopDependent(GD::ROP_PATCOPY, 1));
        $this->assertTrue(GD::isRopDependent(0x00550009, 1));
        $this->assertTrue(GD::isRopDependent(GD::ROP_SRCCOPY, 2));
    }

    public function testPenDashes(): void
    {
        $this->assertEquals([18, 6], GD::getPenDashes(1, true, 1));
        $this->assertEquals([3, 1], GD::getPenDashes(1, false, 4));
        $this->assertEquals([1, 1], GD::getPenDashes(8, true, 1));
        // PS_USERSTYLE : in pen widths for geometric pens
        $this->assertEquals([4, 2], GD::getPenDashes(7, false, 8, [32, 16]));
        $this->assertNull(GD::getPenDashes(0, true, 1));
        $this->assertNull(GD::getPenDashes(7, false, 8, [32]));
    }

    public function testHatchPatterns(): void
    {
        $this->assertTrue(GD::isHatchForeground(0, 3, 16));
        $this->assertFalse(GD::isHatchForeground(0, 3, 17));
        $this->assertTrue(GD::isHatchForeground(5, 3, 5));
        $this->assertTrue(GD::isHatchForeground(5, 3, 3));
    }
}
