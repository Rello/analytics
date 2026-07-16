<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Datasource;

use OCA\Analytics\Datasource\LocalCsv;
use OCA\Analytics\Service\VariableService;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LocalCsvTest extends TestCase {
	public function testReadDataPreservesTwoColumnRows(): void {
		$file = new class {
			public function getMTime(): int {
				return 1;
			}

			public function getContent(): string {
				return "CATEGORY,EURO\nENTERTAINMENT,9798\nLIVING,10943";
			}
		};
		$userFolder = new class($file) {
			private $file;

			public function __construct($file) {
				$this->file = $file;
			}

			public function get(string $path) {
				return $this->file;
			}
		};
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('u1')
			->willReturn($userFolder);

		$csv = new LocalCsv(
			'u1',
			new FakeL10N(),
			new NullLogger(),
			$rootFolder,
			$this->createMock(VariableService::class)
		);

		$result = $csv->readData([
			'user_id' => 'u1',
			'link' => '/EXPENDITURE.CATEGORIES.csv',
		]);

		$this->assertSame(['CATEGORY', 'EURO'], $result['header']);
		$this->assertSame([
			['ENTERTAINMENT', '9798'],
			['LIVING', '10943'],
		], $result['data']);
	}
}
