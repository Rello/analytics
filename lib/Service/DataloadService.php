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
    private $logger;
    private $StorageService;
    private $DatasourceController;
    private $ActivityManager;
    private $ReportService;
    private $l10n;
    private $DataloadMapper;

    public function __construct(
        IL10N $l10n,
        LoggerInterface $logger,
        ActivityManager $ActivityManager,
        DatasourceController $DatasourceController,
        ReportService $ReportService,
        StorageService $StorageService,
        DataloadMapper $DataloadMapper
    )
    {
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->StorageService = $StorageService;
        $this->ActivityManager = $ActivityManager;
        $this->DatasourceController = $DatasourceController;
        $this->ReportService = $ReportService;
        $this->DataloadMapper = $DataloadMapper;
    }

    // Dataloads
    // Dataloads
    // Dataloads

    /**
     * create a new dataload
     *
     * @param int $datasetId
     * @param int $datasourceId
     * @return int
     */
    public function create(int $datasetId, int $datasourceId)
    {
        return $this->DataloadMapper->create($datasetId, $datasourceId);
    }

    /**
     * get all dataloads for a dataset
     *
     * @param int $datasetId
     * @return array
     */
    public function read(int $datasetId)
    {
        return $this->DataloadMapper->read($datasetId);
    }

    /**
     * update dataload
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
     * delete a dataload
     *
     * @param int $dataloadId
     * @return bool
     */
    public function delete(int $dataloadId)
    {
        return $this->DataloadMapper->delete($dataloadId);
    }

    /**
     * execute all dataloads depending on their schedule
     * daily or hourly
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
     * execute a dataload from datasource and store into dataset
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
                    // if datasource only delivers 2 colums, the value needs to be in the last one
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
        $reportMetadata = $this->ReportService->read($dataloadMetadata['dataset'], $dataloadMetadata['user_id']);

        if (!empty($reportMetadata)) {
            $dataloadMetadata['link'] = $dataloadMetadata['option']; //remap until datasource table is renamed link=>option
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
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return array|false
     * @throws Exception
     */
    public function updateData(int $reportId, $dimension1, $dimension2, $value)
    {
        $reportMetadata = $this->ReportService->read($reportId);
        if (!empty($reportMetadata)) {
            $insert = $update = $errorMessage = 0;
            $action = array();
            $value = $this->floatvalue($value);
            if ($value === false) {
                $errorMessage = $this->l10n->t('3rd field must be a valid number');
            } else {
                $action = $this->StorageService->update((int)$reportMetadata['dataset'], $dimension1, $dimension2, $value);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'error' => $errorMessage,
                'validate' => $action['validate'],
            ];

            if ($errorMessage === 0) $this->ActivityManager->triggerEvent((int)$reportMetadata['dataset'], ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Simulate delete data from input form
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return array|false
     */
    public function deleteDataSimulate(int $reportId, $dimension1, $dimension2)
    {
        $reportMetadata = $this->ReportService->read($reportId);
        if (!empty($reportMetadata)) {
            $result = $this->StorageService->deleteSimulate((int)$reportMetadata['dataset'], $dimension1, $dimension2);
            return ['delete' => $result];
        } else {
            return false;
        }
    }

    /**
     * delete data from input form
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $dimension1
     * @param $dimension2
     * @return array|false
     */
    public function deleteData(int $reportId, $dimension1, $dimension2)
    {
        $reportMetadata = $this->ReportService->read($reportId);
        if (!empty($reportMetadata)) {
            $result = $this->StorageService->delete((int)$reportMetadata['dataset'], $dimension1, $dimension2);
            return ['delete' => $result];
        } else {
            return false;
        }
    }

    /**
     * Import clipboard data
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $import
     * @return array|false
     * @throws Exception
     */
    public function importClipboard($reportId, $import)
    {
        $reportMetadata = $this->ReportService->read($reportId);
        if (!empty($reportMetadata)) {
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
                        $action = $this->StorageService->update((int)$reportMetadata['dataset'], $row[0], $row[1], $row[2]);
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

            if ($errorMessage === 0) $this->ActivityManager->triggerEvent((int)$reportMetadata['dataset'], ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Import data into dataset from an internal or external file
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $path
     * @return array|false
     * @throws Exception
     */
    public function importFile(int $reportId, $path)
    {
        $reportMetadata = $this->ReportService->read($reportId);
        if (!empty($reportMetadata)) {
            $insert = $update = 0;
            $reportMetadata['link'] = $path;
            $result = $this->DatasourceController->read(DatasourceController::DATASET_TYPE_FILE, $reportMetadata);

            if ($result['error'] === 0) {
                foreach ($result['data'] as &$row) {
                    $action = $this->StorageService->update((int)$reportMetadata['dataset'], $row[0], $row[1], $row[2]);
                    $insert = $insert + $action['insert'];
                    $update = $update + $action['update'];
                }
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'error' => $result['error'],
            ];

            if ($result['error'] === 0) $this->ActivityManager->triggerEvent((int)$reportMetadata['dataset'], ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
            return $result;
        } else {
            return false;
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