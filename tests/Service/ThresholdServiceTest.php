<?php
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Service\ThresholdService;
use OCA\Analytics\Db\ThresholdMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Notification\NotificationManager;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ThresholdServiceTest extends TestCase {
    public function testValidateTriggersNotification() {
        $thresholds = [
            [
                'id' => 5,
                'dimension' => 0,
                'option' => 'GT',
                'value' => '10',
                'user_id' => 'uid'
            ]
        ];
        $dataset = [
            'name' => 'Sales',
            'dimension1' => 'amount',
            'dimension2' => '',
            'value' => ''
        ];

        $thresholdMapper = $this->createMock(ThresholdMapper::class);
        $thresholdMapper->method('getSevOneThresholdsByReport')->willReturn($thresholds);

        $reportMapper = $this->createMock(ReportMapper::class);
        $reportMapper->method('read')->willReturn($dataset);

        $notification = $this->createMock(NotificationManager::class);
        $notification->expects($this->once())->method('triggerNotification');

        $service = new ThresholdService(
            new NullLogger(),
            $thresholdMapper,
            $notification,
            $reportMapper,
            $this->createMock(\OCA\Analytics\Service\VariableService::class),
            new FakeL10N()
        );

        $result = $service->validate(1, 11, null, null);
        $this->assertSame('Threshold value met', $result);
    }
}
