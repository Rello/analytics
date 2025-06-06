<?php
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Db\DatasetMapper;
use OCP\IDateTimeFormatter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VariableServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        $datasetMapper = $this->createMock(DatasetMapper::class);
        $datasetMapper->method('getLastUpdate')->willReturn(strtotime('2022-01-01 12:00:00'));
        $datasetMapper->method('getOwner')->willReturn('owner');

        $dateFormatter = $this->createMock(IDateTimeFormatter::class);
        $dateFormatter->method('formatDate')->willReturnCallback(function (int $ts) {
            return date('Y-m-d', $ts);
        });
        $dateFormatter->method('formatTime')->willReturnCallback(function (int $ts) {
            return date('H:i', $ts);
        });

        $this->service = new VariableService(
            new NullLogger(),
            $datasetMapper,
            $dateFormatter
        );
    }

    /**
     * @dataProvider filterProvider
     */
    public function testParseFilter(string $variable)
    {
        $ref = new \ReflectionMethod(VariableService::class, 'parseFilter');
        $ref->setAccessible(true);
        $result = $ref->invoke($this->service, $variable);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('option', $result);
        $this->assertArrayHasKey('value', $result);

        if (str_contains($variable, 'quarter')) {
            $this->assertSame('BETWEEN', $result['option']);
            $this->assertIsArray($result['value']);
            $this->assertCount(2, $result['value']);
        } else {
            $this->assertSame('GT', $result['option']);
            $this->assertIsInt($result['value']);
        }
    }

    public function filterProvider(): array
    {
        return [
            ['%today%'],
            ['%yesterday%'],
            ['%last week%'],
            ['%last2weeks%'],
            ['%next month%'],
            ['%next3months%'],
            ['%current year%'],
            ['%next2years%'],
            ['%first quarter%'],
            ['%second quarter%'],
            ['%third quarter%'],
            ['%fourth quarter%'],
            ['%last quarter%'],
            ['%last2quarters%'],
            ['%next quarter%'],
            ['%next2quarters%'],
            ['%current quarter%'],
        ];
    }

    public function testReplaceThresholdsVariables()
    {
        $thresholds = [
            ['value' => '%today%']
        ];
        $result = $this->service->replaceThresholdsVariables($thresholds);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result[0]['value']);
    }

    public function testReplaceTextVariables()
    {
        $now = time();
        $metadata = [
            'dataset' => 1,
            'name' => 'Name %currentDate% %currentTime% %now% %lastUpdateDate% %lastUpdateTime% %owner%',
            'subheader' => 'Header %currentDate%',
        ];

        $expected = [
            'dataset' => 1,
            'name' => 'Name ' . date('Y-m-d', $now) . ' ' . date('H:i', $now) . ' ' . $now . ' ' .
                date('Y-m-d', strtotime('2022-01-01 12:00:00')) . ' ' .
                date('H:i', strtotime('2022-01-01 12:00:00')) . ' owner',
            'subheader' => 'Header ' . date('Y-m-d', $now),
        ];

        $result = $this->service->replaceTextVariables($metadata);
        $this->assertSame($expected['name'], $result['name']);
        $this->assertSame($expected['subheader'], $result['subheader']);
    }

    public function testReplaceTextVariablesSingle()
    {
        $now = time();
        $field = 'Value %currentDate%';
        $expected = 'Value ' . date('Y-m-d', $now);
        $result = $this->service->replaceTextVariablesSingle($field);
        $this->assertSame($expected, $result);
    }

    public function testReplaceDatasourceColumns()
    {
        $result = $this->service->replaceDatasourceColumns('%last week%');
        $this->assertIsString($result);
        $this->assertNotSame('%last week%', $result);
    }

    public function testReplaceFilterVariables()
    {
        $report = [
            'filteroptions' => json_encode([
                'filter' => [
                    ['option' => 'GT', 'value' => '%current quarter%']
                ]
            ])
        ];

        $ref = new \ReflectionMethod(VariableService::class, 'parseFilter');
        $ref->setAccessible(true);
        $parsed = $ref->invoke($this->service, '%current quarter%');

        $start = date('Y-m-d H:m:s', $parsed['value'][0]);
        $end = date('Y-m-d H:m:s', $parsed['value'][1]);

        $expected = [
            'filteroptions' => json_encode([
                'filter' => [
                    ['option' => 'BETWEEN', 'value' => [$start, $end]]
                ]
            ])
        ];

        $result = $this->service->replaceFilterVariables($report);
        $this->assertSame($expected, $result);
    }
}
