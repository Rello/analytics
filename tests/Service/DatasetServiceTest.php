<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ShareService;
use OCA\Analytics\Service\ThresholdService;
use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCP\Files\IRootFolder;
use OCP\ITagManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DatasetServiceTest extends TestCase {
	protected function tearDown(): void {
		$this->setContextChatAvailable(null);
		if (class_exists('\OC')) {
			\OC::$server = null;
		}
		parent::tearDown();
	}

	public function testProviderKeepsDatasetUpdateSuccessfulWhenContextChatFails(): void {
		if (!class_exists('\OC')) {
			eval('class OC { public static $server; }');
		}

		$contextChatManager = new class() {
			public function submitContent(int $datasetId): void {
				throw new \InvalidArgumentException('Entity which should be updated has no id');
			}
		};

		\OC::$server = new class($contextChatManager) {
			private $contextChatManager;

			public function __construct($contextChatManager) {
				$this->contextChatManager = $contextChatManager;
			}

			public function query(string $class) {
				return $this->contextChatManager;
			}
		};

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with(
				'Analytics context chat indexing failed',
				$this->callback(function (array $context): bool {
					$this->assertSame(11, $context['datasetId']);
					$this->assertInstanceOf(\InvalidArgumentException::class, $context['exception']);
					return true;
				})
			);

		$this->setContextChatAvailable(true);

		$service = $this->createService($logger);

		$this->assertTrue($service->provider(11));
	}

	private function createService(LoggerInterface $logger): DatasetService {
		return new DatasetService(
			'u1',
			new FakeL10N(),
			$logger,
			$this->createMock(ITagManager::class),
			$this->createMock(ShareService::class),
			$this->createMock(StorageMapper::class),
			$this->createMock(DatasetMapper::class),
			$this->createMock(ThresholdService::class),
			$this->createMock(DataloadMapper::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(IRootFolder::class),
			$this->createMock(VariableService::class),
			$this->createMock(ReportMapper::class)
		);
	}

	private function setContextChatAvailable(?bool $available): void {
		$property = new \ReflectionProperty(DatasetService::class, 'contextChatAvailable');
		$property->setAccessible(true);
		$property->setValue(null, $available);
	}
}
