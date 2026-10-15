<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\OutputController;
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Service\PanoramaService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\ShareService;
use OCP\AppFramework\Http\NotFoundResponse;
use PHPUnit\Framework\TestCase;

class PanoramaOutputTest extends TestCase {
    private function setupController(bool $shared = false, bool $member = true, bool $authorized = true, string $etag = '42'): array {
        $filter = ['id' => 'date', 'label' => 'Date', 'enabled' => true, 'operator' => 'EQ',
            'mappings' => [['reportId' => 7, 'dimension' => 'dimension1', 'dimensionLabel' => 'Date']]];
        $panorama = ['id' => 3, 'pages' => json_encode([['reports' => [['type' => 0, 'value' => $member ? 7 : 8]]]]),
            'filters' => json_encode([$filter])];
        $metadata = ['id' => 7, 'type' => DatasourceController::DATASET_TYPE_INTERNAL_DB, 'version' => 42,
            'filteroptions' => null];
        $controller = $this->getMockBuilder(OutputController::class)->disableOriginalConstructor()->onlyMethods(['getData'])->getMock();
        $panoramas = $this->createMock(PanoramaService::class);
        $panoramas->method('read')->willReturn($panorama);
        $panoramas->method('isOwn')->with(3)->willReturn(!$shared);
        $reports = $this->createMock(ReportService::class);
        $reports->expects($shared ? $this->never() : $this->any())->method('read')->willReturn($metadata);
        $shares = $this->createMock(ShareService::class);
        $shares->method('getSharedItems')->willReturn([]);
        if ($shared) {
            $shares->expects($this->once())->method('getSharedPanoramaReport')->with(7, 3)->willReturn($authorized ? $metadata : []);
        }
        $request = $this->getMockBuilder(\OCP\IRequest::class)->addMethods(['getHeader'])->getMock();
        $request->method('getHeader')->willReturn($etag);
        foreach (['PanoramaService' => $panoramas, 'ReportService' => $reports, 'ShareService' => $shares, 'request' => $request] as $key => $value) {
            $property = new \ReflectionProperty(OutputController::class, $key);
            $property->setAccessible(true);
            $property->setValue($controller, $value);
        }
        return [$controller, $metadata];
    }

    public function testSharedViewerCanUseOnlySavedVariableAndBypassesMatchingEtag(): void {
        [$controller, $original] = $this->setupController(true);
        $controller->expects($this->once())->method('getData')->with($this->callback(function ($metadata) {
            $this->assertArrayNotHasKey('cacheKey', $metadata);
            $filters = json_decode($metadata['filteroptions'], true)['filter'];
            $this->assertSame([['dimension' => 'dimension1', 'option' => 'EQ', 'value' => '%last2months%']], $filters);
            return true;
        }))->willReturn(['error' => 0, 'data' => []]);
        $response = $controller->readPanorama(7, 3, ['date' => '%last2months%']);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('false', $response->getHeaders()['X-Analytics-Cacheable']);
        $this->assertArrayNotHasKey('ETag', $response->getHeaders());
        $this->assertNull($original['filteroptions']);
    }

    public function testSharedViewerCanLoadReportWithoutVariablesOrSeparateReportOwnership(): void {
        [$controller] = $this->setupController(true, true, true, '');
        $data = ['error' => 0, 'data' => [['2026', 10]]];
        $controller->expects($this->once())->method('getData')
            ->with($this->callback(fn ($metadata) => $metadata['id'] === 7 && $metadata['filteroptions'] === null))
            ->willReturn($data);
        $response = $controller->readPanorama(7, 3);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame($data, $response->getData());
        $this->assertSame('"42"', $response->getHeaders()['ETag']);
    }

    public function testSharedViewerCannotLoadReportOutsidePanorama(): void {
        [$controller] = $this->setupController(true, false);
        $controller->expects($this->never())->method('getData');
        $this->assertInstanceOf(NotFoundResponse::class, $controller->readPanorama(7, 3));
    }

    public function testSharedViewerCannotLoadReportRejectedByPanoramaShareAuthorization(): void {
        [$controller] = $this->setupController(true, true, false);
        $controller->expects($this->never())->method('getData');
        $this->assertInstanceOf(NotFoundResponse::class, $controller->readPanorama(7, 3));
    }

    public function testSharedViewerRetainsMatchingEtagBehavior(): void {
        [$controller] = $this->setupController(true);
        $controller->expects($this->never())->method('getData');
        $this->assertSame(304, $controller->readPanorama(7, 3)->getStatus());
    }

    public function testUnrelatedReportIsRejectedBeforeDataRead(): void {
        [$controller] = $this->setupController(false, false);
        $controller->expects($this->never())->method('getData');
        $this->assertInstanceOf(NotFoundResponse::class, $controller->readPanorama(7, 3, []));
    }

    public function testCrossPanoramaShareIsRejected(): void {
        [$controller] = $this->setupController();
        $panoramas = $this->createMock(PanoramaService::class);
        $panoramas->method('read')->willReturn([]);
        $property = new \ReflectionProperty(OutputController::class, 'PanoramaService');
        $property->setAccessible(true);
        $property->setValue($controller, $panoramas);
        $controller->expects($this->never())->method('getData');
        $this->assertInstanceOf(NotFoundResponse::class, $controller->readPanorama(7, 99, ['date' => '2026']));
    }

    public function testUnknownVariableAndMissingContextAreRejected(): void {
        [$controller] = $this->setupController();
        $controller->expects($this->never())->method('getData');
        $this->assertSame(400, $controller->readPanorama(7, 3, ['unknown' => 'x'])->getStatus());
        $this->assertSame(400, $controller->readPanorama(7, null, ['date' => 'x'])->getStatus());
    }

    public function testResetRetainsExistingCacheBehavior(): void {
        [$controller] = $this->setupController();
        $controller->expects($this->never())->method('getData');
        $this->assertSame(304, $controller->readPanorama(7, 3, [])->getStatus());
    }
}
