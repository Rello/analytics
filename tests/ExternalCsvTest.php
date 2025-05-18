<?php

use PHPUnit\Framework\TestCase;
use OCA\Analytics\Datasource\ExternalCsv;

class ExternalCsvTest extends TestCase
{
    /**
     * @dataProvider delimiterProvider
     */
    public function testDetectDelimiter(string $expected, string $line): void
    {
        require_once __DIR__ . '/../lib/Datasource/ExternalCsv.php';
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
