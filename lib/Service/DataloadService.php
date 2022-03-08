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

namespace OCA\Analytics\Service;

use Exception;
use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Db\DataloadMapper;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class DataloadService
{
    private $userId;
    private $logger;
    private $StorageService;
    private $DatasourceController;
    private $ActivityManager;
    private $ReportService;
    private $l10n;
    private $DataloadMapper;

    public function __construct(
        $userId,
        IL10N $l10n,
        LoggerInterface $logger,
        ActivityManager $ActivityManager,
        DatasourceController $DatasourceController,
        ReportService $ReportService,
        StorageService $StorageService,
        DataloadMapper $DataloadMapper
    )
    {
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->StorageService = $StorageService;
        $this->ActivityManager = $ActivityManager;
        $this->DatasourceController = $DatasourceController;
        $this->ReportService = $ReportService;
        $this->DataloadMapper = $DataloadMapper;
    }

    // Data loads
    // Data loads
    // Data loads

    /**
     * create a new data load
     *
     * @param $datasetId
     * @param $reportId
     * @param int $datasourceId
     * @return int
     * @throws \OCP\DB\Exception
     */
    public function create($datasetId, int $datasourceId)
    {
        return $this->DataloadMapper->create((int)$datasetId, $datasourceId);
    }

    /**
     * get all data loads for a dataset or report
     *
     * @param int $datasetId
     * @param $reportId
     * @return array
     */
    public function read($datasetId)
    {
        return $this->DataloadMapper->read((int)$datasetId);
    }

    /**
     * update data load
     *
     * @param int $dataloadId
     * @param $name
     * @param $option
     * @param $schedule
     * @return bool
     */
    public function update(int $dataloadId, $name, $option, $schedule)
    {
        return $this->DataloadMapper->update($dataloadId, $name, $option, $schedule);
    }

    /**
     * delete a data load
     *
     * @param int $dataloadId
     * @return bool
     */
    public function delete(int $dataloadId)
    {
        return $this->DataloadMapper->delete($dataloadId);
    }

    /**
     * execute all data loads depending on their schedule
     * Daily or Hourly
     *
     * @param $schedule
     * @return void
     * @throws Exception
     */
    public function executeBySchedule($schedule)
    {
        $schedules = $this->DataloadMapper->getDataloadBySchedule($schedule);
        foreach ($schedules as $dataload) {
            $this->execute($dataload['id']);
        }
    }

    /**
     * execute a data load from datas ource and store into dataset
     *
     * @param int $dataloadId
     * @return array
     * @throws Exception
     */
    public function execute(int $dataloadId)
    {
        $bulkSize = 500;
        $insert = $update = $error = 0;
        $bulkInsert = null;

        $dataloadMetadata = $this->DataloadMapper->getDataloadById($dataloadId);
        $option = json_decode($dataloadMetadata['option'], true);
        $result = $this->getDataFromDatasource($dataloadId);
        $datasetId = $result['datasetId'];

        if (isset($option['delete']) and $option['delete'] === 'true') {
            $bulkInsert = $this->StorageService->delete($datasetId, '*', '*', $dataloadMetadata['user_id']);
        }

        $this->DataloadMapper->beginTransaction();

        if ($result['error'] === 0) {
            $currentCount = 0;
            foreach ($result['data'] as $row) {
                if (count($row) === 2) {
                    // if data source only delivers 2 colums, the value needs to be in the last one
                    $row[2] = $row[1];
                    $row[1] = null;
                }
                $action = $this->StorageService->update($datasetId, $row[0], $row[1], $row[2], $dataloadMetadata['user_id'], $bulkInsert);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
                $error = $error + $action['error'];

                if ($currentCount % $bulkSize === 0) {
                    $this->DataloadMapper->commit();
                    $this->DataloadMapper->beginTransaction();
                }
                if ($action['error'] === 0) $currentCount++;
            }
        }
        $this->DataloadMapper->commit();

        $result = [
            'insert' => $insert,
            'update' => $update,
            'error' => $error,
        ];

        if ($error === 0) $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_DATALOAD, $dataloadMetadata['user_id']);
        return $result;
    }

    /**
     * get the data from datasource
     * to be used in simulation or execution
     *
     * @param int $dataloadId
     * @return array|NotFoundResponse
     * @throws NotFoundResponse|NotFoundException
     */
    public function getDataFromDatasource(int $dataloadId)
    {
        $dataloadMetadata = $this->DataloadMapper->getDataloadById($dataloadId);

        if (!empty($dataloadMetadata)) {
            $dataloadMetadata['link'] = $dataloadMetadata['option']; //remap until data source table is renamed link=>option
            $result = $this->DatasourceController->read((int)$dataloadMetadata['datasource'], $dataloadMetadata);
            $result['datasetId'] = $dataloadMetadata['dataset'];
            return $result;
        } else {
            return new NotFoundResponse();
        }
    }

    // Data Manipulation
    // Data Manipulation
    // Data Manipulation

    /**
     * update data from input form
     *
     * @NoAdminRequired
     * @param int $objectId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @param bool $isDataset
     * @return array|false
     * @throws \OCP\DB\Exception
     */
    public function updateData(int $objectId, $dimension1, $dimension2, $value, bool $isDataset)
    {
        $dataset = $this->getDatasetId($objectId, $isDataset);

        if ($dataset != '') {
            $insert = $update = $errorMessage = 0;
            $action = array();
            $value = $this->floatvalue($value);
            if ($value === false) {
                $errorMessage = $this->l10n->t('3rd field must be a valid number');
            } else {
                $action = $this->StorageService->update($dataset, $dimension1, $dimension2, $value);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'error' => $errorMessage,
                'validate' => $action['validate'],
            ];

            if ($errorMessage === 0) $this->ActivityManager->triggerEvent($dataset, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Simulate delete data from input form
     *
     * @NoAdminRequired
     * @param int $objectId
     * @param $dimension1
     * @param $dimension2
     * @param bool $isDataset
     * @return array|false
     * @throws \OCP\DB\Exception
     */
    public function deleteDataSimulate(int $objectId, $dimension1, $dimension2, bool $isDataset)
    {
        $dataset = $this->getDatasetId($objectId, $isDataset);
        if ($dataset != '') {
            $result = $this->StorageService->deleteSimulate($dataset, $dimension1, $dimension2);
            return ['delete' => $result];
        } else {
            return false;
        }
    }

    /**
     * delete data from input form
     *
     * @NoAdminRequired
     * @param int $objectId
     * @param $dimension1
     * @param $dimension2
     * @param bool $isDataset
     * @return array|false
     * @throws \OCP\DB\Exception
     */
    public function deleteData(int $objectId, $dimension1, $dimension2, bool $isDataset)
    {
        $dataset = $this->getDatasetId($objectId, $isDataset);
        if ($dataset != '') {
            $result = $this->StorageService->delete($dataset, $dimension1, $dimension2);
            return ['delete' => $result];
        } else {
            return false;
        }
    }

    /**
     * Import clipboard data
     *
     * @NoAdminRequired
     * @param int $objectId
     * @param $import
     * @param bool $isDataset
     * @return array|false
     * @throws \OCP\DB\Exception
     */
    public function importClipboard($objectId, $import, bool $isDataset)
    {
        $dataset = $this->getDatasetId($objectId, $isDataset);
        if ($dataset != '') {
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
                        $action = $this->StorageService->update($dataset, $row[0], $row[1], $row[2]);
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

            if ($errorMessage === 0) $this->ActivityManager->triggerEvent($dataset, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Import data into dataset from an internal or external file
     *
     * @NoAdminRequired
     * @param int $objectId
     * @param $path
     * @param bool $isDataset
     * @return array|false
     * @throws \OCP\DB\Exception
     */
    public function importFile(int $objectId, $path, bool $isDataset)
    {
        $dataset = $this->getDatasetId($objectId, $isDataset);
        if ($dataset != '') {
            $insert = $update = 0;
            $reportMetadata['link'] = $path;
            $reportMetadata['user_id'] = $this->userId;
            $result = $this->DatasourceController->read(DatasourceController::DATASET_TYPE_FILE, $reportMetadata);

            if ($result['error'] === 0) {
                foreach ($result['data'] as &$row) {
                    $action = $this->StorageService->update($dataset, $row[0], $row[1], $row[2]);
                    $insert = $insert + $action['insert'];
                    $update = $update + $action['update'];
                }
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'error' => $result['error'],
            ];

            if ($result['error'] === 0) $this->ActivityManager->triggerEvent($dataset, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
            return $result;
        } else {
            return false;
        }
    }

    private function getDatasetId($objectId, bool $isDataset)
    {
        if ($isDataset) {
            $dataset = $objectId;
        } else {
            $reportMetadata = $this->ReportService->read($objectId);
            $dataset = (int)$reportMetadata['dataset'];
        }
        return $dataset;
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