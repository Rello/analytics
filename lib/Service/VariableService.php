<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Db\DatasetMapper;
use OCP\IDateTimeFormatter;
use Psr\Log\LoggerInterface;

class VariableService {
	private $logger;
	private $DatasetMapper;
	private $IDateTimeFormatter;

	public function __construct(
		LoggerInterface    $logger,
		DatasetMapper      $DatasetMapper,
		IDateTimeFormatter $IDateTimeFormatter
	) {
		$this->logger = $logger;
		$this->DatasetMapper = $DatasetMapper;
		$this->IDateTimeFormatter = $IDateTimeFormatter;
	}

	/**
	 * replace %*% text variables in thresholds
	 *
	 * @param array $thresholds
	 * @return array
	 */
	public function replaceThresholdsVariables($thresholds) {
		foreach ($thresholds as &$threshold) {
			$fields = ['value'];
			foreach ($fields as $field) {
				isset($threshold[$field]) ? $name = $threshold[$field] : $name = '';
				$parsed = $this->parseFilter($name);
				if (!$parsed) break;
				$threshold[$field] = $parsed['6$startDate'];
			}
		}
		return $thresholds;
	}

	/**
	 * replace %*% text variables in name and subheader
	 *
	 * @param array $datasetMetadata
	 * @return array
	 */
	public function replaceTextVariables($datasetMetadata) {
		$fields = ['name', 'subheader'];
		foreach ($fields as $field) {
			isset($datasetMetadata[$field]) ? $name = $datasetMetadata[$field] : $name = '';

			preg_match_all("/%.*?%/", $name, $matches);
			if (count($matches[0]) > 0) {
				foreach ($matches[0] as $match) {
					$replace = null;
					if ($match === '%currentDate%') {
						$replace = $this->IDateTimeFormatter->formatDate(time(), 'short');
					} elseif ($match === '%currentTime%') {
						$replace = $this->IDateTimeFormatter->formatTime(time(), 'short');
					} elseif ($match === '%now%') {
						$replace = time();
					} elseif ($match === '%lastUpdateDate%') {
						$timestamp = $this->DatasetMapper->getLastUpdate($datasetMetadata['dataset']);
						$replace = $this->IDateTimeFormatter->formatDate($timestamp, 'short');
					} elseif ($match === '%lastUpdateTime%') {
						$timestamp = $this->DatasetMapper->getLastUpdate($datasetMetadata['dataset']);
						$replace = $this->IDateTimeFormatter->formatTime($timestamp, 'short');
					} elseif ($match === '%owner%') {
						$owner = $this->DatasetMapper->getOwner($datasetMetadata['dataset']);
						$replace = $owner;
					}
					if ($replace !== null) {
						$datasetMetadata[$field] = preg_replace('/' . $match . '/', $replace, $datasetMetadata[$field]);
					}
				}
			}
		}
		return $datasetMetadata;
	}

	/**
	 * replace variables in single field or dimension
	 * used in StorageService when data is loaded or entered manually
	 *
	 * @param $field
	 * @return array
	 */
	public function replaceTextVariablesSingle($field) {
		if ($field !== null) {
			preg_match_all("/%.*?%/", $field, $matches);
			if (count($matches[0]) > 0) {
				foreach ($matches[0] as $match) {
					$replace = null;
					if ($match === '%currentDate%') {
						$replace = date("Y-m-d");
					} elseif ($match === '%currentTime%') {
						$replace = $this->IDateTimeFormatter->formatTime(time(), 'short');
					} elseif ($match === '%now%') {
						$replace = time();
					}
					if ($replace !== null) {
						$field = preg_replace('/' . $match . '/', $replace, $field);
					}
				}
			}
		}
		return $field;
	}

	/**
	 * replace variables in single field
	 * used in: API
	 *
	 * @param $columns
	 * @return string
	 */
	public function replaceDatasourceColumns($columns) {
		$parsed = $this->parseFilter($columns);
		$format = $this->parseFormat($columns);
		if (!$parsed) return $columns;
		return date($format, $parsed['value']);
	}

	/**
	 * replace variables in filters and apply format
	 *
	 * @param $reportMetadata
	 * @return array
	 */
	public function replaceFilterVariables($reportMetadata) {
		if ($reportMetadata['filteroptions'] !== null) {
			$filteroptions = json_decode($reportMetadata['filteroptions'], true);
			if (isset($filteroptions['filter'])) {
				foreach ($filteroptions['filter'] as $key => $value) {

					// get the parsed filter
					$parsed = $this->parseFilter($value['value']);
					if (!$parsed) continue;
					// overwrite the filter option. Required for quarters => between
					$filteroptions['filter'][$key]['option'] = $parsed['option'];


					// if a parser is selected in the chart options, it should also be valid here automatically
					if (isset($reportMetadata['chartoptions'])) {
						$chartOptions = json_decode($reportMetadata['chartoptions'], true);
						if (isset($chartOptions['scales']['xAxes']['time']['parser'])) {
							$format = $chartOptions['scales']['xAxes']['time']['parser'];
						}
					}
					$format = $this->parseFormat($value['value']);

					// translate commonly known X timestamp format to U for php
					if ($format === 'X') $format = 'U';

					if (is_array($parsed['value'])) {
						// Format both start and end values for BETWEEN
						$filteroptions['filter'][$key]['value'] = [
							date($format, $parsed['value'][0]),
							date($format, $parsed['value'][1])
						];
					} else {
						$filteroptions['filter'][$key]['value'] = date($format, $parsed['value']);
					}
				}
			}
			$reportMetadata['filteroptions'] = json_encode($filteroptions);
		}
		return $reportMetadata;
	}

	/**
	 * parsing of %*% variables
	 *
	 * @param $filter
	 * @return array|bool
	 */
	private function parseFilter($filter) {
		preg_match_all("/(?<=%).*(?=%)/", $filter, $matches);
		if (count($matches[0]) > 0) {
			$filter = $matches[0][0];
			preg_match('/(first|second|third|fourth|last|next|current|to|yester)?/', $filter, $directionMatch); // direction
			preg_match('/[0-9]+/', $filter, $offsetMatch); // how much
			preg_match('/(day|days|week|weeks|month|months|year|years|quarter|quarters)$/', $filter, $unitMatch); // unit

			if (!$directionMatch[0] || !$unitMatch[0]) {
				// no known text variables found
				return false;
			}

			// if no offset is specified, apply 1 as default
			!isset($offsetMatch[0]) ? $offset = 1 : $offset = $offsetMatch[0];

			// remove "s" to unify e.g. weeks => week
			$unit = rtrim($unitMatch[0], 's');
			$direction = strtolower($directionMatch[0]);

			if ($unit === 'quarter') {
				$currentMonth = (int)date('n');
				$currentYear = (int)date('Y');
				$currentQuarter = (int)ceil($currentMonth / 3);

				$targetQuarter = $currentQuarter;
				$targetYear = $currentYear;

				switch ($direction) {
					case 'first':
						$targetQuarter = 1;
						break;
					case 'second':
						$targetQuarter = 2;
						break;
					case 'third':
						$targetQuarter = 3;
						break;
					case 'fourth':
						$targetQuarter = 4;
						break;
					case 'last':
					case 'yester':
						$targetQuarter = $currentQuarter - $offset;
						while ($targetQuarter < 1) {
							$targetQuarter += 4;
							$targetYear -= 1;
						}
						break;
					case 'next':
						$targetQuarter = $currentQuarter + $offset;
						while ($targetQuarter > 4) {
							$targetQuarter -= 4;
							$targetYear += 1;
						}
						break;
					case 'current':
					default:
						// current quarter, do nothing
						break;
				}

				// First month of the target quarter
				$firstMonthOfQuarter = (($targetQuarter - 1) * 3) + 1;
				$startTS = strtotime("{$targetYear}-{$firstMonthOfQuarter}-01");
				$start = date("Y-m-d", $startTS);

				// Last month of the target quarter
				$lastMonthOfQuarter = $firstMonthOfQuarter + 2;
				$endTS = strtotime("last day of {$targetYear}-{$lastMonthOfQuarter}-01 23:59:59");
				$end = date("Y-m-d", $endTS);

				$return = [
					'value' => [$startTS, $endTS],
					'option' => 'BETWEEN',
					'1$filter' => $filter,
					'2$direction' => $direction,
					'3$target_quarter' => $targetQuarter,
					'4$target_year' => $targetYear,
					'5$startDate' => $start,
					'6$startTS' => $startTS,
					'7$endDate' => $end,
					'8$endTS' => $endTS,
				];
				//$this->logger->info('parseFilter quarter: ' . json_encode($return));
			} else {
				// Existing logic for other units
				if ($direction === "last" || $direction === "yester") {
					$dir = '-';
				} elseif ($direction === "next") {
					$dir = '+';
				} else {
					$dir = '+';
					$offset = 0;
				}
				$timeString = $dir . $offset . ' ' . $unit;
				$baseDate = strtotime($timeString);

				if ($unit === 'day') {
					$startString = 'today';
				} elseif ($unit === 'month') {
					$startString = 'first day of this month';
				} elseif ($unit === 'year') {
					$startString = 'first day of January';
				} else {
					$startString = 'first day of this ' . $unit;
				}
				$startTS = strtotime($startString, $baseDate);
				$start = date("Y-m-d", $startTS);

				$return = [
					'value' => $startTS,
					'option' => 'GT',
					'1$filter' => $filter,
					'2$timestring' => $timeString,
					'3$target' => $baseDate,
					'4$target_clean' => date("Y-m-d", $baseDate),
					'5$startString' => $startString,
					'6$startDate' => $start,
					'7$startTS' => $startTS,
				];
				//$this->logger->info('parseFilter: ' . json_encode($return));
			}
		} else {
			$return = false;
		}
		return $return;
	}

	/**
	 * parsing of ( ) format instructions
	 *
	 * @param $filter
	 * @return string
	 */
	private function parseFormat($filter) {
		preg_match_all("/(?<=\().*(?=\))/", $filter, $matches);
		if (count($matches[0]) > 0) {
			return $matches[0][0];
		} else {
			return 'Y-m-d H:m:s';
		}
	}
}