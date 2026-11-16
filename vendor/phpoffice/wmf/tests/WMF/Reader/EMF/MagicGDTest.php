<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\EMF;

use GdImage;
use PhpOffice\WMF\Reader\EMF\GD;
use PhpOffice\WMF\Reader\EMF\Imagick;
use PhpOffice\WMF\Reader\EMF\Magic;
use PhpOffice\WMF\Reader\EMF\ReaderInterface;
use Tests\PhpOffice\WMF\Reader\AbstractTestReader;

class MagicGDTest extends AbstractTestReader
{
    private function getReader(): ReaderInterface
    {
        $reader = new Magic();
        $reader->setBackends([
            GD::class,
            Imagick::class,
        ]);

        return $reader;
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testLoad(string $file): void
    {
        $reader = $this->getReader();
        $this->assertTrue($reader->load($this->getResourceDir() . $file));
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testLoadFromString(string $file): void
    {
        $reader = $this->getReader();
        $this->assertTrue($reader->loadFromString(file_get_contents($this->getResourceDir() . $file)));
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testGetResource(string $file): void
    {
        $reader = $this->getReader();
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

        $reader = $this->getReader();
        $reader->load($this->getResourceDir() . $file);
        $reader->save($outputFile, 'png');

        $this->assertImageCompare($outputFile, $similarFile, 0.02);

        @unlink($outputFile);
    }

    /**
     * @dataProvider dataProviderMediaTypeEMF
     */
    public function testSave(string $extension, string $mediatype): void
    {
        $outputFile = $this->getResourceDir() . 'output_save.' . $extension;

        $reader = $this->getReader();
        $reader->load($this->getResourceDir() . 'computer_mail.emf');
        $reader->save($outputFile, $extension);
        $this->assertMimeType($outputFile, $mediatype);

        @unlink($outputFile);
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testIsEMF(string $file): void
    {
        $reader = $this->getReader();
        $reader->load($this->getResourceDir() . $file);
        $this->assertTrue($reader->isEMF());
    }

    public function testMediaType(): void
    {
        $reader = $this->getReader();
        $this->assertEquals('image/emf', $reader->getMediaType());
    }
}
