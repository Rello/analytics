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
    private $StorageController;
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
        StorageController $StorageController,
        DataloadMapper $DataloadMapper
    )
    {
        parent::__construct($AppName, $request);
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->StorageController = $StorageController;
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
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function updateData(int $datasetId, $dimension1, $dimension2, $value)
    {
        $datasetMetadata = $this->DatasetService->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = $errorMessage = 0;
            $action = array();
            $value = $this->floatvalue($value);
            if ($value === false) {
                $errorMessage = $this->l10n->t('3rd field must be a valid number');
            } else {
                $action = $this->StorageController->update($datasetId, $dimension1, $dimension2, $value);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'error' => $errorMessage,
                'validate' => $action['validate'],
            ];

            if ($errorMessage === 0) $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD);
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * delete data from input form
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return DataResponse|NotFoundResponse
     */
    public function deleteData(int $datasetId, $dimension1, $dimension2)
    {
        $datasetMetadata = $this->DatasetService->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $result = $this->StorageController->delete($datasetId, $dimension1, $dimension2);
            return new DataResponse(['delete' => $result]);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Simulate delete data from input form
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return DataResponse|NotFoundResponse
     */
    public function deleteDataSimulate(int $datasetId, $dimension1, $dimension2)
    {
        $datasetMetadata = $this->DatasetService->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $result = $this->StorageController->deleteSimulate($datasetId, $dimension1, $dimension2);
            return new DataResponse(['delete' => $result]);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Import clipboard data
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $import
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function importClipboard($datasetId, $import)
    {
        $datasetMetadata = $this->DatasetService->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = $errorMessage = $errorCounter = 0;
            $delimiter = '';

            if ($import === '') {
                $errorMessage = $this->l10n->t('No data');
            } else {
                $delimiter = $this->detectDelimiter($import);
                $rows = str_getcsv($import, "\n");

                foreach ($rows as &$row) {
                    $row = str_getcsv($row, $delimiter);
                    $numberOfColumns = count($row);
                    // last column needs to be a float
                    $row[2] = $this->floatvalue($row[$numberOfColumns - 1]);
                    if ($row[2] === false) {
                        $errorCounter++;
                    } else {
                        if ($numberOfColumns < 3) $row[1] = null;
                        $action = $this->StorageController->update($datasetId, $row[0], $row[1], $row[2]);
                        $insert = $insert + $action['insert'];
                        $update = $update + $action['update'];
                    }
                    if ($errorCounter === 2) {
                        // first error is ignored; might be due to header row
                        $errorMessage = $this->l10n->t('Last field must be a valid number');
                        break;
                    }
                }
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'delimiter' => $delimiter,
                'error' => $errorMessage,
            ];

            if ($errorMessage === 0) $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Import data into dataset from an internal or external file
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $path
     * @return DataResponse|NotFoundResponse
     * @throws NotFoundException
     * @throws Exception
     */
    public function importFile(int $datasetId, $path)
    {
        $datasetMetadata = $this->DatasetService->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = 0;
            $datasetMetadata['link'] = $path;
            $result = $this->DatasourceController->read(DatasourceController::DATASET_TYPE_FILE, $datasetMetadata);

            if ($result['error'] === 0) {
                foreach ($result['data'] as &$row) {
                    $action = $this->StorageController->update($datasetId, $row[0], $row[1], $row[2]);
                    $insert = $insert + $action['insert'];
                    $update = $update + $action['update'];
                }
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'error' => $result['error'],
            ];

            if ($result['error'] === 0) $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    private function detectDelimiter($data): string
    {
        $delimiters = ["\t", ";", "|", ","];
        $data_2 = null;
        $delimiter = $delimiters[0];
        foreach ($delimiters as $d) {
            $firstRow = str_getcsv($data, "\n")[0];
            $data_1 = str_getcsv($firstRow, $d);
            if (sizeof($data_1) > sizeof($data_2)) {
                $delimiter = $d;
                $data_2 = $data_1;
            }
        }
        return $delimiter;
    }

    private function floatvalue($val)
    {
        $val = str_replace(",", ".", $val);
        $val = preg_replace('/\.(?=.*\.)/', '', $val);
        $val = preg_replace('/[^0-9-.]+/', '', $val);
        if (is_numeric($val)) {
            return number_format(floatval($val), 2, '.', '');
        } else {
            return false;
        }
    }
}