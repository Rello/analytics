<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\WMF;

use GdImage;
use PhpOffice\WMF\Exception\WMFException;
use PhpOffice\WMF\Reader\WMF\GD;
use Tests\PhpOffice\WMF\Reader\AbstractTestReader;

class GDTest extends AbstractTestReader
{
    /**
     * @dataProvider dataProviderFilesWMF
     */
    public function testLoad(string $file): void
    {
        $reader = new GD();
        $this->assertTrue($reader->load($this->getResourceDir() . $file));
    }

    /**
     * @dataProvider dataProviderFilesWMF
     */
    public function testLoadFromString(string $file): void
    {
        $reader = new GD();
        $this->assertTrue($reader->loadFromString(file_get_contents($this->getResourceDir() . $file)));
    }

    /**
     * @dataProvider dataProviderFilesWMF
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
     * @dataProvider dataProviderFilesWMF
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
     * @dataProvider dataProviderMediaTypeWMF
     */
    public function testSave(string $extension, string $mediatype): void
    {
        $outputFile = $this->getResourceDir() . 'output_save.' . $extension;

        $reader = new GD();
        $reader->load($this->getResourceDir() . 'burger.wmf');
        $reader->save($outputFile, $extension);
        $this->assertMimeType($outputFile, $mediatype);

        @unlink($outputFile);
    }

    public function testSaveWithException(): void
    {
        $file = 'vegetable.wmf';
        $outputFile = $this->getResourceDir() . 'output_' . pathinfo($file, PATHINFO_FILENAME) . '.png';

        $this->expectException(WMFException::class);

        $reader = new GD();
        $reader->load($this->getResourceDir() . $file);
        $reader->save($outputFile, 'notanextension');
    }

    public function testSaveWithoutException(): void
    {
        $file = 'vegetable.wmf';
        $outputFile = $this->getResourceDir() . 'output_' . pathinfo($file, PATHINFO_FILENAME) . '.png';

        $reader = new GD();
        $reader->enableExceptions(false);
        $reader->load($this->getResourceDir() . $file);
        $this->assertFalse($reader->save($outputFile, 'notanextension'));
    }

    /**
     * @dataProvider dataProviderFilesWMF
     */
    public function testIsWMF(string $file): void
    {
        $reader = new GD();
        $reader->load($this->getResourceDir() . $file);
        $this->assertTrue($reader->isWMF());
    }

    public function testMediaType(): void
    {
        $reader = new GD();
        $this->assertEquals('image/wmf', $reader->getMediaType());
    }

    public function testNotImplementedWithExceptions(): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : Function not implemented : 0x062f');

        $reader = new GD();
        $reader->loadFromString($this->getContentWMFNotImplemented());
    }

    public function testNotImplementedWithoutExceptions(): void
    {
        $reader = new GD();
        $reader->enableExceptions(false);
        $this->assertFalse($reader->loadFromString($this->getContentWMFNotImplemented()));
    }

    /**
     * The test file of libUEMF uses most of the WMF records
     */
    public function testLoadLibUEMF(): void
    {
        $outputFile = $this->getResourceDir() . 'output_test_libuemf.png';

        $reader = new GD();
        $this->assertTrue($reader->load($this->getResourceDir() . 'test_libuemf.wmf'));
        $this->assertTrue($reader->save($outputFile, 'png'));
        $this->assertMimeType($outputFile, 'image/png');

        @unlink($outputFile);
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
        $this->assertTrue($reader->loadFromString($this->getContentWMFWithBitmap(0, $usage)));
        $image = $reader->getResource();

        $this->assertEquals($leftColor, imagecolorat($image, (int) (imagesx($image) / 4), (int) (imagesy($image) / 2)) & 0xFFFFFF);
        $this->assertEquals($rightColor, imagecolorat($image, (int) (imagesx($image) * 3 / 4), (int) (imagesy($image) / 2)) & 0xFFFFFF);
    }

    public function testBitmapNotSupportedWithExceptions(): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : Bitmap not supported : 0x0f43');

        $reader = new GD();
        $reader->loadFromString($this->getContentWMFWithBitmap(1, 0));
    }

    public function testBitmapNotSupportedWithoutExceptions(): void
    {
        $reader = new GD();
        $reader->enableExceptions(false);
        $this->assertFalse($reader->loadFromString($this->getContentWMFWithBitmap(1, 0)));
    }

    public function testLoadStandardWMF(): void
    {
        // Without the placeable header, the size is defined by the window (MM_ANISOTROPIC : 1440 units per inch)
        $content = (string) file_get_contents($this->getResourceDir() . 'burger.wmf');
        $reader = new GD();
        $this->assertTrue($reader->loadFromString(substr($content, 22)));
        $this->assertTrue($reader->isWMF());

        // The window of burger.wmf : extent (2290, -2035)
        $image = $reader->getResource();
        $this->assertEquals((int) ceil(2290 / 1440 * 72), imagesx($image));
        $this->assertEquals((int) ceil(2035 / 1440 * 72), imagesy($image));
    }

    public function testLoadStandardWMFWithoutWindow(): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : Invalid file : the size of the image is not defined');

        // META_HEADER followed by META_EOF
        $reader = new GD();
        $reader->loadFromString(pack('v3Vv2Vv', 1, 9, 0x0300, 12, 0, 3, 0, 0) . pack('Vv', 3, 0x0000));
    }

    public function testInvertRegion(): void
    {
        $content = (string) file_get_contents($this->getResourceDir() . 'burger.wmf');
        // The window of burger.wmf : origin (-1225, 1137), extent (2290, -2035)
        // Region (object 0) : a single scan covering the image
        // nextInChain, objectType, objectCount, regionSize, scanCount, maxScan, bounding box
        $region = pack('v2Vv3', 0, 6, 0, 36, 1, 2) . pack('s4', -1225, -898, 1065, 1137)
            // Scan : count, top, bottom, left, right, count
            . pack('v', 2) . pack('s4', -898, 1137, -1225, 1065) . pack('v', 2);
        $records = pack('Vv', 3 + strlen($region) / 2, 0x06FF) . $region
            . pack('Vv', 4, 0x012A) . pack('v', 0)
            . pack('Vv', 3, 0x0000);

        // META_HEADER : maximum number of objects
        $header = substr($content, 22, 18);
        $header = substr($header, 0, 10) . pack('v', 1) . substr($header, 12);

        $reader = new GD();
        $this->assertTrue($reader->loadFromString(substr($content, 0, 22) . $header . $records));
        $image = $reader->getResource();
        // White becomes black
        $this->assertEquals(0x000000, imagecolorat($image, (int) (imagesx($image) / 2), (int) (imagesy($image) / 2)) & 0xFFFFFF);
    }

    public function testBackgroundColor(): void
    {
        $file = $this->getResourceDir() . 'burger.wmf';
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
