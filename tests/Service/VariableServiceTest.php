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

    /**
     * @dataProvider replaceFilterVariablesProvider
     */
    public function testReplaceFilterVariables(array $filter, string $expectedOption, $expectedValue)
    {
        $report = [
            'filteroptions' => json_encode([
                'filter' => [$filter]
            ])
        ];

        $expected = [
            'filteroptions' => json_encode([
                'filter' => [
                    ['option' => $expectedOption, 'value' => $expectedValue]
                ]
            ])
        ];

        $result = $this->service->replaceFilterVariables($report);
        $this->assertSame($expected, $result);
    }

    public function replaceFilterVariablesProvider(): array
    {
        $defaultFormat = 'Y-m-d H:m:s';
        $currentQuarterRange = self::expectedQuarterRange('current');
        $last20DaysStart = self::expectedStartTimestamp('last', 'day', 20);
        $nextWeekStart = self::expectedStartTimestamp('next', 'week', 1);
        $todayStart = self::expectedStartTimestamp('current', 'day', 1);

        return [
            'current quarter overrides option' => [
                ['option' => 'GT', 'value' => '%current quarter%'],
                'BETWEEN',
                [
                    date($defaultFormat, $currentQuarterRange[0]),
                    date($defaultFormat, $currentQuarterRange[1]),
                ],
            ],
            'last 20 days trims and overrides option' => [
                ['option' => 'LT', 'value' => '%last 20 days% '],
                'LT',
                date($defaultFormat, $last20DaysStart),
            ],
            'next week with custom format' => [
                ['option' => 'EQ', 'value' => '%next week%(Y-m-d)'],
                'GT',
                date('Y-m-d', $nextWeekStart),
            ],
            'today with unix format' => [
                ['option' => 'NEQ', 'value' => '%today%(X)'],
                'GT',
                date('U', $todayStart),
            ],
        ];
    }

    private static function expectedStartTimestamp(string $direction, string $unit, int $offset): int
    {
        if ($direction === 'last' || $direction === 'yester') {
            $dir = '-';
        } elseif ($direction === 'next') {
            $dir = '+';
        } else {
            $dir = '+';
            $offset = 0;
        }

        $timeString = $dir . $offset . ' ' . $unit;
        $baseDate = strtotime($timeString);

        if ($unit === 'day') {
            $startString = 'today';
        } elseif ($unit === 'month') {
            $startString = 'first day of this month';
        } elseif ($unit === 'year') {
            $startString = 'first day of January';
        } else {
            $startString = 'first day of this ' . $unit;
        }

        return strtotime($startString, $baseDate);
    }

    private static function expectedQuarterRange(string $direction, int $offset = 1): array
    {
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
        $currentQuarter = (int)ceil($currentMonth / 3);

        $targetQuarter = $currentQuarter;
        $targetYear = $currentYear;

        switch ($direction) {
            case 'first':
                $targetQuarter = 1;
                break;
            case 'second':
                $targetQuarter = 2;
                break;
            case 'third':
                $targetQuarter = 3;
                break;
            case 'fourth':
                $targetQuarter = 4;
                break;
            case 'last':
            case 'yester':
                $targetQuarter = $currentQuarter - $offset;
                while ($targetQuarter < 1) {
                    $targetQuarter += 4;
                    $targetYear -= 1;
                }
                break;
            case 'next':
                $targetQuarter = $currentQuarter + $offset;
                while ($targetQuarter > 4) {
                    $targetQuarter -= 4;
                    $targetYear += 1;
                }
                break;
            case 'current':
            default:
                break;
        }

        $firstMonthOfQuarter = (($targetQuarter - 1) * 3) + 1;
        $startTS = strtotime("{$targetYear}-{$firstMonthOfQuarter}-01");
        $lastMonthOfQuarter = $firstMonthOfQuarter + 2;
        $endTS = strtotime("last day of {$targetYear}-{$lastMonthOfQuarter}-01 23:59:59");

        return [$startTS, $endTS];
    }
}
