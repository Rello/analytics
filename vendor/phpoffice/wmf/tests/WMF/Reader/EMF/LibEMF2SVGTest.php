<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\EMF;

use PhpOffice\WMF\Exception\WMFException;
use PhpOffice\WMF\Reader\Detector;
use PhpOffice\WMF\Reader\EMF\GD;
use Tests\PhpOffice\WMF\Reader\AbstractTestReader;

/**
 * Test files of the libemf2svg project
 *
 * @see tests/resources/libemf2svg/README.md
 *
 * @group libemf2svg
 */
class LibEMF2SVGTest extends AbstractTestReader
{
    /**
     * @return array<string, array<string>>
     */
    public static function dataProviderFiles(): array
    {
        return self::getFiles(['emf', 'emf-ea']);
    }

    /**
     * @return array<string, array<string>>
     */
    public static function dataProviderFilesCorrupted(): array
    {
        return self::getFiles(['emf-corrupted']);
    }

    /**
     * @param array<string> $directories
     *
     * @return array<string, array<string>>
     */
    private static function getFiles(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            foreach (glob(dirname(__DIR__, 3) . '/resources/libemf2svg/' . $directory . '/*.emf') ?: [] as $file) {
                $files[$directory . '/' . basename($file)] = [$file];
            }
        }

        return $files;
    }

    /**
     * @dataProvider dataProviderFiles
     */
    public function testLoadAndSave(string $file): void
    {
        $outputFile = $this->getResourceDir() . 'output_libemf2svg_' . basename($file, '.emf') . '.png';

        $reader = new GD();
        $this->assertTrue($reader->load($file));
        $this->assertTrue($reader->isEMF());
        // EMF+ files are drawn with their EMF+ records
        $this->assertEquals(Detector::detectFile($file) == Detector::TYPE_EMFPLUS, $reader->isEMFPlusRendered());
        $this->assertTrue($reader->save($outputFile, 'png'));
        $this->assertMimeType($outputFile, 'image/png');

        @unlink($outputFile);
    }

    /**
     * Corrupted files must not raise PHP errors
     *
     * @dataProvider dataProviderFilesCorrupted
     */
    public function testLoadCorruptedWithExceptions(string $file): void
    {
        $reader = new GD();
        try {
            $this->assertIsBool($reader->load($file));
        } catch (WMFException $e) {
            $this->assertStringStartsWith('Reader : ', $e->getMessage());
        }
    }

    /**
     * @dataProvider dataProviderFilesCorrupted
     */
    public function testLoadCorruptedWithoutExceptions(string $file): void
    {
        $reader = new GD();
        $reader->enableExceptions(false);
        $this->assertIsBool($reader->load($file));
    }
}
