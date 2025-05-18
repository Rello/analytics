<?php

use PHPUnit\Framework\TestCase;
use OCA\Analytics\Datasource\ExternalCsv;
use ReflectionClass;

class ExternalCsvTest extends TestCase
{
    /**
     * @dataProvider delimiterProvider
     */
    public function testDetectDelimiter(string $expected, string $line): void
    {
        $refClass = new ReflectionClass(ExternalCsv::class);
        $instance = $refClass->newInstanceWithoutConstructor();
        $method = $refClass->getMethod('detectDelimiter');
        $method->setAccessible(true);
        $result = $method->invoke($instance, $line);
        $this->assertSame($expected, $result);
    }

    public static function delimiterProvider(): array
    {
        return [
            [',', 'a,b,c'],
            [';', 'a;b;c'],
            ['|', 'a|b|c'],
            ["\t", "a\tb\tc"],
        ];
    }
}
