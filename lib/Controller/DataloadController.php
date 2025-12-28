<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

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
     * @param $datasetId
     * @param int $datasourceId
     * @return DataResponse
     * @throws \OCP\DB\Exception
     */
    #[NoAdminRequired]
    public function create($datasetId, int $datasourceId): DataResponse
    {
        return new DataResponse(['id' => $this->DataloadService->create($datasetId, $datasourceId)]);
    }

    /**
     * get all data loads for a dataset or report
     *
     * @param $datasetId
     * @param $reportId
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function read($datasetId): DataResponse
    {
        return new DataResponse(['dataloads' => $this->DataloadService->read($datasetId)]);
    }

    /**
     * update dataload
     *
     * @param int $dataloadId
     * @param $name
     * @param $option
     * @param $schedule
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function update(int $dataloadId, $name, $option, $schedule): DataResponse
    {
        return new DataResponse(['update' => $this->DataloadService->update($dataloadId, $name, $option, $schedule)]);
    }

    /**
     * copy a dataload
     *
     * @param int $dataloadId
     * @return DataResponse
     * @throws NotFoundException
     */
    #[NoAdminRequired]
    public function copy(int $dataloadId): DataResponse
    {
        return new DataResponse($this->DataloadService->copy($dataloadId));
    }

    /**
     * delete a dataload
     *
     * @param int $dataloadId
     * @return bool
     */
    #[NoAdminRequired]
    public function delete(int $dataloadId): bool
    {
        return $this->DataloadService->delete($dataloadId);
    }

    /**
     * simulate a dataload and output its data
     *
     * @param int $dataloadId
     * @return DataResponse
     * @throws NotFoundException
     */
    #[NoAdminRequired]
    public function simulate(int $dataloadId): DataResponse
    {
        return new DataResponse($this->DataloadService->getDataFromDatasource($dataloadId));
    }

    /**
     * execute a dataload from data source and store into dataset
     *
     * @param int $dataloadId
     * @return DataResponse
     * @throws Exception
     */
    #[NoAdminRequired]
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
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    #[NoAdminRequired]
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
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     */
    #[NoAdminRequired]
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
     * @param int $objectId
     * @param $dimension1
     * @param $dimension2
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     */
    #[NoAdminRequired]
    public function deleteDataSimulate(int $objectId, $dimension1, $dimension2, bool $isDataset)
    {
        $result = $this->DataloadService->deleteDataSimulate($objectId, $dimension1, $dimension2, $isDataset);
        if ($result) {
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Import clipboard data
     *
     * @param int $reportId
     * @param $import
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    #[NoAdminRequired]
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
     * @param int $reportId
     * @param $path
     * @param bool $isDataset
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    #[NoAdminRequired]
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