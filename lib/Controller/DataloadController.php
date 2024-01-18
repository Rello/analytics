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

use Exception;
use OCA\Analytics\Service\DataloadService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DataloadController extends Controller
{
    private $logger;
    private $DataloadService;

    public function __construct(
        string $appName,
        IRequest $request,
        LoggerInterface $logger,
        DataloadService $DataloadService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DataloadService = $DataloadService;
    }

    /**
     * create a new dataload
     *
     * @NoAdminRequired
     * @param $datasetId
     * @param int $datasourceId
     * @return DataResponse
     * @throws \OCP\DB\Exception
     */
    public function create($datasetId, int $datasourceId): DataResponse
    {
        return new DataResponse(['id' => $this->DataloadService->create($datasetId, $datasourceId)]);
    }

    /**
     * get all data loads for a dataset or report
     *
     * @NoAdminRequired
     * @param $datasetId
     * @param $reportId
     * @return DataResponse
     */
    public function read($datasetId): DataResponse
    {
        return new DataResponse(['dataloads' => $this->DataloadService->read($datasetId)]);
    }

    /**
     * update dataload
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @param $name
     * @param $option
     * @param $schedule
     * @return DataResponse
     */
    public function update(int $dataloadId, $name, $option, $schedule): DataResponse
    {
        return new DataResponse(['update' => $this->DataloadService->update($dataloadId, $name, $option, $schedule)]);
    }

    /**
     * copy a dataload
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return DataResponse
     * @throws NotFoundException
     */
    public function copy(int $dataloadId): DataResponse
    {
        return new DataResponse($this->DataloadService->copy($dataloadId));
    }

    /**
     * delete a dataload
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return bool
     */
    public function delete(int $dataloadId): bool
    {
        return $this->DataloadService->delete($dataloadId);
    }

    /**
     * simulate a dataload and output its data
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return DataResponse
     * @throws NotFoundException
     */
    public function simulate(int $dataloadId): DataResponse
    {
        return new DataResponse($this->DataloadService->getDataFromDatasource($dataloadId));
    }

    /**
     * execute a dataload from datasource and store into dataset
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return DataResponse
     * @throws Exception
     */
    public function execute(int $dataloadId): DataResponse
    {
        return new DataResponse($this->DataloadService->execute($dataloadId));
    }


    // Data Manipulation
    // Data Manipulation
    // Data Manipulation

    /**
     * update data from input form
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function updateData(int $reportId, $dimension1, $dimension2, $value, bool $isDataset)
    {
        $result = $this->DataloadService->updateData($reportId, $dimension1, $dimension2, $value, $isDataset);
        if ($result) {
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * delete data from input form
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     */
    public function deleteData(int $reportId, $dimension1, $dimension2, bool $isDataset)
    {
        $result = $this->DataloadService->deleteData($reportId, $dimension1, $dimension2, $isDataset);
        if ($result) {
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Simulate delete data from input form
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     */
    public function deleteDataSimulate(int $reportId, $dimension1, $dimension2, bool $isDataset)
    {
        $result = $this->DataloadService->deleteDataSimulate($reportId, $dimension1, $dimension2, $isDataset);
        if ($result) {
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Import clipboard data
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $import
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function importClipboard(int $reportId, $import, bool $isDataset)
    {
        $result = $this->DataloadService->importClipboard($reportId, $import, $isDataset);
        if ($result) {
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Import data into dataset from an internal or external file
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $path
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function importFile(int $reportId, $path, bool $isDataset)
    {
        $result = $this->DataloadService->importFile($reportId, $path, $isDataset);
        if ($result) {
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }
}