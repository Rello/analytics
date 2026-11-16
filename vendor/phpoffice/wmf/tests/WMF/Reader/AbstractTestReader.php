<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader;

use Imagick;
use PHPUnit\Framework\TestCase;

class AbstractTestReader extends TestCase
{
    public function getResourceDir(): string
    {
        return dirname(__DIR__, 2) . '/resources/';
    }

    public function assertImageCompare(string $expectedFile, string $outputFile, float $threshold = 0): void
    {
        $imExpected = new Imagick($expectedFile);
        $imOutput = new Imagick($outputFile);

        $result = $imExpected->compareImages($imOutput, Imagick::METRIC_MEANSQUAREERROR);
        $this->assertLessThanOrEqual($threshold, $result[1]);
    }

    /**
     * @return array<array<string>>
     */
    public static function dataProviderFilesWMF(): array
    {
        return [
            [
                'burger.wmf',
            ],
            [
                'chicken.wmf',
            ],
            [
                'fish.wmf',
            ],
            [
                'vegetable.wmf',
            ],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function dataProviderFilesWMFNotImplemented(): array
    {
        return [
            [
                'test_libuemf.wmf',
            ],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function dataProviderMediaTypeWMF(): array
    {
        return [
            [
                'gif',
                'image/gif',
            ],
            [
                'jpg',
                'image/jpeg',
            ],
            [
                'jpeg',
                'image/jpeg',
            ],
            [
                'png',
                'image/png',
            ],
            [
                'webp',
                'image/webp',
            ],
            [
                'wbmp',
                'image/vnd.wap.wbmp',
            ],
            [
                'wmf',
                'image/x-wmf',
            ],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function dataProviderFilesEMF(): array
    {
        return [
            [
                'computer_mail.emf',
            ],
            [
                'inkscape_shapes.emf',
            ],
        ];
    }

    /**
     * @return array<array<string>>
     */
    public static function dataProviderMediaTypeEMF(): array
    {
        return [
            [
                'gif',
                'image/gif',
            ],
            [
                'jpg',
                'image/jpeg',
            ],
            [
                'jpeg',
                'image/jpeg',
            ],
            [
                'png',
                'image/png',
            ],
            [
                'webp',
                'image/webp',
            ],
            [
                'wbmp',
                'image/vnd.wap.wbmp',
            ],
        ];
    }

    /**
     * Returns an EMF file (header of computer_mail.emf) with a record not implemented (EMR_ALPHABLEND)
     */
    public function getContentEMFNotImplemented(): string
    {
        $content = file_get_contents($this->getResourceDir() . 'computer_mail.emf');
        list(, $headerSize) = unpack('V', substr($content, 4, 4));

        return substr($content, 0, $headerSize)
            . pack('V2', 0x72, 8)
            . pack('V5', 0x0E, 20, 0, 16, 20);
    }

    /**
     * Returns a WMF file (headers of burger.wmf) with a record not implemented (META_DRAWTEXT)
     */
    public function getContentWMFNotImplemented(): string
    {
        $content = file_get_contents($this->getResourceDir() . 'burger.wmf');

        return substr($content, 0, 22 + 18)
            . pack('Vv', 3, 0x062F)
            . pack('Vv', 3, 0x0000);
    }

    /**
     * Returns a 2x1 DIB (bitmap info & bits) : the first pixel uses the color 0, the second one the color 1
     *
     * The color table contains red & blue, or indexes in the palette of the file (DIB_PAL_COLORS)
     */
    private function getDIB(int $compression, bool $hasPaletteIndexes): string
    {
        $colorTable = $hasPaletteIndexes ? "\x00\x00\x01\x00" : "\x00\x00\xFF\x00\xFF\x00\x00\x00";

        return pack('VllvvVVllVV', 40, 2, 1, 1, 1, $compression, 4, 0, 0, 2, 0) . $colorTable . "\x40\0\0\0";
    }

    /**
     * Returns an EMF file (header of inkscape_shapes.emf) with a EMR_STRETCHDIBITS record covering the image
     *
     * @param int $compression BI_RGB (0) or a compression not supported by monochrome bitmaps (BI_RLE8 : 1)
     * @param int $usage DIB_RGB_COLORS (0) or DIB_PAL_COLORS (1)
     */
    public function getContentEMFWithBitmap(int $compression, int $usage): string
    {
        $content = file_get_contents($this->getResourceDir() . 'inkscape_shapes.emf');
        list(, $headerSize) = unpack('V', substr($content, 4, 4));

        $dib = $this->getDIB($compression, $usage == 1);
        $bmiSize = strlen($dib) - 4;
        $record = pack('l4', 0, 0, 2503, 1889)
            // xDest, yDest, xSrc, ySrc, cxSrc, cySrc
            . pack('l6', 0, 0, 0, 0, 2, 1)
            // offBmi, cbBmi, offBits, cbBits, usage, SRCCOPY
            . pack('V6', 80, $bmiSize, 80 + $bmiSize, 4, $usage, 0x00CC0020)
            // cxDest, cyDest
            . pack('l2', 2504, 1890)
            . $dib;

        return substr($content, 0, $headerSize)
            . pack('V2', 0x51, 8 + strlen($record)) . $record
            . pack('V5', 0x0E, 20, 0, 16, 20);
    }

    /**
     * Colors of the rows of the 8x8 DIB of getContentEMFWithDIBitsToDevice(), from top to bottom
     */
    public const DIBITS_COLORS = [0xFF0000, 0xFF0000, 0x00FF00, 0x00FF00, 0x0000FF, 0x0000FF, 0xFFFF00, 0xFFFF00];

    /**
     * Returns a 8x8 EMF file (1 logical unit = 1 pixel) drawing a 8x8 DIB with EMR_SETDIBITSTODEVICE records
     *
     * The rows of the DIB use the colors DIBITS_COLORS. Each record contains a band of $scansPerBand scan lines.
     *
     * @param bool $isTopDown If the DIB is top-down (else bottom-up)
     * @param int $scansPerBand Number of scan lines of each record
     * @param int $ySrc Y of the source rectangle (from the bottom of a bottom-up DIB, from the top of a top-down DIB)
     * @param int $cySrc Height of the source rectangle
     */
    public function getContentEMFWithDIBitsToDevice(bool $isTopDown, int $scansPerBand, int $ySrc = 0, int $cySrc = 8): string
    {
        $size = 8;
        $records = '';
        $count = 0;
        for ($startScan = 0; $startScan < $size; $startScan += $scansPerBand) {
            $scans = min($scansPerBand, $size - $startScan);
            $bits = '';
            for ($scan = $startScan; $scan < $startScan + $scans; ++$scan) {
                // The scan line 0 is the bottom row of a bottom-up DIB
                $color = self::DIBITS_COLORS[$isTopDown ? $scan : $size - 1 - $scan];
                $bits .= str_repeat(pack('CCC', $color & 0xFF, ($color >> 8) & 0xFF, $color >> 16), $size);
            }
            $bmi = pack('VllvvVVllVV', 40, $size, $isTopDown ? -$size : $size, 1, 24, 0, 0, 0, 0, 0, 0);
            $record = pack('l4', 0, 0, $size - 1, $size - 1)
                // xDest, yDest, xSrc, ySrc, cxSrc, cySrc
                . pack('l6', 0, 0, 0, $ySrc, $size, $cySrc)
                // offBmi, cbBmi, offBits, cbBits, usage, iStartScan, cScans
                . pack('V7', 76, strlen($bmi), 76 + strlen($bmi), strlen($bits), 0, $startScan, $scans)
                . $bmi . $bits;
            $records .= pack('V2', 0x50, 8 + strlen($record)) . $record;
            ++$count;
        }
        $records .= pack('V5', 0x0E, 20, 0, 16, 20);

        // Bounds, Frame (0.01 mm at 96 DPI), signature, version, bytes, records, handles, reserved, description & palette
        $header = pack('l4', 0, 0, $size - 1, $size - 1)
            . pack('l4', 0, 0, (int) round($size * 2540 / 96), (int) round($size * 2540 / 96))
            . pack('VVVVvvVVV', 0x464D4520, 0x10000, 88 + strlen($records), $count + 2, 1, 0, 0, 0, 0)
            // Device (pixels) & Millimeters
            . pack('l4', 1024, 768, 271, 203);

        return pack('V2', 0x01, 88) . $header . $records;
    }

    /**
     * Returns a WMF file (headers of burger.wmf) with a META_STRETCHDIB record covering the image
     *
     * @param int $compression BI_RGB (0) or a compression not supported by monochrome bitmaps (BI_RLE8 : 1)
     * @param int $usage DIB_RGB_COLORS (0) or DIB_PAL_COLORS (1)
     */
    public function getContentWMFWithBitmap(int $compression, int $usage): string
    {
        $content = file_get_contents($this->getResourceDir() . 'burger.wmf');

        // The window of burger.wmf : origin (-1225, 1137), extent (2290, -2035)
        $params = pack('Vv', 0x00CC0020, $usage)
            // srcHeight, srcWidth, ySrc, xSrc, destHeight, destWidth, yDest, xDest
            . pack('v8', 1, 2, 0, 0, -2035 & 0xFFFF, 2290, 1137, -1225 & 0xFFFF)
            . $this->getDIB($compression, $usage == 1);

        return substr($content, 0, 22 + 18)
            . pack('Vv', 3 + strlen($params) / 2, 0x0F43) . $params
            . pack('Vv', 3, 0x0000);
    }

    public function assertMimeType(string $filename, string $expectedMimeType): void
    {
        // Use GD
        $gdInfo = getimagesize($filename);
        if (is_array($gdInfo)) {
            $this->assertEquals($expectedMimeType, $gdInfo['mime']);

            return;
        }

        // Use Imagick
        $this->assertEquals($expectedMimeType, (new Imagick($filename))->getImageMimeType());
    }
}
