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

use OCA\Analytics\Service\DataService;
use OCA\Analytics\Service\DatasetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ILogger;
use OCP\IRequest;


class DatasetController extends Controller
{
    private $logger;
    private $DatasetService;
    private $ShareController;
    private $DataService;

    const DATASET_TYPE_GROUP = 0;
    const DATASET_TYPE_INTERNAL_FILE = 1;
    const DATASET_TYPE_INTERNAL_DB = 2;
    const DATASET_TYPE_GIT = 3;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        ShareController $ShareController,
        DataService $DataService,
        DatasetService $DatasetService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DatasetService = $DatasetService;
        $this->DataService = $DataService;
        $this->ShareController = $ShareController;
    }

    /**
     * get all datasets
     * @NoAdminRequired
     */
    public function index()
    {
        $ownDatasets = $this->DatasetService->index();
        $sharedDatasets = $this->ShareController->getSharedDatasets();
        $ownDatasets = array_merge($ownDatasets, $sharedDatasets);
        return new JSONResponse($ownDatasets);
    }

    /**
     * get own dataset details
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     */
    public function read(int $datasetId)
    {
        return new DataResponse($this->DatasetService->getOwnDataset($datasetId));
    }

    /**
     * create new datasets
     * @NoAdminRequired
     * @return bool
     */
    public function create()
    {
        return $this->DatasetService->create();
    }

    /**
     * get dataset details
     * @NoAdminRequired
     * @param int $datasetId
     * @return bool
     */
    public function delete(int $datasetId)
    {
        $this->DataService->deleteDataByDataset($datasetId);
        $this->ShareController->deleteShareByDataset($datasetId);
        $this->DatasetService->delete($datasetId);
        return true;
    }

    /**
     * get dataset details
     * @NoAdminRequired
     * @param int $datasetId
     * @param $name
     * @param int $parent
     * @param int $type
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return bool
     */
    public function update(int $datasetId, $name, int $parent, int $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3)
    {
        return $this->DatasetService->update($datasetId, $name, $parent, $type, $link, $visualization, $chart, $dimension1, $dimension2, $dimension3);
    }

}