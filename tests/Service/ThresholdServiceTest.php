<?php
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\ThresholdService;
use OCA\Analytics\Db\ThresholdMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Notification\NotificationManager;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCA\Analytics\Service\VariableService;
use OCP\IDateTimeFormatter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ThresholdServiceTest extends TestCase {
	/**
	 * @dataProvider validationData
	 */
	public function testValidate(array $thresholds, array $dataset, $dimension1, $dimension2, $value, $expectedNotification)
	{
        $thresholdMapper = $this->createMock(ThresholdMapper::class);
        $thresholdMapper->method('getSevOneThresholdsByReport')->willReturn($thresholds);

        $reportMapper = $this->createMock(ReportMapper::class);
        $reportMapper->method('read')->willReturn($dataset);

		$notification = $this->createMock(NotificationManager::class);
		if ($expectedNotification) {
			$notification->expects($this->once())->method('triggerNotification');
		} else {
			$notification->expects($this->never())->method('triggerNotification');
		}

		$datasetMapper = $this->createMock(\OCA\Analytics\Db\DatasetMapper::class);
		$dateFormatter = $this->createMock(IDateTimeFormatter::class);

		$variableService = new VariableService(
			new NullLogger(),
			$datasetMapper,
			$dateFormatter
		);

		$service = new ThresholdService(
			new NullLogger(),
			$thresholdMapper,
			$notification,
			$reportMapper,
			$variableService,
			new FakeL10N()
		);

		// Call read to trigger variable replacement logic
		//$this->assertSame($thresholds, $service->read(1));

		$result = $service->validate($dataset['id'], $dimension1, $dimension2, $value);

		// Assert whatever return value or side effects you need
		if ($expectedNotification) {
			$this->assertSame('Threshold value met', $result);
		} else {
			$this->assertNull($result);
		}
	}

	public function validationData()
	{
		return [
			// Threshold
			// Report Metadata
			// Data row column1;column2;value
			// expected true/false
			'Numeric Value GT' => [
				[['id' => 1, 'dimension' => 0, 'option' => 'GT', 'target' => '10', 'user_id' => 'u1']],
				['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
				15, 15, 25,
				true,
			],
			'Date equal %today%' => [
				[['id' => 1, 'dimension' => 0, 'option' => 'GT', 'target' => '%today%', 'user_id' => 'u1']],
				['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
				date('YYYY-m-d'), 15, 25,
				true,
			],
			'String GT true' => [
				[['id' => 1, 'dimension' => 0, 'option' => 'GT', 'target' => 'ABB', 'user_id' => 'u1']],
				['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
				'ABC', 15, 25,
				true,
			],
			'String GT false' => [
				[['id' => 1, 'dimension' => 0, 'option' => 'GT', 'target' => 'ABD', 'user_id' => 'u1']],
				['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
				'ABC', 15, 25,
				false,
			],
			'LT no notification' => [
				[['id' => 1, 'dimension' => 0, 'option' => 'LT', 'target' => '10', 'user_id' => 'u1']],
				['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
				'20', '20', '20',
				false,
			],
			'decimal true' => [
				[['id' => 1, 'dimension' => 0, 'option' => 'GT', 'target' => '10', 'user_id' => 'u1']],
				['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
				'10.11', 20, 20,
				true,
			],
                        'decimal false' => [
                                [['id' => 1, 'dimension' => 0, 'option' => 'LT', 'target' => '10', 'user_id' => 'u1']],
                                ['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
                                '10.11', 20, 20,
                                false,
                        ],
                        'thousands comma' => [
                                [['id' => 1, 'dimension' => 0, 'option' => 'GT', 'target' => '5000', 'user_id' => 'u1']],
                                ['id' => 1, 'name' => 'Report A', 'dimension1' => 'amount', 'dimension2' => '', 'value' => ''],
                                '6,063', 20, 20,
                                true,
                        ],
                        // Add more scenarios here …
                ];
        }
}