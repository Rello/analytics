<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Renderer;

use PhpOffice\WMF\Exception\WMFException;
use PhpOffice\WMF\Renderer\Bitmap;
use PHPUnit\Framework\TestCase;

class BitmapTest extends TestCase
{
    /**
     * Returns a packed DIB (BITMAPINFOHEADER + color table + bits)
     */
    private function getPackedDIB(int $width, int $height, int $bitCount, string $palette, string $bits, int $colors = 0): string
    {
        return pack('VllvvVVllVV', 40, $width, $height, 1, $bitCount, 0, strlen($bits), 0, 0, $colors, 0) . $palette . $bits;
    }

    public function testReadPackedDIB24(): void
    {
        // Bottom-up 2x2 bitmap : rows are padded to 4 bytes, colors are BGR
        $bits = "\x00\x00\xFF\x00\xFF\x00\0\0"   // Bottom row : red, green
            . "\xFF\x00\x00\xFF\xFF\xFF\0\0";    // Top row : blue, white
        $bitmap = Bitmap::readPackedDIB($this->getPackedDIB(2, 2, 24, '', $bits));

        $this->assertEquals([
            'width' => 2,
            'height' => 2,
            'pixels' => [
                [0x0000FF, 0xFFFFFF],
                [0xFF0000, 0x00FF00],
            ],
        ], $bitmap);
    }

    public function testReadPackedDIBTopDown(): void
    {
        $bits = "\x00\x00\xFF\0\xFF\x00\x00\0";
        $bitmap = Bitmap::readPackedDIB($this->getPackedDIB(1, -2, 24, '', $bits));

        $this->assertEquals([[0xFF0000], [0x0000FF]], $bitmap['pixels']);
    }

    public function testReadPackedDIBPalette(): void
    {
        // 1-bit bitmap with a palette of 2 colors (red & blue)
        $palette = "\x00\x00\xFF\x00\xFF\x00\x00\x00";
        $bitmap = Bitmap::readPackedDIB($this->getPackedDIB(3, 1, 1, $palette, "\xA0\0\0\0", 2));

        $this->assertEquals([[0x0000FF, 0xFF0000, 0x0000FF]], $bitmap['pixels']);
    }

    public function testReadPackedDIBPaletteIndexes(): void
    {
        // DIB_PAL_COLORS : the palette is not known, a gray scale is used
        $bitmap = Bitmap::readPackedDIB($this->getPackedDIB(2, 1, 1, "\0\0\1\0", "\x40\0\0\0", 2), true);

        $this->assertEquals([[0x000000, 0xFFFFFF]], $bitmap['pixels']);
    }

    public function testReadDIBRLE8(): void
    {
        // 4x2 bitmap, colors : red (0), green (1), blue (2)
        $palette = "\x00\x00\xFF\x00\x00\xFF\x00\x00\xFF\x00\x00\x00";
        // Bottom row : 2 red pixels, then absolute mode (green, blue, padding) ; end of line
        // Top row : delta (2, 0), then 1 green pixel ; end of bitmap
        $bits = "\x02\x00\x00\x03\x01\x02\x00\x00\x00\x00\x00\x02\x02\x00\x01\x01\x00\x01";
        $dib = pack('VllvvVVllVV', 40, 5, 2, 1, 8, 1, strlen($bits), 0, 0, 3, 0) . $palette . $bits;
        $bitmap = Bitmap::readDIB($dib, 0, 52);

        $this->assertNotNull($bitmap);
        $this->assertEquals([
            // Skipped pixels are transparent
            [Bitmap::TRANSPARENT, Bitmap::TRANSPARENT, 0x00FF00, Bitmap::TRANSPARENT, Bitmap::TRANSPARENT],
            [0xFF0000, 0xFF0000, 0x00FF00, 0x0000FF, 0xFF0000],
        ], $bitmap['pixels']);
    }

    public function testReadDIBRLE4(): void
    {
        // 6x1 bitmap, colors : red (0), green (1)
        $palette = "\x00\x00\xFF\x00\x00\xFF\x00\x00";
        // 3 pixels alternating red & green, then absolute mode (green, red, green) ; end of bitmap
        $bits = "\x03\x01\x00\x03\x10\x10\x00\x01";
        $dib = pack('VllvvVVllVV', 40, 6, 1, 1, 4, 2, strlen($bits), 0, 0, 2, 0) . $palette . $bits;
        $bitmap = Bitmap::readDIB($dib, 0, 48);

        $this->assertNotNull($bitmap);
        $this->assertEquals([[0xFF0000, 0x00FF00, 0xFF0000, 0x00FF00, 0xFF0000, 0x00FF00]], $bitmap['pixels']);
    }

    public function testReadDIBNotSupported(): void
    {
        // BI_RLE8 is only valid for 8 bits per pixel
        $dib = pack('VllvvVVllVV', 40, 1, 1, 1, 4, 1, 0, 0, 0, 0, 0);
        $this->assertNull(Bitmap::readDIB($dib, 0, 40));
        // Empty bitmap
        $this->assertNull(Bitmap::readPackedDIB($this->getPackedDIB(0, 1, 24, '', '')));
        // BITMAPCOREHEADER
        $this->assertNull(Bitmap::readPackedDIB(pack('VvvvV', 12, 1, 1, 1, 24) . str_repeat("\0", 28)));
    }

    /**
     * @return array<array<callable>>
     */
    public static function dataProviderTruncated(): array
    {
        return [
            [function () {
                return Bitmap::readDIB(str_repeat("\0", 20), 0, 40);
            }],
            [function () {
                return Bitmap::readPackedDIB(pack('VvvvV', 12, 1, 1, 1, 24));
            }],
            [function () {
                return Bitmap::readBitmap16("\0\0\1\0");
            }],
        ];
    }

    /**
     * @dataProvider dataProviderTruncated
     */
    public function testTruncated(callable $read): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : Invalid file : truncated bitmap');

        $read();
    }

    public function testReadDIBImage(): void
    {
        // BI_PNG : the bits are a PNG image
        $image = imagecreatetruecolor(2, 1);
        imagesetpixel($image, 0, 0, 0x112233);
        imagesetpixel($image, 1, 0, 0x445566);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $dib = pack('VllvvVVllVV', 40, 2, 1, 1, 0, 5, strlen($png), 0, 0, 0, 0) . $png;
        $this->assertEquals([[0x112233, 0x445566]], Bitmap::readDIB($dib, 0, 40, 40, strlen($png))['pixels']);

        // A corrupted image is empty
        $dib = pack('VllvvVVllVV', 40, 2, 1, 1, 0, 5, 4, 0, 0, 0, 0) . 'fake';
        $this->assertEquals(['width' => 0, 'height' => 0, 'pixels' => []], Bitmap::readDIB($dib, 0, 40, 40, 4));
    }

    public function testReadBitmap16(): void
    {
        // Monochrome 3x2 bitmap, rows of 2 bytes
        $bitmap = Bitmap::readBitmap16(pack('vvvvCC', 0, 3, 2, 2, 1, 1) . "\xA0\0\x40\0");

        $this->assertEquals([
            [0xFFFFFF, 0x000000, 0xFFFFFF],
            [0x000000, 0xFFFFFF, 0x000000],
        ], $bitmap['pixels']);
        $this->assertNull(Bitmap::readBitmap16(pack('vvvvCC', 0, 0, 2, 2, 1, 1)));
    }

    public function testGetAverageColor(): void
    {
        $bitmap = ['width' => 2, 'height' => 1, 'pixels' => [[0xFF0000, 0x0000FF]]];

        $this->assertEquals([128, 0, 128], Bitmap::getAverageColor($bitmap));
    }
}
