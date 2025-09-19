<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ThresholdService;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

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
     * @param int $reportId
     * @return array
     */
    #[NoAdminRequired]
    public function read(int $reportId)
    {
        return $this->ThresholdService->readRaw($reportId);
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
	 */
    #[NoAdminRequired]
    public function create(int $reportId, $dimension, $option, $value, int $severity, $coloring)
    {
        return $this->ThresholdService->create($reportId, $dimension, $option, $value, $severity, $coloring);
    }

    /**
     * Delete threshold for dataset
     *
     * @param int $thresholdId
     * @return bool
     */
    #[NoAdminRequired]
    public function delete(int $thresholdId)
    {
        $this->ThresholdService->delete($thresholdId);
        return true;
    }

    /**
     * Update threshold order
     *
     * @param int $reportId
     * @param array $order
     * @return bool
     */
    #[NoAdminRequired]
    public function reorder(int $reportId, $order): bool
    {
        if (is_string($order)) {
            $order = json_decode($order, true);
        }
        $this->ThresholdService->reorder($order);
        return true;
    }

    /**
     * validate threshold
     *
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return string
     * @throws \Exception
     */
    #[NoAdminRequired]
    public function validate(int $reportId, $dimension1, $dimension2, $value)
    {
        return $this->ThresholdService->validate($reportId, $dimension1, $dimension2, $value);
    }
}