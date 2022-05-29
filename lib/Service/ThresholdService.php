<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCA\Analytics\Notification\NotificationManager;
use Psr\Log\LoggerInterface;

class ThresholdService
{
    private $logger;
    private $ThresholdMapper;
    private $ReportMapper;
    private $NotificationManager;

    public function __construct(
        LoggerInterface $logger,
        ThresholdMapper $ThresholdMapper,
        NotificationManager $NotificationManager,
        ReportMapper $ReportMapper
    )
    {
        $this->logger = $logger;
        $this->ThresholdMapper = $ThresholdMapper;
        $this->NotificationManager = $NotificationManager;
        $this->ReportMapper = $ReportMapper;
    }

    /**
     * read all thresholds for a dataset
     *
     * @param int $reportId
     * @return array
     */
    public function read(int $reportId)
    {
        return $this->ThresholdMapper->getThresholdsByReport($reportId);
    }

    /**
     * create new threshold for dataset
     *
     * @param int $reportId
     * @param $dimension1
     * @param $option
     * @param $value
     * @param int $severity
     * @return int
     */
    public function create(int $reportId, $dimension1, $option, $value, int $severity)
    {
        $value = $this->floatvalue($value);
        return $this->ThresholdMapper->create($reportId, $dimension1, $value, $option, $severity);
    }

    private function floatvalue($val)
    {
        $val = str_replace(",", ".", $val);
        $val = preg_replace('/\.(?=.*\.)/', '', $val);
        $val = preg_replace('/[^0-9-.]+/', '', $val);
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
    public function delete(int $thresholdId)
    {
        $this->ThresholdMapper->deleteThreshold($thresholdId);
        return true;
    }

    /**
     * validate threshold per report
     *
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return string
     * @throws \Exception
     */
    public function validate(int $reportId, $dimension1, $dimension2, $value)
    {
        $result = '';
        $thresholds = $this->ThresholdMapper->getSevOneThresholdsByReport($reportId);
        $datasetMetadata = $this->ReportMapper->readOwn($reportId);

        foreach ($thresholds as $threshold) {
            if ($threshold['dimension1'] === $dimension1 or $threshold['dimension1'] === '*') {
                if (version_compare($value, $threshold['value'], $threshold['option'])) {
                    $this->NotificationManager->triggerNotification(NotificationManager::SUBJECT_THRESHOLD, $reportId, $threshold['id'], ['report' => $datasetMetadata['name'], 'subject' => $dimension1, 'rule' => $threshold['option'], 'value' => $threshold['value']], $datasetMetadata['user_id']);
                    $result = 'Threshold value met';
                }
            }
        }
        return $result;
    }
}