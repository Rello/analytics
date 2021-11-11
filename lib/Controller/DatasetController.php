<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\ReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DatasetController extends Controller
{
    private $logger;
    private $DatasetService;
    private $ReportService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        DatasetService $DatasetService,
        ReportService $ReportService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DatasetService = $DatasetService;
        $this->ReportService = $ReportService;
    }

    /**
     * get all datasets
     *
     * @NoAdminRequired
     * @return DataResponse
     */
    public function index()
    {
        return $this->DatasetService->index();
    }

    /**
     * create new dataset
     *
     * @NoAdminRequired
     * @param $name
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return int
     * @throws \OCP\DB\Exception
     */
    public function create($name, $dimension1, $dimension2, $value)
    {
        return $this->DatasetService->create($name, $dimension1, $dimension2, $value);
    }

    /**
     * get own dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return array
     */
    public function read(int $datasetId)
    {
        return $this->DatasetService->read($datasetId);
    }

    /**
     * Delete Dataset and all depending objects
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function delete(int $datasetId)
    {
        $own = $this->read($datasetId);
        if ($own) {
            $reports = $this->ReportService->reportsForDataset($datasetId);
            foreach ($reports as $report) {
                $this->logger->error('report id: '.$report['id']);
                $this->ReportService->delete($report['id']);
            }
            $this->DatasetService->delete($datasetId);
        }

        return true;
    }

    /**
     * get dataset details
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $name
     * @param null $dimension1
     * @param null $dimension2
     * @param null $value
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function update(int $datasetId, $name, $dimension1 = null, $dimension2 = null, $value = null)
    {
        return $this->DatasetService->update($datasetId, $name, $dimension1, $dimension2, $value);
    }

    /**
     * get status of the dataset
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @throws \OCP\DB\Exception
     */
    public function status(int $datasetId)
    {
        return $this->DatasetService->status($datasetId);
    }

}