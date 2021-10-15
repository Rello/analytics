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

use Exception;
use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Service\DataloadService;
use OCA\Analytics\Service\DatasetService;
use OCA\Analytics\Service\StorageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DataloadController extends Controller
{
    private $logger;
    private $StorageService;
    private $DatasourceController;
    private $ActivityManager;
    private $DatasetService;
    private $DataloadService;
    private $l10n;
    private $DataloadMapper;

    public function __construct(
        string $AppName,
        IRequest $request,
        IL10N $l10n,
        LoggerInterface $logger,
        ActivityManager $ActivityManager,
        DatasourceController $DatasourceController,
        DatasetService $DatasetService,
        DataloadService $DataloadService,
        StorageService $StorageService,
        DataloadMapper $DataloadMapper
    )
    {
        parent::__construct($AppName, $request);
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->StorageService = $StorageService;
        $this->ActivityManager = $ActivityManager;
        $this->DatasourceController = $DatasourceController;
        $this->DatasetService = $DatasetService;
        $this->DataloadService = $DataloadService;
        $this->DataloadMapper = $DataloadMapper;
    }

    /**
     * create a new dataload
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param int $datasourceId
     * @return DataResponse
     */
    public function create(int $datasetId, int $datasourceId)
    {
        return new DataResponse(['id' => $this->DataloadService->create($datasetId, $datasourceId)]);
    }

    /**
     * get all dataloads for a dataset
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @return DataResponse
     */
    public function read(int $datasetId)
    {
        $result = array();
        $result['dataloads'] = $this->DataloadService->read($datasetId);
        return new DataResponse($result);
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
    public function update(int $dataloadId, $name, $option, $schedule)
    {
        return new DataResponse(['update' => $this->DataloadService->update($dataloadId, $name, $option, $schedule)]);
    }

    /**
     * delete a dataload
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return bool
     */
    public function delete(int $dataloadId)
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
    public function simulate(int $dataloadId)
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
    public function execute(int $dataloadId)
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
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function updateData(int $reportId, $dimension1, $dimension2, $value)
    {
        $result = $this->DataloadService->updateData($reportId, $dimension1, $dimension2, $value);
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
     * @return DataResponse|NotFoundResponse
     */
    public function deleteData(int $reportId, $dimension1, $dimension2)
    {
        $result = $this->DataloadService->deleteData($reportId, $dimension1, $dimension2);
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
     * @return DataResponse|NotFoundResponse
     */
    public function deleteDataSimulate(int $reportId, $dimension1, $dimension2)
    {
        $result = $this->DataloadService->deleteDataSimulate($reportId, $dimension1, $dimension2);
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
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function importClipboard($reportId, $import)
    {
        $result = $this->DataloadService->importClipboard($reportId, $import);
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
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function importFile(int $reportId, $path)
    {
        $result = $this->DataloadService->importFile($reportId, $path);
        if ($result) {
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }
}