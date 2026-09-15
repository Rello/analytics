<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Service\FlexibleStorageService;
use OCA\Analytics\Service\ReportService;
use OCA\Analytics\Service\StorageService;
use OCA\Analytics\Service\ThresholdService;
use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Storage\DatasetStorageResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class StorageServiceTest extends TestCase {
	public function testUpdateReturnsInvalidNumericValueReason(): void {
		$storageMapper = $this->createMock(StorageMapper::class);
		$storageMapper->expects($this->never())->method('create');

		$result = $this->createService($storageMapper)->update(
			42,
			'Solar',
			'09/2026',
			'not numeric',
			'admin',
			null,
			null,
			false
		);

		$this->assertSame(1, $result['error']);
		$this->assertSame('Last field must be a valid number', $result['message']);
	}

	public function testUpdateReturnsStorageExceptionReason(): void {
		$storageMapper = $this->createMock(StorageMapper::class);
		$storageMapper->expects($this->once())
			->method('create')
			->willThrowException(new \RuntimeException('Database write failed'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				'Analytics data row storage failed for dataset 42: Database write failed',
				$this->callback(function (array $context): bool {
					$this->assertSame(42, $context['datasetId']);
					$this->assertSame(\RuntimeException::class, $context['exceptionClass']);
					$this->assertInstanceOf(\RuntimeException::class, $context['exception']);
					return true;
				})
			);

		$result = $this->createService($storageMapper, $logger)->update(
			42,
			'Solar',
			'09/2026',
			'39.55',
			'admin',
			null,
			null,
			false
		);

		$this->assertSame(1, $result['error']);
		$this->assertSame('Storage operation failed; see preceding Analytics log entry', $result['message']);
	}

	private function createService(StorageMapper $storageMapper, ?LoggerInterface $logger = null): StorageService {
		$resolver = $this->createMock(DatasetStorageResolver::class);
		$resolver->method('resolve')->with(42)->willReturn(['mode' => DatasetStorageResolver::LEGACY]);
		$variableService = $this->createMock(VariableService::class);
		$variableService->method('replaceTextVariablesSingle')->willReturnArgument(0);

		return new StorageService(
			$logger ?? new NullLogger(),
			$storageMapper,
			$resolver,
			$this->createMock(FlexibleStorageService::class),
			$this->createMock(ThresholdService::class),
			$variableService,
			$this->createMock(ReportService::class)
		);
	}
}
