<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Service\ShareService;
use PHPUnit\Framework\TestCase;

class PanoramaShareAccessTest extends TestCase {
    private function service(ReportMapper $mapper): ShareService {
        $service = $this->getMockBuilder(ShareService::class)->disableOriginalConstructor()->onlyMethods(['getSharedItems'])->getMock();
        $service->method('getSharedItems')->willReturn([
            ['id' => 3, 'user_id' => 'owner', 'pages' => json_encode([['reports' => [['type' => 0, 'value' => 7]]]])],
            ['id' => 4, 'user_id' => 'owner', 'pages' => json_encode([['reports' => [['type' => 0, 'value' => 8]]]])],
        ]);
        $property = new \ReflectionProperty(ShareService::class, 'ReportMapper');
        $property->setAccessible(true);
        $property->setValue($service, $mapper);
        return $service;
    }

    public function testReportFromAnotherAccessiblePanoramaDoesNotGrantAccessInThisOne(): void {
        $mapper = $this->createMock(ReportMapper::class);
        $mapper->expects($this->never())->method('read');
        $this->assertSame([], $this->service($mapper)->getSharedPanoramaReport(7, 4));
    }

    public function testMembershipDoesNotBypassReportOwnership(): void {
        $mapper = $this->createMock(ReportMapper::class);
        $mapper->expects($this->once())->method('read')->with(7)->willReturn(['id' => 7, 'user_id' => 'someone-else']);
        $this->assertSame([], $this->service($mapper)->getSharedPanoramaReport(7, 3));
    }

    public function testMatchingPanoramaAndOwnerGrantAccess(): void {
        $mapper = $this->createMock(ReportMapper::class);
        $report = ['id' => 7, 'user_id' => 'owner'];
        $mapper->expects($this->once())->method('read')->with(7)->willReturn($report);
        $this->assertSame($report, $this->service($mapper)->getSharedPanoramaReport(7, 3));
    }
}
