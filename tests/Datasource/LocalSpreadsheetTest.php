<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Datasource;

use OCA\Analytics\Datasource\LocalSpreadsheet;
use OCA\Analytics\Tests\Stubs\FakeL10N;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\NullLogger;

class LocalSpreadsheetTest extends TestCase {
	public function testListWorksheetNamesReadsSelectedWorkbook(): void {
		$path = __DIR__ . '/../../vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/locale/Translations.xlsx';
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getInternalPath')->willReturn('book.xlsx');
		$file->method('getStorage')->willReturn(new class($path) {
			public function __construct(private string $path) {}
			public function getLocalFile(string $internalPath): string { return $this->path; }
		});
		$folder = new class($file) {
			public function __construct(private $file) {}
			public function get(string $path) { return $this->file; }
		};
		$root = $this->createMock(\OCP\Files\IRootFolder::class);
		$root->expects($this->once())->method('getUserFolder')->with('u1')->willReturn($folder);
		$datasource = new LocalSpreadsheet(new FakeL10N(), new NullLogger(), $root);

		$this->assertSame(['Excel Localisation', 'Excel Functions'], $datasource->listWorksheetNames('u1', '/reports/book.xlsx'));
	}

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

	public function testConvertExcelDateReturnsCanonicalDateAndTimeValues(): void {
		$datasource = new LocalSpreadsheet(
			new FakeL10N(),
			new NullLogger(),
			$this->createMock(\OCP\Files\IRootFolder::class)
		);
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setCellValue('A1', Date::PHPToExcel(new \DateTimeImmutable('2026-08-28 01:15:00')));
		$sheet->getStyle('A1')->getNumberFormat()->setFormatCode('dd.mm.yy hh:mm');
		$sheet->setCellValue('B1', Date::PHPToExcel(new \DateTimeImmutable('2026-08-28')));
		$sheet->getStyle('B1')->getNumberFormat()->setFormatCode('dd/mm/yy');
		$sheet->setCellValue('C1', Date::PHPToExcel(new \DateTimeImmutable('2026-08-28 01:15:30')));
		$sheet->getStyle('C1')->getNumberFormat()->setFormatCode('dd.mm.yy hh:mm:ss');
		$sheet->setCellValue('D1', Date::PHPToExcel(new \DateTimeImmutable('1899-12-31 01:15:00')));
		$sheet->getStyle('D1')->getNumberFormat()->setFormatCode('[h]:mm');

		$method = new \ReflectionMethod(LocalSpreadsheet::class, 'convertExcelDate');
		$method->setAccessible(true);

		$this->assertSame(
			[['2026-08-28 01:15', '2026-08-28', '2026-08-28 01:15:30', 75.0]],
			$method->invoke($datasource, $spreadsheet, [['', '', '', '']], 'A1:D1')
		);
	}
}
