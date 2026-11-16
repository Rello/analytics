<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader;

use PhpOffice\WMF\Reader\Detector;

class DetectorTest extends AbstractTestReader
{
    /**
     * @return array<array<string>>
     */
    public static function dataProviderFiles(): array
    {
        return [
            ['burger.wmf', Detector::TYPE_WMF],
            ['chicken.wmf', Detector::TYPE_WMF],
            ['fish.wmf', Detector::TYPE_WMF],
            ['test_libuemf.wmf', Detector::TYPE_WMF],
            ['vegetable.wmf', Detector::TYPE_WMF],
            ['computer_mail.emf', Detector::TYPE_EMF],
            ['inkscape_shapes.emf', Detector::TYPE_EMF],
            ['libemf2svg/emf/test-001.emf', Detector::TYPE_EMF],
            ['libemf2svg/emf/test-000.emf', Detector::TYPE_EMFPLUS],
            ['libemf2svg/emf/test-180.emf', Detector::TYPE_EMFPLUS],
            ['libemf2svg/emf-ea/EA-test-file-101.emf', Detector::TYPE_EMFPLUS],
            ['computer_mail.png', Detector::TYPE_UNKNOWN],
            ['files.txt', Detector::TYPE_UNKNOWN],
        ];
    }

    /**
     * @dataProvider dataProviderFiles
     */
    public function testDetect(string $file, string $expectedType): void
    {
        $this->assertEquals($expectedType, Detector::detect(file_get_contents($this->getResourceDir() . $file)));
    }

    /**
     * @dataProvider dataProviderFiles
     */
    public function testDetectFile(string $file, string $expectedType): void
    {
        $this->assertEquals($expectedType, Detector::detectFile($this->getResourceDir() . $file));
    }

    /**
     * @dataProvider dataProviderFiles
     */
    public function testIsMethods(string $file, string $expectedType): void
    {
        $content = file_get_contents($this->getResourceDir() . $file);

        $this->assertEquals($expectedType == Detector::TYPE_WMF, Detector::isWMF($content));
        // EMF+ files are EMF files too
        $this->assertEquals(in_array($expectedType, [Detector::TYPE_EMF, Detector::TYPE_EMFPLUS]), Detector::isEMF($content));
        $this->assertEquals($expectedType == Detector::TYPE_EMFPLUS, Detector::isEMFPlus($content));
    }

    public function testDetectFileNotFound(): void
    {
        $this->assertEquals(Detector::TYPE_UNKNOWN, Detector::detectFile($this->getResourceDir() . 'notfound.emf'));
    }

    /**
     * @return array<array<string>>
     */
    public static function dataProviderInvalidContents(): array
    {
        return [
            [''],
            ['WMF'],
            [str_repeat("\0", 128)],
            // Placeable key without META_HEADER
            [pack('V', 0x9AC6CDD7) . str_repeat("\0", 60)],
            // EMF header without the signature
            [pack('V2', 1, 88) . str_repeat("\0", 80)],
        ];
    }

    /**
     * @dataProvider dataProviderInvalidContents
     */
    public function testDetectInvalidContent(string $content): void
    {
        $this->assertEquals(Detector::TYPE_UNKNOWN, Detector::detect($content));
    }

    /**
     * A WMF file without the placeable header is detected as WMF, but not as placeable WMF
     */
    public function testStandardWMF(): void
    {
        $content = substr(file_get_contents($this->getResourceDir() . 'burger.wmf'), 22);

        $this->assertEquals(Detector::TYPE_WMF, Detector::detect($content));
        $this->assertTrue(Detector::isWMF($content));
        $this->assertFalse(Detector::isPlaceableWMF($content));
    }

    /**
     * A EMF file with a truncated EMF+ header is a EMF file
     */
    public function testTruncatedEMFPlus(): void
    {
        $content = file_get_contents($this->getResourceDir() . 'libemf2svg/emf/test-000.emf');
        list(, $headerSize) = unpack('V', substr($content, 4, 4));

        $this->assertEquals(Detector::TYPE_EMF, Detector::detect(substr($content, 0, $headerSize + 10)));
    }
}
