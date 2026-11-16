<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Renderer;

use PhpOffice\WMF\Renderer\Encoding;
use PHPUnit\Framework\TestCase;

class EncodingTest extends TestCase
{
    public function testDecodeANSI(): void
    {
        // Windows-1252 : 0xE9 is "é", 0x80 is "€"
        $this->assertEquals('Café €', Encoding::decodeANSI("Caf\xE9 \x80"));
        // Stops at the first NUL character
        $this->assertEquals('Arial', Encoding::decodeANSI("Arial\0\0garbage"));
        $this->assertEquals('', Encoding::decodeANSI(''));
    }

    public function testDecodeUTF16(): void
    {
        $this->assertEquals('Café', Encoding::decodeUTF16("C\0a\0f\0\xE9\0"));
        // Surrogate pair
        $this->assertEquals('A😀', Encoding::decodeUTF16("A\0\x3D\xD8\x00\xDE"));
        // Stops at the first NUL character
        $this->assertEquals('A', Encoding::decodeUTF16("A\0\0\0B\0"));
        // Odd length
        $this->assertEquals('A', Encoding::decodeUTF16("A\0B"));
        // Lone surrogate
        $this->assertEquals("\u{FFFD}", Encoding::decodeUTF16("\x3D\xD8"));
    }

    public function testDecodeSymbol(): void
    {
        // ASCII & private characters (U+F0xx) of the Symbol font
        $this->assertEquals('αβχ ≤ Σ', Encoding::decodeSymbol("ab\u{F063} \u{F0A3} S"));
        // Characters which are not in the Symbol font are kept
        $this->assertEquals('1+2=3 €', Encoding::decodeSymbol('1+2=3 €'));
    }
}
