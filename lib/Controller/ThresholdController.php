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

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ThresholdService;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ThresholdController extends Controller
{
    private $logger;
    private $ThresholdService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        ThresholdService $ThresholdService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ThresholdService = $ThresholdService;
    }

    /**
     * read all thresholds for a dataset
     *
     * @NoAdminRequired
     * @param int $reportId
     * @return array
     */
    public function read(int $reportId)
    {
        return $this->ThresholdService->readRaw($reportId);
    }

    /**
     * create new threshold for dataset
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $dimension1
     * @param $option
     * @param $value
     * @param int $severity
     * @return int
     */
    public function create(int $reportId, $dimension1, $option, $value, int $severity)
    {
        return $this->ThresholdService->create($reportId, $dimension1, $option, $value, $severity);
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
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return string
     * @throws \Exception
     */
    public function validate(int $reportId, $dimension1, $dimension2, $value)
    {
        return $this->ThresholdService->validate($reportId, $dimension1, $dimension2, $value);
    }
}