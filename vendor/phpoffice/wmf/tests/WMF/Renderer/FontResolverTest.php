<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Renderer;

use PhpOffice\WMF\Renderer\FontResolver;
use PHPUnit\Framework\TestCase;

class FontResolverTest extends TestCase
{
    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        // Fake font files : only their names are used to resolve fonts
        $this->directory = sys_get_temp_dir() . '/wmf_fonts_' . uniqid();
        mkdir($this->directory . '/liberation', 0777, true);
        foreach (['LiberationSans-Regular.ttf', 'LiberationSans-Bold.ttf', 'LiberationSerif-Regular.ttf', 'arialbd.ttf', 'readme.txt'] as $file) {
            touch($this->directory . '/liberation/' . $file);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/liberation/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory . '/liberation');
        rmdir($this->directory);
    }

    /**
     * @param array<string, mixed> $font
     *
     * @return array<string, mixed>
     */
    private function getFont(array $font): array
    {
        return $font + ['face' => 'Arial', 'weight' => 400, 'italic' => false, 'pitchAndFamily' => 0];
    }

    public function testDirectories(): void
    {
        $resolver = new FontResolver();
        foreach ($resolver->getDirectories() as $directory) {
            $this->assertDirectoryExists($directory);
        }

        $this->assertInstanceOf(FontResolver::class, $resolver->setDirectories([$this->directory]));
        $this->assertEquals([$this->directory], $resolver->getDirectories());
    }

    public function testResolve(): void
    {
        $resolver = (new FontResolver())->setDirectories([$this->directory]);
        $path = $this->directory . '/liberation/';

        // Font available
        $this->assertEquals($path . 'arialbd.ttf', $resolver->resolve($this->getFont(['weight' => 700])));
        // Font not available : similar font
        $this->assertEquals($path . 'LiberationSans-Regular.ttf', $resolver->resolve($this->getFont([])));
        $this->assertEquals($path . 'LiberationSerif-Regular.ttf', $resolver->resolve($this->getFont(['face' => 'Times New Roman'])));
        // Unknown font : family defined by pitchAndFamily (FF_ROMAN)
        $this->assertEquals($path . 'LiberationSerif-Regular.ttf', $resolver->resolve($this->getFont(['face' => 'Unknown', 'pitchAndFamily' => 0x12])));
        // Style not available : regular style
        $this->assertEquals($path . 'LiberationSans-Regular.ttf', $resolver->resolve($this->getFont(['face' => 'Helvetica', 'italic' => true])));
        // No similar font
        $this->assertNull($resolver->resolve($this->getFont(['face' => 'Courier New'])));
    }

    public function testResolveWithoutFonts(): void
    {
        $resolver = (new FontResolver())->setDirectories([$this->directory . '/notfound']);

        $this->assertNull($resolver->resolve($this->getFont([])));
    }
}
