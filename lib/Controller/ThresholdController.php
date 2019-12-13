<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Notification\NotificationManager;
use OCP\AppFramework\Controller;
use OCP\ILogger;
use OCP\IRequest;

class ThresholdController extends Controller
{
    private $logger;
    private $DBController;
    private $NotificationManager;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        DbController $DBController,
        NotificationManager $NotificationManager
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DBController = $DBController;
        $this->NotificationManager = $NotificationManager;
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
        return $this->DBController->getThresholdsByDataset($datasetId);
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
        return $this->DBController->createThreshold($datasetId, $dimension1, $dimension3, $option, $severity);
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
        $this->DBController->deleteThreshold($thresholdId);
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
        $thresholds = $this->DBController->getSevOneThresholdsByDataset($datasetId);
        $datasetMetadata = $this->DBController->getOwnDataset($datasetId);

        foreach ($thresholds as $threshold) {
            //$this->logger->error('ThresholdController 108: ' . json_encode($threshold));
            if ($threshold['dimension1'] === $dimension1) {
                if (version_compare($dimension3, $threshold['dimension3'], $threshold['option'])) {
                    $this->NotificationManager->triggerNotification(NotificationManager::OBJECT_DATASET, $datasetId, NotificationManager::SUBJECT_THRESHOLD, ['report' => $datasetMetadata['name'], 'subject' => $dimension1, 'rule' => $threshold['option'], 'value' => $threshold['dimension3']]);
                    $result = 'Threshold value met';
                }
            }
        }
        return $result;
    }
}