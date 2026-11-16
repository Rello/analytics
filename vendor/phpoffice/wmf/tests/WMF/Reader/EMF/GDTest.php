<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\EMF;

use GdImage;
use PhpOffice\WMF\Exception\WMFException;
use PhpOffice\WMF\Reader\EMF\GD;
use Tests\PhpOffice\WMF\Reader\AbstractTestReader;

class GDTest extends AbstractTestReader
{
    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testLoad(string $file): void
    {
        $reader = new GD();
        $this->assertTrue($reader->load($this->getResourceDir() . $file));
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testLoadFromString(string $file): void
    {
        $reader = new GD();
        $this->assertTrue($reader->loadFromString(file_get_contents($this->getResourceDir() . $file)));
    }

    /**
     * @dataProvider dataProviderFilesWMF
     */
    public function testLoadWMF(string $file): void
    {
        $reader = new GD();
        $this->assertFalse($reader->load($this->getResourceDir() . $file));
        $this->assertFalse($reader->isEMF());
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testGetResource(string $file): void
    {
        $reader = new GD();
        $reader->load($this->getResourceDir() . $file);
        if (\PHP_VERSION_ID < 80000) {
            $this->assertIsResource($reader->getResource());
        } else {
            /* @phpstan-ignore-next-line */
            $this->assertInstanceOf(GdImage::class, $reader->getResource());
        }
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testOutput(string $file): void
    {
        $outputFile = $this->getResourceDir() . 'output_' . pathinfo($file, PATHINFO_FILENAME) . '.png';
        $similarFile = $this->getResourceDir() . pathinfo($file, PATHINFO_FILENAME) . '.png';

        $reader = new GD();
        $reader->load($this->getResourceDir() . $file);
        $this->assertTrue($reader->save($outputFile, 'png'));

        $this->assertImageCompare($outputFile, $similarFile, 0.02);

        @unlink($outputFile);
    }

    /**
     * @dataProvider dataProviderMediaTypeEMF
     */
    public function testSave(string $extension, string $mediatype): void
    {
        $outputFile = $this->getResourceDir() . 'output_save.' . $extension;

        $reader = new GD();
        $reader->load($this->getResourceDir() . 'computer_mail.emf');
        $reader->save($outputFile, $extension);
        $this->assertMimeType($outputFile, $mediatype);

        @unlink($outputFile);
    }

    public function testSaveEMF(): void
    {
        $file = $this->getResourceDir() . 'computer_mail.emf';
        $outputFile = $this->getResourceDir() . 'output_save.emf';

        $reader = new GD();
        $reader->load($file);
        $this->assertTrue($reader->save($outputFile, 'emf'));
        $this->assertFileEquals($file, $outputFile);

        @unlink($outputFile);
    }

    public function testSaveWithException(): void
    {
        $file = 'computer_mail.emf';
        $outputFile = $this->getResourceDir() . 'output_' . pathinfo($file, PATHINFO_FILENAME) . '.png';

        $this->expectException(WMFException::class);

        $reader = new GD();
        $reader->load($this->getResourceDir() . $file);
        $reader->save($outputFile, 'notanextension');
    }

    public function testSaveWithoutException(): void
    {
        $file = 'computer_mail.emf';
        $outputFile = $this->getResourceDir() . 'output_' . pathinfo($file, PATHINFO_FILENAME) . '.png';

        $reader = new GD();
        $reader->enableExceptions(false);
        $reader->load($this->getResourceDir() . $file);
        $this->assertFalse($reader->save($outputFile, 'notanextension'));
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testIsEMF(string $file): void
    {
        $reader = new GD();
        $reader->load($this->getResourceDir() . $file);
        $this->assertTrue($reader->isEMF());
    }

    public function testMediaType(): void
    {
        $reader = new GD();
        $this->assertEquals('image/emf', $reader->getMediaType());
    }

    public function testNotImplementedWithExceptions(): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : Function not implemented : 0x0072');

        $reader = new GD();
        $reader->loadFromString($this->getContentEMFNotImplemented());
    }

    public function testNotImplementedWithoutExceptions(): void
    {
        $reader = new GD();
        $reader->enableExceptions(false);
        $this->assertFalse($reader->loadFromString($this->getContentEMFNotImplemented()));
    }

    /**
     * @return array<array<int>>
     */
    public static function dataProviderBitmapColors(): array
    {
        return [
            // DIB_RGB_COLORS : the colors of the color table (red & blue)
            [0, 0xFF0000, 0x0000FF],
            // DIB_PAL_COLORS : the palette of the file is not known, a gray scale is used
            [1, 0x000000, 0xFFFFFF],
        ];
    }

    /**
     * @dataProvider dataProviderBitmapColors
     */
    public function testBitmapColors(int $usage, int $leftColor, int $rightColor): void
    {
        $reader = new GD();
        $this->assertTrue($reader->loadFromString($this->getContentEMFWithBitmap(0, $usage)));
        $image = $reader->getResource();

        $this->assertEquals($leftColor, imagecolorat($image, (int) (imagesx($image) / 4), (int) (imagesy($image) / 2)) & 0xFFFFFF);
        $this->assertEquals($rightColor, imagecolorat($image, (int) (imagesx($image) * 3 / 4), (int) (imagesy($image) / 2)) & 0xFFFFFF);
    }

    public function testBitmapNotSupportedWithExceptions(): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : Bitmap not supported : 0x0051');

        $reader = new GD();
        $reader->loadFromString($this->getContentEMFWithBitmap(1, 0));
    }

    public function testBitmapNotSupportedWithoutExceptions(): void
    {
        $reader = new GD();
        $reader->enableExceptions(false);
        $this->assertFalse($reader->loadFromString($this->getContentEMFWithBitmap(1, 0)));
    }

    /**
     * EMF file of PhpSpreadsheet issue #274 : a bitmap drawn by EMR_SETDIBITSTODEVICE records of 1 scan line,
     * over a black background (EMR_BITBLT with PATCOPY)
     *
     * @see https://github.com/PHPOffice/PhpSpreadsheet/issues/274
     */
    public function testSetDIBitsToDeviceByScanLine(): void
    {
        $file = $this->getResourceDir() . 'phpspreadsheet/issue274.emf';
        $outputFile = $this->getResourceDir() . 'phpspreadsheet/output_issue274.png';

        $reader = new GD();
        $this->assertTrue($reader->load($file));
        $image = $reader->getResource();
        $this->assertEquals(131, imagesx($image));
        $this->assertEquals(131, imagesy($image));
        // The black background is covered by the bitmap
        $this->assertNotEquals(0x000000, imagecolorat($image, 64, 64) & 0xFFFFFF);

        $this->assertTrue($reader->save($outputFile, 'png'));
        $this->assertImageCompare($outputFile, $this->getResourceDir() . 'phpspreadsheet/issue274.png', 0.02);
        @unlink($outputFile);
    }

    /**
     * @return array<string, array{bool, int, int, int, array<int|null>}>
     */
    public static function dataProviderDIBitsToDevice(): array
    {
        $colors = self::DIBITS_COLORS;
        // The lower half of the DIB, drawn at the top of the image (the rest stays white)
        $lowerHalf = [0x0000FF, 0x0000FF, 0xFFFF00, 0xFFFF00, 0xFFFFFF, 0xFFFFFF, 0xFFFFFF, 0xFFFFFF];

        return [
            'bottom-up, whole DIB' => [false, 8, 0, 8, $colors],
            'bottom-up, bands of 1 scan line' => [false, 1, 0, 8, $colors],
            'bottom-up, bands of 3 scan lines' => [false, 3, 0, 8, $colors],
            'top-down, whole DIB' => [true, 8, 0, 8, $colors],
            'top-down, bands of 3 scan lines' => [true, 3, 0, 8, $colors],
            // The origin of a bottom-up DIB is its lower-left corner
            'bottom-up, lower half' => [false, 8, 0, 4, $lowerHalf],
            'bottom-up, lower half, bands of 3 scan lines' => [false, 3, 0, 4, $lowerHalf],
            // The origin of a top-down DIB is its upper-left corner
            'top-down, lower half' => [true, 8, 4, 4, $lowerHalf],
            'top-down, lower half, bands of 3 scan lines' => [true, 3, 4, 4, $lowerHalf],
        ];
    }

    /**
     * @dataProvider dataProviderDIBitsToDevice
     *
     * @param array<int> $expectedColors Expected colors of the rows of the image, from top to bottom
     */
    public function testSetDIBitsToDevice(bool $isTopDown, int $scansPerBand, int $ySrc, int $cySrc, array $expectedColors): void
    {
        $reader = new GD();
        $this->assertTrue($reader->loadFromString($this->getContentEMFWithDIBitsToDevice($isTopDown, $scansPerBand, $ySrc, $cySrc)));
        $image = $reader->getResource();

        $this->assertEquals(8, imagesx($image));
        $this->assertEquals(8, imagesy($image));
        $colors = [];
        for ($y = 0; $y < 8; ++$y) {
            $colors[] = imagecolorat($image, 4, $y) & 0xFFFFFF;
        }
        $this->assertEquals($expectedColors, $colors);
    }

    public function testEMFPlus(): void
    {
        $file = $this->getResourceDir() . 'libemf2svg/emf/test-000.emf';

        $reader = new GD();
        $this->assertTrue($reader->isEMFPlusEnabled());
        $this->assertFalse($reader->isEMFPlusRendered());
        $this->assertTrue($reader->load($file));
        $this->assertTrue($reader->isEMFPlusRendered());

        // EMF+ disabled : only the EMF records are drawn
        $this->assertInstanceOf(GD::class, $reader->setEMFPlusEnabled(false));
        $this->assertFalse($reader->isEMFPlusEnabled());
        $this->assertTrue($reader->load($file));
        $this->assertFalse($reader->isEMFPlusRendered());

        // EMF files have no EMF+ records
        $reader->setEMFPlusEnabled(true);
        $this->assertTrue($reader->load($this->getResourceDir() . 'computer_mail.emf'));
        $this->assertFalse($reader->isEMFPlusRendered());
    }

    /**
     * If the EMF+ records can not be drawn, the EMF records are drawn
     */
    public function testEMFPlusFallback(): void
    {
        $content = (string) file_get_contents($this->getResourceDir() . 'libemf2svg/emf/test-000.emf');
        // The type of the first EMF+ record after the EMF+ header is replaced by an unknown type
        list(, $headerSize) = unpack('V', substr($content, 4, 4));
        list(, $commentSize) = unpack('V', substr($content, $headerSize + 4, 4));
        $content = substr_replace($content, pack('v', 0x40FF), $headerSize + $commentSize + 16, 2);

        $reader = new GD();
        $this->assertTrue($reader->loadFromString($content));
        $this->assertFalse($reader->isEMFPlusRendered());
    }

    public function testBackgroundColor(): void
    {
        $file = $this->getResourceDir() . 'computer_mail.emf';
        $outputFile = $this->getResourceDir() . 'output_background.jpg';

        $reader = new GD();
        $this->assertEquals([255, 255, 255], $reader->getBackgroundColor());

        // Transparent background
        $this->assertInstanceOf(GD::class, $reader->setBackgroundColor(null));
        $this->assertNull($reader->getBackgroundColor());
        $reader->load($file);
        $this->assertEquals(127, (imagecolorat($reader->getResource(), 0, 0) >> 24) & 0x7F);
        // Formats without alpha channel are saved on a white background
        $this->assertTrue($reader->save($outputFile, 'jpg'));
        $this->assertGreaterThan(0xF0, imagecolorat(imagecreatefromjpeg($outputFile), 0, 0) & 0xFF);
        @unlink($outputFile);

        // Colored background
        $reader->setBackgroundColor([0, 0, 255]);
        $reader->load($file);
        $this->assertEquals(0x0000FF, imagecolorat($reader->getResource(), 0, 0));
    }
}
