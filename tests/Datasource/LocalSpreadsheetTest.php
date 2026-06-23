<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Datasource;

use OCA\Analytics\Datasource\LocalSpreadsheet;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LocalSpreadsheetTest extends TestCase {
	public function testValidateRangesRejectsOversizedRange(): void {
		$spreadsheet = new LocalSpreadsheet(
			new FakeL10N(),
			new NullLogger(),
			$this->createMock(\OCP\Files\IRootFolder::class)
		);

		$method = new \ReflectionMethod(LocalSpreadsheet::class, 'validateRanges');
		$method->setAccessible(true);

		$this->assertSame('Spreadsheet range is too large', $method->invoke($spreadsheet, 'A1:A9999999'));
	}
}
