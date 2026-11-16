<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\EMF;

use GdImage;
use Imagick as ImagickBase;
use PhpOffice\WMF\Reader\EMF\GD;
use PhpOffice\WMF\Reader\EMF\Imagick;
use PhpOffice\WMF\Reader\EMF\Magic;
use Tests\PhpOffice\WMF\Reader\AbstractTestReader;

class MagicTest extends AbstractTestReader
{
    public function testGetBackends(): void
    {
        $reader = new Magic();
        $this->assertEquals([
            Imagick::class,
            GD::class,
        ], $reader->getBackends());
    }

    public function testSetBackends(): void
    {
        $reader = new Magic();
        $this->assertInstanceOf(Magic::class, $reader->setBackends([
            GD::class,
            Imagick::class,
            'NotABackend',
        ]));
        $this->assertEquals([
            GD::class,
            Imagick::class,
        ], $reader->getBackends());
    }

    /**
     * @dataProvider dataProviderFilesEMF
     */
    public function testLoadWithDefaultBackends(string $file): void
    {
        $reader = new Magic();
        $this->assertTrue($reader->load($this->getResourceDir() . $file));
        $this->assertTrue($reader->isEMF());

        // Imagick is used only if it supports EMF, else GD is used
        if (extension_loaded('imagick') && in_array('EMF', ImagickBase::queryFormats())) {
            $this->assertInstanceOf(ImagickBase::class, $reader->getResource());
        } elseif (\PHP_VERSION_ID >= 80000) {
            /* @phpstan-ignore-next-line */
            $this->assertInstanceOf(GdImage::class, $reader->getResource());
        }
    }

    public function testIsSupported(): void
    {
        $this->assertTrue((new GD())->isSupported());
        $this->assertTrue((new Magic())->isSupported());

        $reader = new Magic();
        $reader->setBackends([]);
        $this->assertFalse($reader->isSupported());
    }

    /**
     * A backend not supported is skipped
     */
    public function testBackendNotSupported(): void
    {
        $unsupported = new class extends Imagick {
            public function isSupported(): bool
            {
                return false;
            }
        };

        $reader = new Magic();
        $reader->setBackends([
            get_class($unsupported),
            GD::class,
        ]);
        $this->assertTrue($reader->load($this->getResourceDir() . 'computer_mail.emf'));
        $this->assertTrue($reader->isEMF());
        if (\PHP_VERSION_ID >= 80000) {
            /* @phpstan-ignore-next-line */
            $this->assertInstanceOf(GdImage::class, $reader->getResource());
        }
    }
}
