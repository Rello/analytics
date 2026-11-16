<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\WMF;

use GdImage;
use PhpOffice\WMF\Reader\WMF\GD;
use PhpOffice\WMF\Reader\WMF\Imagick;
use PhpOffice\WMF\Reader\WMF\Magic;
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
        ]));
        $this->assertEquals([
            GD::class,
            Imagick::class,
        ], $reader->getBackends());
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
        $this->assertTrue($reader->load($this->getResourceDir() . 'burger.wmf'));
        $this->assertTrue($reader->isWMF());
        if (\PHP_VERSION_ID >= 80000) {
            /* @phpstan-ignore-next-line */
            $this->assertInstanceOf(GdImage::class, $reader->getResource());
        }
    }
}
