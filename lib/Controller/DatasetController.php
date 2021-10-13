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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DatasetController extends Controller
{
    private $logger;
    private $DatasetService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        DatasetService $DatasetService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DatasetService = $DatasetService;
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
     * @param string $file
     * @return int
     */
    public function create($file = '')
    {
        return $this->DatasetService->create($file);
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
     */
    public function delete(int $datasetId)
    {
        return $this->DatasetService->delete($datasetId);
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

}