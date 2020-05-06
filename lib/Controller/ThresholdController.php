<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCA\Analytics\Notification\NotificationManager;
use OCP\AppFramework\Controller;
use OCP\ILogger;
use OCP\IRequest;

class ThresholdController extends Controller
{
    private $logger;
    private $ThresholdMapper;
    private $DatasetMapper;
    private $NotificationManager;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        ThresholdMapper $ThresholdMapper,
        NotificationManager $NotificationManager,
        DatasetMapper $DatasetMapper
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ThresholdMapper = $ThresholdMapper;
        $this->NotificationManager = $NotificationManager;
        $this->DatasetMapper = $DatasetMapper;
    }

    /**
     * read all thresholds for a dataset
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return array
     */
    public function read(int $datasetId)
    {
        return $this->ThresholdMapper->getThresholdsByDataset($datasetId);
    }

    /**
     * create new threshold for dataset
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $option
     * @param $dimension3
     * @param int $severity
     * @return int
     */
    public function create(int $datasetId, $dimension1, $option, $dimension3, int $severity)
    {
        $dimension3 = $this->floatvalue($dimension3);
        return $this->ThresholdMapper->createThreshold($datasetId, $dimension1, $dimension3, $option, $severity);
    }

    /**
     * Delete threshold for dataset
     *
     * @NoAdminRequired
     * @param int $thresholdId
     * @return bool
     */
    public function delete(int $thresholdId)
    {
        $this->ThresholdMapper->deleteThreshold($thresholdId);
        return true;
    }

    /**
     * validate threshold
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return string
     * @throws \Exception
     */
    public function validate(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        $result = '';
        $thresholds = $this->ThresholdMapper->getSevOneThresholdsByDataset($datasetId);
        $datasetMetadata = $this->DatasetMapper->getDatasetOptions($datasetId);

        foreach ($thresholds as $threshold) {
            //$this->logger->error('ThresholdController 104: ' . $threshold['dimension3'].'==='.$threshold['option'].'==='.$dimension3);
            if ($threshold['dimension1'] === $dimension1 or $threshold['dimension1'] === '*') {
                if (version_compare($dimension3, $threshold['dimension3'], $threshold['option'])) {
                    $this->NotificationManager->triggerNotification(NotificationManager::SUBJECT_THRESHOLD, $datasetId, $threshold['id'], ['report' => $datasetMetadata['name'], 'subject' => $dimension1, 'rule' => $threshold['option'], 'value' => $threshold['dimension3']], $datasetMetadata['user_id']);
                    $result = 'Threshold value met';
                }
            }
        }
        return $result;
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
}