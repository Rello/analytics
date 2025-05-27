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
use OCA\Analytics\Notification\NotificationManager;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;
use OCP\IL10N;

class ThresholdService {
	private $logger;
	private $ThresholdMapper;
	private $ReportMapper;
	private $NotificationManager;
	private $VariableService;
	private $l10n;

	public function __construct(
		LoggerInterface $logger,
		ThresholdMapper $ThresholdMapper,
		NotificationManager $NotificationManager,
		ReportMapper $ReportMapper,
		VariableService $VariableService,
		IL10N $l10n
	) {
		$this->logger = $logger;
		$this->ThresholdMapper = $ThresholdMapper;
		$this->NotificationManager = $NotificationManager;
		$this->ReportMapper = $ReportMapper;
		$this->VariableService = $VariableService;
		$this->l10n = $l10n;
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
        public function create(int $reportId, $dimension, $option, $value, int $severity, $coloring) {
                return $this->ThresholdMapper->create($reportId, $dimension, $value, $option, $severity, $coloring);
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

	/**
	 * Delete threshold
	 *
	 * @param int $thresholdId
	 * @return bool
	 */
	public function delete(int $thresholdId) {
		$this->ThresholdMapper->deleteThreshold($thresholdId);
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
                $result = '';
                $thresholds = $this->ThresholdMapper->getSevOneThresholdsByReport($reportId);
                $datasetMetadata = $this->ReportMapper->read($reportId);

                foreach ($thresholds as $threshold) {
                        $dimIndex = intval($threshold['dimension2']);
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
                                if (in_array($threshold['option'], ['<','>','<=','>='])) {
                                        $comparison = version_compare(floatval($compare), floatval($threshold['value']), $threshold['option']);
                                } elseif ($threshold['option'] === '=') {
                                        $comparison = ($compare == $threshold['value']);
                                } else {
                                        $comparison = ($compare != $threshold['value']);
                                }

                                if ($comparison) {
                                        $this->NotificationManager->triggerNotification(NotificationManager::SUBJECT_THRESHOLD, $reportId, $threshold['id'], [
                                                'report' => $datasetMetadata['name'],
                                                'subject' => $subject,
                                                'rule' => $threshold['option'],
                                                'value' => $threshold['value']
                                        ], $threshold['user_id']);
                                        $result = 'Threshold value met';
                                }
                        }
                }
                return $result;
        }
}