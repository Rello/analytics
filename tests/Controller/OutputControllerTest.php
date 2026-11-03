<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Controller;

use OCA\Analytics\Controller\OutputController;
use PHPUnit\Framework\TestCase;

class OutputControllerTest extends TestCase {
	public function testDatasourceCacheKeyIsReusedWhenReportVersionMatches(): void {
		$controller = $this->createControllerWithoutConstructor();

		$cacheKey = $this->invokePrivateMethod(
			$controller,
			'getDatasourceCacheKey',
			[['version' => 42], '42:csv-1234']
		);

		$this->assertSame('csv-1234', $cacheKey);
	}

	public function testDatasourceCacheKeyIsRejectedWhenReportVersionChanged(): void {
		$controller = $this->createControllerWithoutConstructor();

		$cacheKey = $this->invokePrivateMethod(
			$controller,
			'getDatasourceCacheKey',
			[['version' => 43], '42:csv-1234']
		);

		$this->assertNull($cacheKey);
	}

	public function testLegacyDatasourceCacheKeyIsRejected(): void {
		$controller = $this->createControllerWithoutConstructor();

		$cacheKey = $this->invokePrivateMethod(
			$controller,
			'getDatasourceCacheKey',
			[['version' => 42], 'csv-1234']
		);

		$this->assertNull($cacheKey);
	}

	public function testExternalCacheKeyContainsReportAndDatasourceVersions(): void {
		$controller = $this->createControllerWithoutConstructor();

		$cacheKey = $this->invokePrivateMethod(
			$controller,
			'buildExternalCacheKey',
			[['version' => 42], 'csv-1234']
		);

		$this->assertSame('42:csv-1234', $cacheKey);
	}

	public function testRecipientOptionsRequireUpdatePermission(): void {
		foreach ([2, '2', 1, null] as $permissions) {
			$metadata = [
				'filteroptions' => 'owner filters',
				'dataoptions' => 'owner columns',
				'chartoptions' => 'owner chart options',
				'tableoptions' => 'owner table options',
				'chart' => 'bar',
				'visualization' => 'chart',
			];
			if ($permissions !== null) {
				$metadata['permissions'] = $permissions;
			}
			$recipientOptions = [
				'filteroptions' => '{}',
				'dataoptions' => '[]',
				'chartoptions' => '{}',
				'tableoptions' => '{"length":100}',
				'chart' => 'line',
				'visualization' => 'table',
			];
			set_error_handler(static function (int $severity, string $message): void {
				throw new \ErrorException($message, 0, $severity);
			});
			try {
				$result = $this->invokePrivateMethod(
					$this->createControllerWithoutConstructor(),
					'evaluateCanFilter',
					array_merge([$metadata], array_values($recipientOptions))
				);
			} finally {
				restore_error_handler();
			}
			$this->assertSame((int)$permissions === 2 ? array_replace($metadata, $recipientOptions) : $metadata, $result);
		}
	}

	private function createControllerWithoutConstructor(): OutputController {
		$reflection = new \ReflectionClass(OutputController::class);
		return $reflection->newInstanceWithoutConstructor();
	}

	private function invokePrivateMethod(OutputController $controller, string $methodName, array $arguments) {
		$reflection = new \ReflectionMethod(OutputController::class, $methodName);
		return $reflection->invokeArgs($controller, $arguments);
	}
}
