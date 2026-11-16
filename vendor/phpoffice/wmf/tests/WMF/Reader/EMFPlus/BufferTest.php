<?php

declare(strict_types=1);

namespace Tests\PhpOffice\WMF\Reader\EMFPlus;

use PhpOffice\WMF\Exception\WMFException;
use PhpOffice\WMF\Reader\EMFPlus\Buffer;
use PHPUnit\Framework\TestCase;

class BufferTest extends TestCase
{
    public function testIntegers(): void
    {
        $buffer = new Buffer(pack('Cvvvlg', 0xAB, 0xFFFF, 0xFFFF, 0x1234, -2, 1.5));

        $this->assertEquals(0xAB, $buffer->readUInt8());
        $this->assertEquals(0xFFFF, $buffer->readUInt16());
        $this->assertEquals(-1, $buffer->readInt16());
        $this->assertEquals(0x1234, $buffer->readUInt16());
        $this->assertEquals(-2, $buffer->readInt32());
        $this->assertEquals(1.5, $buffer->readFloat());
        $this->assertEquals(0, $buffer->getRemaining());
    }

    public function testColor(): void
    {
        // ARGB : 0x80112233
        $buffer = new Buffer(pack('V', 0x80112233));

        $this->assertEquals([0x11, 0x22, 0x33, 0x80], $buffer->readColor());
    }

    public function testRects(): void
    {
        $buffer = new Buffer(pack('v4', 1, 2, 3, 0xFFFF) . pack('g4', 1.5, 2.5, 3.5, 4.5));

        $this->assertEquals([1, 2, 3, -1], $buffer->readRect(true));
        $this->assertEquals([1.5, 2.5, 3.5, 4.5], $buffer->readRect());
    }

    public function testPoints(): void
    {
        $this->assertEquals([[1.5, -2.5]], (new Buffer(pack('g2', 1.5, -2.5)))->readPoints(1));
        $this->assertEquals([[1, -2]], (new Buffer(pack('v2', 1, 0xFFFE)))->readPoints(1, true));
    }

    public function testRelativePoints(): void
    {
        // EmfPlusInteger7 : 10 & -3 (0x7D), then EmfPlusInteger15 (big-endian) : 1000 (0x83E8) & -1000 (0xFC18)
        $buffer = new Buffer("\x0A\x7D\x83\xE8\xFC\x18");

        $this->assertEquals([[10, -3], [1010, -1003]], $buffer->readPoints(2, false, true));
    }

    public function testAlign(): void
    {
        $buffer = new Buffer(str_repeat("\0", 8));
        $buffer->readUInt8();
        $buffer->align();

        $this->assertEquals(4, $buffer->getPosition());
    }

    public function testTruncated(): void
    {
        $this->expectException(WMFException::class);
        $this->expectExceptionMessage('Reader : Invalid file : truncated EMF+ data');

        (new Buffer("\0\0"))->readUInt32();
    }
}
