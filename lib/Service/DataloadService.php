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

namespace OCA\Analytics\Service;

use Exception;
use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Notification\NotificationManager;
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
    private $DatasetService;
    private $VariableService;
    private $l10n;
    private $DataloadMapper;
    private $NotificationManager;

    public function __construct(
        $userId,
        IL10N $l10n,
        LoggerInterface $logger,
        ActivityManager $ActivityManager,
        DatasourceController $DatasourceController,
        ReportService $ReportService,
        DatasetService $DatasetService,
        StorageService $StorageService,
        VariableService $VariableService,
        NotificationManager $NotificationManager,
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
        $this->DatasetService = $DatasetService;
        $this->VariableService = $VariableService;
        $this->NotificationManager = $NotificationManager;
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
        $array = json_decode($option, true);
        foreach ($array as $key => $value) {
            $array[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
        }
        $option = json_encode($array);

        return $this->DataloadMapper->update($dataloadId, $name, $option, $schedule);
    }

    /**
     * copy a data load
     *
     * @param int $dataloadId
     * @return bool
     */
    public function copy(int $dataloadId)
    {
        return $this->DataloadMapper->copy($dataloadId);
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
            $result = $this->execute($dataload['id']);
            if ($result['error'] !== 0) {
                // if the data source produced an error, a notification needs to be triggered
                $dataset = $this->DatasetService->read($dataload['dataset']);
                $this->NotificationManager->triggerNotification(NotificationManager::DATALOAD_ERROR, $dataload['dataset'], $dataload['id'], ['dataloadName' => $dataload['name'], 'datasetName' => $dataset['name']], $dataload['user_id']);
            }
        }
    }

    /**
     * execute a data load from data source and store into dataset
     *
     * @param int $dataloadId
     * @return array
     * @throws Exception
     */
    public function execute(int $dataloadId)
    {
        $bulkSize = 500;
        $insert = $update = $error = $delete = $currentCount = 0;
        $bulkInsert = null;
        $aggregation = null;

        // get the data from the datasource
        $result = $this->getDataFromDatasource($dataloadId);

        // dont continue in case of datasource error
        if ($result['error'] !== 0) {
            return [
                'insert' => $insert,
                'update' => $update,
                'delete' => $delete,
                'error' => 1,
            ];
        }

        // get the meta data
        $dataloadMetadata = $this->DataloadMapper->getDataloadById($dataloadId);
        $option = json_decode($dataloadMetadata['option'], true);
        $datasetId = $dataloadMetadata['dataset'];

        // this is a deletion request. Just run the deletion and stop after that with a return.
        if ($dataloadMetadata['datasource'] === 0) {
            // deletion jobs are using the same dimension/option/value settings a report filter
            $filter = array();
            $filter['filteroptions'] = '{"filter":{"' . $option['filterDimension'] . '":{"option":"' . $option['filterOption'] . '","value":"' . $option['filterValue'] . '"}}}';
            // Text variables like %xx% could be in use here
            $filter = $this->VariableService->replaceFilterVariables($filter);

            $records = $this->StorageService->deleteWithFilter($dataloadMetadata['dataset'], json_decode($filter['filteroptions'], true));

            return [
                'insert' => $insert,
                'update' => $update,
                'delete' => $records,
                'error' => $error,
            ];
        }

        // "delete all date before loading" is true in the data source options
        // in this case, bulkInsert is additionally set to true. Then no further checks for existing records are needed
        // to reduce db selects
        if (isset($option['delete']) and $option['delete'] === 'true') {
            $this->StorageService->delete($datasetId, '*', '*', $dataloadMetadata['user_id']);
            $bulkInsert = true;
        }

        // if the data set has no data, it is the same as the delete all option
        // in this case, bulkInsert is set to true. Then no further checks for existing records are needed
        // to reduce db selects
        $numberOfRecords = $this->StorageService->getRecordCount($datasetId, $dataloadMetadata['user_id']);
        if ($numberOfRecords['count'] === 0) {
            $bulkInsert = true;
        }

        // Feature not yet available
        if (isset($option['aggregation']) and $option['aggregation'] !== 'overwrite') {
            $aggregation = $option['aggregation'];
        }

        // collect mass updates to reduce statements to the database
        $this->DataloadMapper->beginTransaction();
        foreach ($result['data'] as $row) {
            // only one column is not possible
            if (count($row) === 1) {
                $this->logger->info('loading data with only one column is not possible. This is a data load for the dataset: ' . $datasetId);
                $error = $error + 1;
                continue;
            }
            // if data source only delivers 2 columns, the value needs to be in the last one
            if (count($row) === 2) {
                $row[2] = $row[1];
                $row[1] = null;
            }

            $action = $this->StorageService->update($datasetId, $row[0], $row[1], $row[2], $dataloadMetadata['user_id'], $bulkInsert, $aggregation);
            $insert = $insert + $action['insert'];
            $update = $update + $action['update'];
            $error = $error + $action['error'];

            if ($currentCount % $bulkSize === 0) {
                $this->DataloadMapper->commit();
                $this->DataloadMapper->beginTransaction();
            }
            if ($action['error'] === 0) $currentCount++;
        }
        $this->DataloadMapper->commit();

        $result = [
            'insert' => $insert,
            'update' => $update,
            'delete' => $delete,
            'error' => $error,
        ];

        $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_DATALOAD, $dataloadMetadata['user_id']);
        return $result;
    }

    /**
     * get the data from datasource
     * to be used in simulation or execution
     *
     * @param int $dataloadId
     * @return array|NotFoundResponse
     * @throws NotFoundResponse
     * @throws \OCP\DB\Exception
     */
    public function getDataFromDatasource(int $dataloadId)
    {
        $dataloadMetadata = $this->DataloadMapper->getDataloadById($dataloadId);

        if (!empty($dataloadMetadata)) {

            if ($dataloadMetadata['datasource'] !== 0) {
                $dataloadMetadata['link'] = $dataloadMetadata['option']; //remap until data source table is renamed link=>option

                $result = $this->DatasourceController->read((int)$dataloadMetadata['datasource'], $dataloadMetadata);
                $result['datasetId'] = $dataloadMetadata['dataset'];
            } else {
                // this is a deletion request. Just run the simulation and return the possible row count in the expected result array
                $option = json_decode($dataloadMetadata['option'], true);

                // deletion jobs are using the same dimension/option/value settings a report filter
                $filter = array();
                $filter['filteroptions'] = '{"filter":{"' . $option['filterDimension'] . '":{"option":"' . $option['filterOption'] . '","value":"' . $option['filterValue'] . '"}}}';
                // Text variables like %xx% could be in use here
                $filter = $this->VariableService->replaceFilterVariables($filter);

                $result = [
                    'header' => '',
                    'dimensions' => '',
                    'data' => $this->StorageService->deleteWithFilterSimulate($dataloadMetadata['dataset'], json_decode($filter['filteroptions'], true)),
                    'error' => 0,
                ];
            }

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
            $reportMetadata = array();
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
        $data_2 = array();
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
        // if value is a 3 digit comma number with one leading zero like 0,111, it should not go through the 1000 separator removal
        if (preg_match('/(?<=\b0)\,(?=\d{3}\b)/', $val) === 0 && preg_match('/(?<=\b0)\.(?=\d{3}\b)/', $val) === 0) {
            // remove , as 1000 separator
            $val = preg_replace('/(?<=\d)\,(?=\d{3}\b)/', '', $val);
            // remove . as 1000 separator
            $val = preg_replace('/(?<=\d)\.(?=\d{3}\b)/', '', $val);
        }
        // convert remaining comma to decimal point
        $val = str_replace(",", ".", $val);
        if (is_numeric($val)) {
            return number_format(floatval($val), 2, '.', '');
        } else {
            return false;
        }
    }

}