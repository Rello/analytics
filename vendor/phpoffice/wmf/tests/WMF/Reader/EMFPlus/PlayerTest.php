<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\EMFPlus;

use GdImage;
use PhpOffice\WMF\Exception\WMFException;
use PhpOffice\WMF\Reader\EMFPlus\Player;
use PhpOffice\WMF\Renderer\FontResolver;
use PhpOffice\WMF\Renderer\GD as Renderer;
use PHPUnit\Framework\TestCase;

class PlayerTest extends TestCase
{
    private const VERSION = 0xDBC01002;
    private const RED = 0xFFFF0000;

    /**
     * Returns an EMF+ record
     */
    private function record(int $type, int $flags, string $data = ''): string
    {
        return pack('v2V2', $type, $flags, 12 + strlen($data), strlen($data)) . $data;
    }

    private function header(): string
    {
        return $this->record(Player::EMFPLUS_HEADER, 1, pack('V4', self::VERSION, 1, 96, 96));
    }

    /**
     * Fills a rectangle with a solid color
     */
    private function fillRect(float $x, float $y, float $width, float $height, int $argb = self::RED): string
    {
        return $this->record(Player::EMFPLUS_FILL_RECTS, 0x8000, pack('V2', $argb, 1) . pack('g4', $x, $y, $width, $height));
    }

    /**
     * Plays records on a renderer of 20x20 pixels, and returns the image
     *
     * @phpstan-ignore-next-line
     *
     * @return GdImage|resource
     */
    private function play(string $records)
    {
        $renderer = new Renderer(20, 20);
        (new Player($renderer))->play($this->header() . $records);

        return $renderer->render();
    }

    /**
     * @phpstan-ignore-next-line
     *
     * @param GdImage|resource $image
     */
    private function assertColorAt($image, int $x, int $y, int $expected): void
    {
        $this->assertEquals(sprintf('%06X', $expected), sprintf('%06X', imagecolorat($image, $x, $y) & 0xFFFFFF), sprintf('Color at (%d, %d)', $x, $y));
    }

    public function testFillRects(): void
    {
        $image = $this->play($this->fillRect(5, 5, 10, 10));

        $this->assertColorAt($image, 10, 10, 0xFF0000);
        $this->assertColorAt($image, 2, 2, 0xFFFFFF);
    }

    public function testAlpha(): void
    {
        // Half transparent red on white (the alpha channel of GD has 7 bits)
        $image = $this->play($this->fillRect(0, 0, 20, 20, 0x80FF0000));
        $color = imagecolorat($image, 10, 10);

        $this->assertEquals(0xFF, ($color >> 16) & 0xFF);
        $this->assertEqualsWithDelta(0x7F, ($color >> 8) & 0xFF, 2);
        $this->assertEqualsWithDelta(0x7F, $color & 0xFF, 2);
    }

    public function testWorldTransform(): void
    {
        $image = $this->play(
            $this->record(Player::EMFPLUS_SET_WORLD_TRANSFORM, 0, pack('g6', 2, 0, 0, 2, 0, 0))
            . $this->fillRect(5, 5, 5, 5)
        );

        $this->assertColorAt($image, 15, 15, 0xFF0000);
        $this->assertColorAt($image, 8, 8, 0xFFFFFF);
    }

    public function testPageTransform(): void
    {
        // UnitInch with a scale of 1/96 : 1 unit = 1 pixel at 96 DPI
        $image = $this->play(
            $this->record(Player::EMFPLUS_SET_PAGE_TRANSFORM, 4, pack('g', 1 / 96))
            . $this->fillRect(10, 10, 10, 10)
        );

        $this->assertColorAt($image, 15, 15, 0xFF0000);
        $this->assertColorAt($image, 5, 5, 0xFFFFFF);
    }

    public function testSaveRestore(): void
    {
        $image = $this->play(
            $this->record(Player::EMFPLUS_SAVE, 0, pack('V', 1))
            . $this->record(Player::EMFPLUS_SET_WORLD_TRANSFORM, 0, pack('g6', 1, 0, 0, 1, 10, 10))
            . $this->record(Player::EMFPLUS_RESTORE, 0, pack('V', 1))
            . $this->fillRect(0, 0, 5, 5)
        );

        $this->assertColorAt($image, 2, 2, 0xFF0000);
        $this->assertColorAt($image, 12, 12, 0xFFFFFF);
    }

    public function testClipRect(): void
    {
        // Intersect (1) : only the left half is drawn
        $image = $this->play(
            $this->record(Player::EMFPLUS_SET_CLIP_RECT, 0x0100, pack('g4', 0, 0, 10, 20))
            . $this->fillRect(0, 0, 20, 20)
            . $this->record(Player::EMFPLUS_RESET_CLIP, 0)
            . $this->fillRect(0, 18, 20, 2, 0xFF0000FF)
        );

        $this->assertColorAt($image, 5, 10, 0xFF0000);
        $this->assertColorAt($image, 15, 10, 0xFFFFFF);
        $this->assertColorAt($image, 15, 19, 0x0000FF);
    }

    public function testFillPath(): void
    {
        // Path object (id 1) : triangle above the diagonal
        $path = pack('V2v2', self::VERSION, 3, 0, 0) . pack('g6', 0, 0, 20, 0, 20, 20) . pack('C3x', 0, 1, 0x81);
        $image = $this->play(
            $this->record(Player::EMFPLUS_OBJECT, 0x0301, $path)
            . $this->record(Player::EMFPLUS_FILL_PATH, 0x8001, pack('V', 0xFF00FF00))
        );

        $this->assertColorAt($image, 17, 3, 0x00FF00);
        $this->assertColorAt($image, 3, 17, 0xFFFFFF);
    }

    public function testDrawLines(): void
    {
        // Pen object (id 0) : black, 2 pixels wide
        $pen = pack('V4', self::VERSION, 0, 0, 2) . pack('g', 2) . pack('V3', self::VERSION, 0, 0xFF000000);
        $image = $this->play(
            $this->record(Player::EMFPLUS_OBJECT, 0x0200, $pen)
            . $this->record(Player::EMFPLUS_DRAW_LINES, 0x0000, pack('V', 2) . pack('g4', 0, 10, 20, 10))
        );

        $this->assertColorAt($image, 10, 10, 0x000000);
        $this->assertColorAt($image, 10, 3, 0xFFFFFF);
    }

    public function testFillEllipse(): void
    {
        $image = $this->play($this->record(Player::EMFPLUS_FILL_ELLIPSE, 0x8000, pack('V', self::RED) . pack('g4', 0, 0, 20, 20)));

        $this->assertColorAt($image, 10, 10, 0xFF0000);
        $this->assertColorAt($image, 1, 1, 0xFFFFFF);
    }

    public function testGetDC(): void
    {
        $player = new Player(new Renderer(20, 20));

        $this->assertTrue($player->play($this->header() . $this->record(Player::EMFPLUS_GET_DC, 0)));
        $this->assertFalse($player->play($this->record(Player::EMFPLUS_GET_DC, 0) . $this->fillRect(0, 0, 1, 1)));
    }

    public function testRecordNotImplemented(): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : EMF+ record not implemented : 0x40ff');

        $this->play($this->record(0x40FF, 0));
    }

    public function testInvalidRecordSize(): void
    {
        $this->expectException(WMFException::class);

        $this->play(pack('v2V2', Player::EMFPLUS_CLEAR, 0, 4, 0));
    }

    /**
     * Returns the number of rows containing dark pixels
     *
     * @phpstan-ignore-next-line
     *
     * @param GdImage|resource $image
     */
    private function countDarkRows($image): int
    {
        $rows = 0;
        for ($y = 0; $y < imagesy($image); ++$y) {
            for ($x = 0; $x < imagesx($image); ++$x) {
                if ((imagecolorat($image, $x, $y) & 0xFF) < 0x80) {
                    ++$rows;
                    break;
                }
            }
        }

        return $rows;
    }

    public function testDrawStringWrapped(): void
    {
        if ((new FontResolver())->resolve(['face' => 'Arial', 'weight' => 400, 'italic' => false, 'pitchAndFamily' => 0]) === null) {
            $this->markTestSkipped('No font available');
        }
        // Font object (id 0) : Arial, 10 pixels
        $font = pack('V', self::VERSION) . pack('g', 10) . pack('V', 2) . pack('l', 0) . pack('V2', 0, 5) . "A\0r\0i\0a\0l\0";
        $text = 'aaa bbb ccc ddd';
        $drawString = function (float $width) use ($text): string {
            return $this->record(
                Player::EMFPLUS_DRAW_STRING,
                0x8000,
                pack('V3', 0xFF000000, 0xFFFFFFFF, strlen($text)) . pack('g4', 0, 0, $width, $width > 0 ? 100 : 0) . iconv('UTF-8', 'UTF-16LE', $text)
            );
        };

        $renderer = new Renderer(100, 100);
        (new Player($renderer))->play($this->header() . $this->record(Player::EMFPLUS_OBJECT, 0x0600, $font) . $drawString(0));
        $singleLine = $this->countDarkRows($renderer->render());

        // The layout rectangle is narrow : the text is wrapped
        $renderer = new Renderer(100, 100);
        (new Player($renderer))->play($this->header() . $this->record(Player::EMFPLUS_OBJECT, 0x0600, $font) . $drawString(25));
        $wrapped = $this->countDarkRows($renderer->render());

        $this->assertGreaterThan(0, $singleLine);
        $this->assertGreaterThan(2 * $singleLine, $wrapped);
    }
}
