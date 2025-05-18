<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Datasource;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Psr\Log\LoggerInterface;

class LocalSpreadsheet implements IDatasource {
	private $logger;
	private $rootFolder;
	private $l10n;

	public function __construct(
		IL10N           $l10n,
		LoggerInterface $logger,
		IRootFolder     $rootFolder
	) {
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
	}

	/**
         * @return string Display Name of the data source
	 */
	public function getName(): string {
		return $this->l10n->t('Local') . ': Spreadsheet';
	}

	/**
         * @return int digit unique data source id
	 */
	public function getId(): int {
		return 7;
	}

	/**
	 * @return array available options of the data source
	 */
	public function getTemplate(): array {
		$template = array();
		$template[] = [
			'id' => 'link',
			'name' => $this->l10n->t('File'),
			'placeholder' => $this->l10n->t('File'),
			'type' => 'filePicker'
		];
		$template[] = [
			'id' => 'sheet',
			'name' => $this->l10n->t('Sheet'),
			'placeholder' => $this->l10n->t('sheet name')
		];
		$template[] = [
			'id' => 'range',
			'name' => $this->l10n->t('Cell range'),
			'placeholder' => $this->l10n->t('e.g. A1:C3,A5:C5')
		];
		return $template;
	}

	/**
	 * Read the Data
	 * @param $option
	 * @return array
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws Exception
	 * @throws \PhpOffice\PhpSpreadsheet\Exception
	 */
	public function readData($option): array {
		include_once __DIR__ . '/../../vendor/autoload.php';
		$header = $dataClean = $data = array();
		$headerrow = $error = 0;

		$file = $this->rootFolder->getUserFolder($option['user_id'])->get($option['link']);
		$fileName = $file->getStorage()->getLocalFile($file->getInternalPath());

		$inputFileType = IOFactory::identify($fileName);
		$reader = IOFactory::createReader($inputFileType);
		//$reader->setReadDataOnly(true); disabled as idDate is not working otherwise
		if (strlen($option['sheet']) > 0) {
			$reader->setLoadSheetsOnly([$option['sheet']]);
		}
		$spreadsheet = $reader->load($fileName);

		// separated columns can be selected via ranges e.g. "A1:B9,C1:C9"
		// these ranges are read and linked
		$ranges = str_getcsv($option['range']);
		foreach ($ranges as $range) {
			$values = $spreadsheet->getActiveSheet()
								  ->rangeToArray($range, // The worksheet range that we want to retrieve
									  null,         // Value that should be returned for empty cells
									  true,   // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
									  true,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
									  false       // Should the array be indexed by cell row and cell column
								  );

			$values = $this->convertExcelDate($spreadsheet, $values, $range);

			if (empty($data)) {
				// first range will fill the array with all rows
				$data = $values;
			} else {
				// further columns will be attached to the first ones
				foreach ($data as $key => $value) {
					$data[$key] = array_merge($value, $values[$key]);
				}
			}
		}

		// Remove columns that are completely null
		$data = $this->removeNullColumns($data);

		foreach ($data as $key => $value) {
			if ($headerrow === 0) {
				$header = array_values($value);
				$headerrow = 1;
			} else if (!$this->containsOnlyNull($value)) {
				array_push($dataClean, array_values($value));
			}
		}

		return [
			'header' => $header,
			'dimensions' => array_slice($header, 0, count($header) - 1),
			'data' => $dataClean,
			'error' => $error,
		];
	}

	private function removeNullColumns(array $data): array {
		// Transpose the data to work with columns as rows
		$transposedData = array_map(null, ...$data);

		// Filter out columns that contain only null values
		$filteredData = array_filter($transposedData, function ($column) {
			return !$this->containsOnlyNull($column);
		});

		// Transpose back to original row-column structure
		return array_map(null, ...$filteredData);
	}

	/**
	 * @param $array
	 * @return bool
	 */
	private function containsOnlyNull($array): bool {
		return !(array_reduce($array, function ($carry, $item) {
				return $carry += (is_null($item) ? 0 : 1);
			}, 0) > 0);
	}

	/**
	 * every cell is checked if it is an excel date (stored in number of days since 1990)
	 * then, the date format from excel is reapplied in php
	 * @param $spreadsheet
	 * @param $values
	 * @param $range
	 * @return array
	 * @throws \PhpOffice\PhpSpreadsheet\Exception
	 */
	private function convertExcelDate($spreadsheet, $values, $range): array {
		$map = [
			"yyyy" => "Y",  // Four-digit year
			"yy" => "y",    // Two-digit year
			"MM" => "m",    // Two-digit month
			"mmm" => "M",   // Three-letter month abbreviation
			"mm" => "i",    // Two-digit minutes (lowercase)
			"dd" => "d",    // Two-digit day
			"d" => "j",     // Day without leading zeros
			"hh" => "H",    // 24-hour format
			"h" => "G",     // 12-hour format
			"ss" => "s"     // Seconds
		];

		$start = str_getcsv($range, ':');
		$startCell = Coordinate::coordinateFromString($start[0]);
		$startColumn = (int)Coordinate::columnIndexFromString($startCell[0]);
		$startRow = (int)$startCell[1];

		foreach ($values as $rowIndex => $row) {
			foreach ($row as $columnIndex => $cellValue) {
				$columnLetter = Coordinate::stringFromColumnIndex($columnIndex + $startColumn);
				$rowNumber = $rowIndex + $startRow;
				$coordinate = $columnLetter . $rowNumber;
				$cell = $spreadsheet->getActiveSheet()->getCell($coordinate);

				// Check if it's part of a merged range
				if ($cell->isInMergeRange()) {
					// Overwrite the cell with the top-left cell of the merged range
					$mergedRange = $cell->getMergeRange();
					[$startCell] = explode(':', $mergedRange);
					$cell = $spreadsheet->getActiveSheet()->getCell($startCell);
					$values[$rowIndex][$columnIndex] = $cell->getValue();
				}

				$excelFormat = $cell->getStyle()->getNumberFormat()->getFormatCode();

				if (preg_match('/%/', $excelFormat)) {
					// Convert percentage to decimal value
					$cellValue = $cell->getCalculatedValue();
					$values[$rowIndex][$columnIndex] = round($cellValue, 2);
				} elseif (Date::isDateTime($cell)) {
					// handle date values
					$excelFormat = rtrim($excelFormat, ";@");

					// Check if it's a duration format (e.g., h:mm, [h]:mm, h:mm:ss)
					if (preg_match('/[h]+:?[m]+:?[s]*/i', $excelFormat)) {
						// Convert time duration to decimal
						$excelTime = $cell->getCalculatedValue();
						$totalHours = Date::excelToDateTimeObject($excelTime)->format('G'); // Extract hours
						$totalMinutes = Date::excelToDateTimeObject($excelTime)->format('i'); // Extract minutes
						$totalSeconds = Date::excelToDateTimeObject($excelTime)->format('s'); // Extract seconds

						// Convert the time to decimal (minutes)
						$totalMinutesValue = ($totalHours * 60) + $totalMinutes + ($totalSeconds / 60);
						$values[$rowIndex][$columnIndex] = round($totalMinutesValue, 2); // Rounded to 2 decimal places
					} else {
						// Regular date formatting
						$date = Date::excelToDateTimeObject($cell->getCalculatedValue());
						$targetFormat = strtr($excelFormat, $map);
						$values[$rowIndex][$columnIndex] = $date->format($targetFormat);
					}
				}
			}
		}
		return $values;
	}
}