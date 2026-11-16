<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\EMF;

use Imagick as ImagickBase;
use PhpOffice\WMF\Reader\EMF\Imagick as ImagickReader;
use Tests\PhpOffice\WMF\Reader\AbstractTestReader;

class ImagickTest extends AbstractTestReader
{
    protected function setUp(): void
    {
        if (!extension_loaded('imagick') || !in_array('EMF', ImagickBase::queryFormats())) {
            $this->markTestSkipped('Imagick does not support EMF files (only available on Windows)');
        }
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testLoad(string $file): void
    {
        $reader = new ImagickReader();
        $this->assertTrue($reader->load($this->getResourceDir() . $file));
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testLoadFromString(string $file): void
    {
        $reader = new ImagickReader();
        $this->assertTrue($reader->loadFromString(file_get_contents($this->getResourceDir() . $file)));
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testGetResource(string $file): void
    {
        $reader = new ImagickReader();
        $reader->load($this->getResourceDir() . $file);
        $this->assertInstanceOf(ImagickBase::class, $reader->getResource());
    }

    /**
     * @dataProvider dataProviderMediaTypeEMF
     */
    public function testSave(string $extension, string $mediatype): void
    {
        $outputFile = $this->getResourceDir() . 'output_save.' . $extension;

        $reader = new ImagickReader();
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
        $reader = new ImagickReader();
        $reader->load($this->getResourceDir() . $file);
        $this->assertTrue($reader->isEMF());
    }

    public function testMediaType(): void
    {
        $reader = new ImagickReader();
        $this->assertEquals('image/emf', $reader->getMediaType());
    }
}
