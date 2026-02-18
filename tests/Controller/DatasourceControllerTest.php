<?php
namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;

class DatasourceControllerTest extends TestCase {
    public function testAggregateDataRemovesColumnsUsingNumericSorting() {
        $controller = $this->createController();

        $header = [];
        $row1 = [];
        $row2 = [];
        for ($i = 0; $i <= 10; $i++) {
            $header[] = 'col' . $i;
            $row1[] = 'r1c' . $i;
            $row2[] = 'r2c' . $i;
        }
        $header[] = 'Value';
        $row1[] = 1;
        $row2[] = 2;
        $data = ['header' => $header, 'data' => [$row1, $row2]];

        $filter = json_encode(['drilldown' => ['2' => true, '10' => true]]);
        $reflection = new \ReflectionClass(DatasourceController::class);
        $method = $reflection->getMethod('aggregateData');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $data, $filter);

        $this->assertSame('Value', end($result['header']));
        $this->assertNotContains('col2', $result['header']);
        $this->assertNotContains('col10', $result['header']);
        $this->assertCount(10, $result['header']);
    }

    public function testReadIgnoresPersistedCacheKeyWhenValidationIsDisabled(): void {
        $localCsv = $this->createMock(\OCA\Analytics\Datasource\LocalCsv::class);
        $localCsv->expects($this->once())
            ->method('readData')
            ->with($this->callback(function ($option) {
                $this->assertSame('/data.csv', $option['link']);
                $this->assertSame('u1', $option['user_id']);
                $this->assertArrayNotHasKey('cacheKey', $option);
                return true;
            }))
            ->willReturn([
                'header' => ['a', 'b', 'c'],
                'dimensions' => ['a', 'b'],
                'data' => [['x', 'y', 1]],
                'error' => 0,
            ]);

        $controller = $this->createController($localCsv);
        $metadata = [
            'link' => '{"link":"/data.csv","cacheKey":"persisted-key"}',
            'user_id' => 'u1',
        ];

        $result = $controller->read(DatasourceController::DATASET_TYPE_LOCAL_CSV, $metadata, false);

        $this->assertSame(0, $result['error']);
        $this->assertCount(1, $result['data']);
    }

    public function testReadUsesOnlyRequestCacheKeyForValidation(): void {
        $localCsv = $this->createMock(\OCA\Analytics\Datasource\LocalCsv::class);
        $localCsv->expects($this->once())
            ->method('readData')
            ->with($this->callback(function ($option) {
                $this->assertSame('/data.csv', $option['link']);
                $this->assertSame('u1', $option['user_id']);
                $this->assertSame('request-key', $option['cacheKey']);
                return true;
            }))
            ->willReturn([
                'header' => [],
                'dimensions' => [],
                'data' => [],
                'error' => 0,
                'cache' => [
                    'cacheable' => true,
                    'key' => 'request-key',
                    'notModified' => true,
                ],
            ]);

        $controller = $this->createController($localCsv);
        $metadata = [
            'link' => '{"link":"/data.csv","cacheKey":"persisted-key"}',
            'user_id' => 'u1',
            'cacheKey' => 'request-key',
        ];

        $result = $controller->read(DatasourceController::DATASET_TYPE_LOCAL_CSV, $metadata);

        $this->assertTrue($result['cache']['notModified']);
    }

    private function createController(?\OCA\Analytics\Datasource\LocalCsv $localCsv = null): DatasourceController {
        $appConfig = $this->createMock(\OCP\IAppConfig::class);
        $appConfig->method('getValueString')->willReturn('');

        return new DatasourceController(
            'analytics',
            $this->createMock(\OCP\IRequest::class),
            $this->createMock(\Psr\Log\LoggerInterface::class),
            $this->createMock(\OCA\Analytics\Datasource\Github::class),
            $localCsv ?? $this->createMock(\OCA\Analytics\Datasource\LocalCsv::class),
            $this->createMock(\OCA\Analytics\Datasource\Regex::class),
            $this->createMock(\OCA\Analytics\Datasource\ExternalJson::class),
            $this->createMock(\OCA\Analytics\Datasource\LocalJson::class),
            $this->createMock(\OCA\Analytics\Datasource\ExternalCsv::class),
            $this->createMock(\OCA\Analytics\Datasource\LocalSpreadsheet::class),
            new FakeL10N(),
            $this->createMock(\OCP\EventDispatcher\IEventDispatcher::class),
            $appConfig
        );
    }
}
