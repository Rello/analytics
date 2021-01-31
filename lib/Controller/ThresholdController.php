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
use OCA\Analytics\Notification\NotificationManager;
use OCA\Analytics\Service\ThresholdService;
use OCP\AppFramework\Controller;
use OCP\ILogger;
use OCP\IRequest;

class ThresholdController extends Controller
{
    private $logger;
    private $ThresholdService;
    private $DatasetMapper;
    private $NotificationManager;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        ThresholdService $ThresholdService,
        NotificationManager $NotificationManager,
        DatasetMapper $DatasetMapper
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ThresholdService = $ThresholdService;
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
        return $this->ThresholdService->read($datasetId);
    }

    /**
     * create new threshold for dataset
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $option
     * @param $value
     * @param int $severity
     * @return int
     */
    public function create(int $datasetId, $dimension1, $option, $value, int $severity)
    {
        return $this->ThresholdService->create($datasetId, $dimension1, $value, $option, $severity);
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
        $this->ThresholdService->delete($thresholdId);
        return true;
    }

    /**
     * validate threshold
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return string
     * @throws \Exception
     */
    public function validate(int $datasetId, $dimension1, $dimension2, $value)
    {
        return $this->ThresholdService->validate($datasetId, $dimension1, $dimension2, $value);
    }
}