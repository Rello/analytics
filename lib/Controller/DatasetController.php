<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Controller;

use OCA\data\Service\DatasetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;


class DatasetController extends Controller
{
    private $logger;
    private $DatasetService;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        DatasetService $DatasetService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DatasetService = $DatasetService;
    }

    /**
     * get all datasets
     * @NoAdminRequired
     */
    public function index()
    {
        return new DataResponse($this->DatasetService->index());
    }

    /**
     * get dataset details
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     */
    public function read(int $datasetId)
    {
        return new DataResponse($this->DatasetService->read($datasetId));
    }

    /**
     * create new datasets
     * @NoAdminRequired
     */
    public function create()
    {
        return new DataResponse($this->DatasetService->create());
    }

    /**
     * get dataset details
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     */
    public function delete(int $datasetId)
    {
        return new DataResponse($this->DatasetService->delete($datasetId));
    }

    /**
     * get dataset details
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     */
    public function update(int $datasetId, $name, $type, $link, $visualization, $chart)
    {
        return new DataResponse($this->DatasetService->update($datasetId, $name, $type, $link, $visualization, $chart));
    }

}