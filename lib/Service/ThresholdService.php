<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCA\Analytics\Db\FlexibleStorageMapper;
use OCA\Analytics\Exception\FlexibleStorageException;
use OCA\Analytics\Notification\NotificationManager;
use OCA\Analytics\Storage\DatasetStorageResolver;
use OCA\Analytics\Storage\FlexibleColumnResolver;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;
use OCP\IL10N;

class ThresholdService {
	private const CALCULATED_COLUMN_DIMENSION_OFFSET = 10000;

	private $logger;
	private $ThresholdMapper;
	private $ReportMapper;
	private $NotificationManager;
	private $VariableService;
	private $l10n;
	private DatasetStorageResolver $DatasetStorageResolver;
	private FlexibleStorageMapper $FlexibleStorageMapper;

	public function __construct(
		LoggerInterface     $logger,
		ThresholdMapper     $ThresholdMapper,
		NotificationManager $NotificationManager,
		ReportMapper        $ReportMapper,
		VariableService     $VariableService,
		IL10N               $l10n,
		DatasetStorageResolver $DatasetStorageResolver,
		FlexibleStorageMapper $FlexibleStorageMapper
	) {
		$this->logger = $logger;
		$this->ThresholdMapper = $ThresholdMapper;
		$this->NotificationManager = $NotificationManager;
		$this->ReportMapper = $ReportMapper;
		$this->VariableService = $VariableService;
		$this->l10n = $l10n;
		$this->DatasetStorageResolver = $DatasetStorageResolver;
		$this->FlexibleStorageMapper = $FlexibleStorageMapper;
	}

	/**
	 * read all thresholds for a dataset
	 *
	 * @param int $reportId
	 * @return array
	 */
	public function read(int $reportId) {
		$thresholds = $this->ThresholdMapper->getThresholdsByReport($reportId);
		return $this->VariableService->replaceThresholdsVariables($thresholds);
	}

	/**
	 * read all thresholds for a dataset without any replaced text variables
	 *
	 * @param int $reportId
	 * @return array
	 */
	public function readRaw(int $reportId) {
		return $this->ThresholdMapper->getThresholdsByReport($reportId);
	}

	/**
	 * create new threshold for dataset
	 *
	 * @param int $reportId
	 * @param $dimension
	 * @param $option
	 * @param $value
	 * @param int $severity
	 * @param $coloring
	 * @return int
	 * @throws Exception
	 */
	public function create(int $reportId, $dimension, $option, $value, int $severity, $coloring, ?string $sourceColumnRef = null) {
		$report = $this->ReportMapper->readOwn($reportId);
		if (empty($report)) {
			return 0;
		}
		if ((int)($report['dataset'] ?? 0) > 0) {
			$storage = $this->DatasetStorageResolver->resolve((int)$report['dataset'], true);
			if ($storage['mode'] === DatasetStorageResolver::FLEXIBLE_SHARED) {
				if ($sourceColumnRef === null) {
					throw new FlexibleStorageException('missing_threshold_column', 'Flexible thresholds require a source column reference.', ['field' => 'sourceColumnRef']);
				}
				(new FlexibleColumnResolver($this->FlexibleStorageMapper->getColumns((int)$report['dataset'])))->resolve($sourceColumnRef);
			} elseif ($sourceColumnRef !== null) {
				throw new FlexibleStorageException('invalid_threshold_column', 'Source column references are available only for flexible datasets.', ['field' => 'sourceColumnRef']);
			}
		} elseif ($sourceColumnRef !== null) {
			throw new FlexibleStorageException('invalid_threshold_column', 'Source column references are available only for flexible datasets.', ['field' => 'sourceColumnRef']);
		}
		$this->ReportMapper->increaseVersionByReport($reportId);
		return $this->ThresholdMapper->create($reportId, $dimension, $value, $option, $severity, $coloring, $sourceColumnRef);
	}

	private function floatvalue($val) {
		// if value is a 3 digit comma number with one leading zero like 0,111, it should not go through the 1000 separator removal
		if (preg_match('/(?<=\b0)\,(?=\d{3}\b)/', $val) === 0 && preg_match('/(?<=\b0)\.(?=\d{3}\b)/', $val) === 0) {
			// remove , as 1000 separator
			$val = preg_replace('/(?<=\d)\,(?=\d{3}\b)/', '', $val);
			// remove . as 1000 separator
			$val = preg_replace('/(?<=\d)\.(?=\d{3}\b)/', '', $val);
		}
		// convert remaining comma to decimal point
		$val = str_replace(",", ".", $val);
		if (is_numeric($val)) {
			return number_format(floatval($val), 2, '.', '');
		} else {
			return false;
		}
	}

	private function normalizeNumberString(string $str): string {
		$str = trim($str);
		$hasComma = str_contains($str, ',');
		$hasDot = str_contains($str, '.');

		if ($hasComma && $hasDot) {
			if (strrpos($str, ',') > strrpos($str, '.')) {
				// comma as decimal separator
				$str = str_replace('.', '', $str);
				$str = str_replace(',', '.', $str);
			} else {
				// dot as decimal separator
				$str = str_replace(',', '', $str);
			}
		} elseif ($hasComma) {
			$idx = strrpos($str, ',');
			$digits = strlen($str) - $idx - 1;
			if ($digits <= 2) {
				$str = str_replace(',', '.', $str);
			} else {
				$str = str_replace(',', '', $str);
			}
		} elseif ($hasDot) {
			$idx = strrpos($str, '.');
			$digits = strlen($str) - $idx - 1;
			if ($digits > 2) {
				$str = str_replace('.', '', $str);
			}
		}

		return str_replace(' ', '', $str);
	}

	/**
	 * Compare two values and return comparison result similar to spaceship operator
	 *
	 * @param $a
	 * @param $b
	 * @return int
	 */
	private function compareValues($a, $b) {
		if (is_numeric($a) && is_numeric($b)) {
			$normA = $this->normalizeNumberString((string)$a);
			$normB = $this->normalizeNumberString((string)$b);
			return floatval($normA) <=> floatval($normB);
		}
		return strcmp((string)$a, (string)$b);
	}

	/**
	 * Delete threshold
	 *
	 * @param int $thresholdId
	 * @return bool
	 */
	public function delete(int $thresholdId) {
		$reportId = $this->ThresholdMapper->getOwnReportByThreshold($thresholdId);
		if ($reportId === 0) {
			return false;
		}
		$this->logger->info('reportId: ' . $reportId);
		if (!empty($this->ReportMapper->readOwn($reportId))) {
			$this->ReportMapper->increaseVersionByReport($reportId);
		}
		$this->ThresholdMapper->deleteThreshold($thresholdId);
		return true;
	}

	/**
	 * Update sequence of multiple thresholds
	 *
	 * @param array $orderedIds
	 * @return bool
	 */
	public function reorder(array $orderedIds): bool {
		$position = 1;
		$reportId = 0;
		foreach ($orderedIds as $id) {
			$thresholdReportId = $this->ThresholdMapper->getOwnReportByThreshold((int)$id);
			if ($thresholdReportId === 0) {
				return false;
			}
			$reportId = $thresholdReportId;
			$this->ThresholdMapper->updateSequence((int)$id, $position);
			$position++;
		}
		$this->logger->info('reportId: ' . $reportId);
		if ($reportId !== 0 && !empty($this->ReportMapper->readOwn($reportId))) {
			$this->ReportMapper->increaseVersionByReport($reportId);
		}

		return true;
	}

	/**
	 * validate notification thresholds per report
	 *
	 * @param int $reportId
	 * @param $dimension1
	 * @param $dimension2
	 * @param $value
	 * @param int $insert
	 * @return string
	 * @throws \Exception
	 */
	public function validate(int $reportId, $dimension1, $dimension2, $value, int $insert = 0) {
		$result = null;
		$thresholds = $this->ThresholdMapper->getSevOneThresholdsByReport($reportId);
		$thresholds = $this->VariableService->replaceThresholdsVariables($thresholds);
		$datasetMetadata = $this->ReportMapper->read($reportId);

		foreach ($thresholds as $threshold) {
			if (isset($datasetMetadata['user_id']) && $threshold['user_id'] !== $datasetMetadata['user_id']) {
				continue;
			}
			$dimIndex = intval($threshold['dimension']);
			// Calculated columns are evaluated only in the browser-rendered table.
			if ($dimIndex >= self::CALCULATED_COLUMN_DIMENSION_OFFSET) {
				continue;
			}
			switch ($dimIndex) {
				case 0:
					$compare = $dimension1;
					$subject = $datasetMetadata['dimension1'];
					break;
				case 1:
					$compare = $dimension2;
					$subject = $datasetMetadata['dimension2'];
					break;
				default:
					$compare = $value;
					$subject = $datasetMetadata['value'];
			}

			if ($threshold['option'] === 'new' && $insert != 0) {
				$this->NotificationManager->triggerNotification(NotificationManager::SUBJECT_THRESHOLD, $reportId, $threshold['id'], [
					'report' => $datasetMetadata['name'],
					'subject' => $subject,
					'rule' => $this->l10n->t('new record'),
					'value' => ''
				], $threshold['user_id']);
				$result = 'Threshold value met';
			} else {
				$option = strtoupper($threshold['option']);

				// map legacy symbolic options to the new textual ones
				$legacyMap = [
					'=' => 'EQ',
					'>' => 'GT',
					'<' => 'LT',
					'>=' => 'GE',
					'<=' => 'LE',
					'!=' => 'NE',
				];
				if (isset($legacyMap[$option])) {
					$option = $legacyMap[$option];
				}

				switch ($option) {
					case 'EQ':
						$comparison = $this->compareValues($compare, $threshold['target']) === 0;
						break;
					case 'NE':
						$comparison = $this->compareValues($compare, $threshold['target']) !== 0;
						break;
					case 'GT':
						$comparison = $this->compareValues($compare, $threshold['target']) > 0;
						break;
					case 'GE':
						$comparison = $this->compareValues($compare, $threshold['target']) >= 0;
						break;
					case 'LT':
						$comparison = $this->compareValues($compare, $threshold['target']) < 0;
						break;
					case 'LE':
						$comparison = $this->compareValues($compare, $threshold['target']) <= 0;
						break;
					case 'LIKE':
						$comparison = (strpos((string)$compare, (string)$threshold['target']) !== false);
						break;
					case 'IN':
						preg_match_all("/'(?:[^'\\\\]|\\\\.)*'|[^,;]+/", $threshold['target'], $matches);
						$valuesArray = array_map(function ($v) {
							return trim($v, " '");
						}, $matches[0]);
						$comparison = in_array((string)$compare, $valuesArray, true);
						break;
					default:
						$comparison = false;
				}

				if ($comparison) {
					$this->NotificationManager->triggerNotification(NotificationManager::SUBJECT_THRESHOLD, $reportId, $threshold['id'], [
						'report' => $datasetMetadata['name'],
						'subject' => $subject,
						'rule' => $threshold['option'],
						'value' => $threshold['target']
					], $threshold['user_id']);
					$result = 'Threshold value met';
				}
			}
		}
		return $result;
	}

	/** @param array<string,mixed> $values stable column reference to canonical value */
	public function validateFlexible(int $datasetId, array $values, bool $insert): void {
		$this->validateFlexibleRecords($datasetId, [['values' => $values, 'insert' => $insert]]);
	}

	/** @param list<array{values:array<string,mixed>,insert:bool}> $records */
	public function validateFlexibleRecords(int $datasetId, array $records): void {
		$columns = array_column($this->FlexibleStorageMapper->getColumns($datasetId), null, 'ref');
		foreach ($this->ReportMapper->reportsForDataset($datasetId) as $report) {
			$reportId = (int)$report['id'];
			$thresholds = $this->VariableService->replaceThresholdsVariables(
				$this->ThresholdMapper->getSevOneThresholdsByReport($reportId)
			);
			foreach ($records as $record) {
				foreach ($thresholds as $threshold) {
					$reference = $threshold['source_column_ref'] ?? null;
					if (
						!is_string($reference)
						|| !array_key_exists($reference, $record['values'])
						|| !isset($columns[$reference])
						|| (($threshold['user_id'] ?? null) !== ($report['user_id'] ?? null))
					) {
						continue;
					}
					$compare = $record['values'][$reference];
					$matched = $threshold['option'] === 'new' && $record['insert'];
					if (!$matched && $threshold['option'] !== 'new') {
						$matched = $this->matchesThreshold($compare, $threshold['target'], (string)$threshold['option']);
					}
					if (!$matched) {
						continue;
					}
					$this->NotificationManager->triggerNotification(NotificationManager::SUBJECT_THRESHOLD, $reportId, $threshold['id'], [
						'report' => $report['name'],
						'subject' => $columns[$reference]['name'],
						'rule' => $threshold['option'] === 'new' ? $this->l10n->t('new record') : $threshold['option'],
						'value' => $threshold['option'] === 'new' ? '' : $threshold['target'],
					], $threshold['user_id']);
				}
			}
		}
	}

	private function matchesThreshold(mixed $value, mixed $target, string $option): bool {
		$option = strtoupper($option);
		$option = ['=' => 'EQ', '>' => 'GT', '<' => 'LT', '>=' => 'GE', '<=' => 'LE', '!=' => 'NE'][$option] ?? $option;
		return match ($option) {
			'EQ' => $this->compareValues($value, $target) === 0,
			'NE' => $this->compareValues($value, $target) !== 0,
			'GT' => $this->compareValues($value, $target) > 0,
			'GE' => $this->compareValues($value, $target) >= 0,
			'LT' => $this->compareValues($value, $target) < 0,
			'LE' => $this->compareValues($value, $target) <= 0,
			'LIKE' => strpos((string)$value, (string)$target) !== false,
			'IN' => $this->matchesInThreshold($value, (string)$target),
			default => false,
		};
	}

	private function matchesInThreshold(mixed $value, string $target): bool {
		preg_match_all("/'(?:[^'\\\\]|\\\\.)*'|[^,;]+/", $target, $matches);
		$values = array_map(static fn (string $item): string => trim($item, " '"), $matches[0]);
		return in_array((string)$value, $values, true);
	}
}
