<?php
namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;

class DatasourceControllerTest extends TestCase {
    public function testAggregateDataRemovesColumnsUsingNumericSorting() {
        $controller = new DatasourceController(
            'analytics',
            $this->createMock(\OCP\IRequest::class),
            $this->createMock(\Psr\Log\LoggerInterface::class),
            $this->createMock(\OCA\Analytics\Datasource\Github::class),
            $this->createMock(\OCA\Analytics\Datasource\LocalCsv::class),
            $this->createMock(\OCA\Analytics\Datasource\Regex::class),
            $this->createMock(\OCA\Analytics\Datasource\ExternalJson::class),
            $this->createMock(\OCA\Analytics\Datasource\LocalJson::class),
            $this->createMock(\OCA\Analytics\Datasource\ExternalCsv::class),
            $this->createMock(\OCA\Analytics\Datasource\LocalSpreadsheet::class),
            new FakeL10N(),
            $this->createMock(\OCP\EventDispatcher\IEventDispatcher::class),
            $this->createMock(\OCP\IAppConfig::class)
        );

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
}
