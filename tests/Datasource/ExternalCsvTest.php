<?php
namespace OCA\Analytics\Tests\Datasource;

use OCA\Analytics\Datasource\ExternalCsv;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCA\Analytics\Service\VariableService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ExternalCsvTest extends TestCase
{
    private ExternalCsv $csv;

    protected function setUp(): void
    {
        $variableService = $this->createMock(VariableService::class);
        $this->csv = new ExternalCsv('uid', new FakeL10N(), new NullLogger(), $variableService);
    }

    /**
     * @dataProvider delimiterProvider
     */
    public function testDetectDelimiter(string $row, string $expected): void
    {
        $ref = new \ReflectionMethod(ExternalCsv::class, 'detectDelimiter');
        $ref->setAccessible(true);
        $this->assertSame($expected, $ref->invoke($this->csv, $row));
    }

    public function delimiterProvider(): array
    {
        return [
            ['a,b,c', ','],
            ['a;b;c', ';'],
            ['a|b|c', '|'],
            ["a\tb\tc", "\t"],
        ];
    }
}
