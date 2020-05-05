<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use Exception;
use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\DataloadMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

class DataloadController extends Controller
{
    private $logger;
    private $StorageController;
    private $DataSourceController;
    private $userId;
    private $ActivityManager;
    private $DatasetController;
    private $l10n;
    private $DataloadMapper;

    public function __construct(
        string $AppName,
        IRequest $request,
        IL10N $l10n,
        $userId,
        ILogger $logger,
        ActivityManager $ActivityManager,
        DataSourceController $DataSourceController,
        DatasetController $DatasetController,
        StorageController $StorageController,
        DataloadMapper $DataloadMapper
    )
    {
        parent::__construct($AppName, $request);
        $this->l10n = $l10n;
        $this->userId = $userId;
        $this->logger = $logger;
        $this->StorageController = $StorageController;
        $this->ActivityManager = $ActivityManager;
        $this->DataSourceController = $DataSourceController;
        $this->DatasetController = $DatasetController;
        $this->DataloadMapper = $DataloadMapper;
    }

    // Dataloads
    // Dataloads
    // Dataloads

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
        return new DataResponse(['id' => $this->DataloadMapper->create($datasetId, $datasourceId)]);
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
        $result['dataloads'] = $this->DataloadMapper->read($datasetId);
        $result['templates'] = $this->DataSourceController->getTemplates();
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
        return new DataResponse(['update' => $this->DataloadMapper->update($dataloadId, $name, $option, $schedule)]);
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
        return $this->DataloadMapper->delete($dataloadId);
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
        $result = $this->getDataFromDatasource($dataloadId);
        return new DataResponse($result);
    }

    /**
     * execute all dataloads depending on their schedule
     * daily or hourly
     *
     * @NoAdminRequired
     * @param $schedule
     * @return void
     * @throws Exception
     */
    public function executeBySchedule($schedule)
    {
        $schedules = $this->DataloadMapper->getDataloadBySchedule($schedule);
        //$this->logger->debug('DataLoadController 145: execute schedule '.$schedule);
        foreach ($schedules as $dataload) {
            //$this->logger->debug('DataLoadController 147: execute dataload '.$dataload['id']);
            $this->execute($dataload['id']);
        }
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
        //$this->logger->debug('DataLoadController 143:'.$dataloadId);
        $dataloadMetadata = $this->DataloadMapper->getDataloadById($dataloadId);
        $result = $this->getDataFromDatasource($dataloadId);
        $insert = $update = 0;
        $datasetId = $result['datasetId'];
        //$this->logger->debug('DataLoadController 146: loading into dataset '.$datasetId);

        if ($result['error'] === 0) {
            //$this->logger->debug('DataLoadController 149: OK');
            foreach ($result['data'] as &$row) {
                $action = $this->StorageController->update($datasetId, $row[0], $row[1], $row[2], $dataloadMetadata['user_id']);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
            }
        }

        $result = [
            'insert' => $insert,
            'update' => $update,
            'error' => $result['error'],
        ];

        if ($result['error'] === 0) $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_DATALOAD, $dataloadMetadata['user_id']);

        return new DataResponse($result);
    }

    /**
     * get the data from datasource
     * to be used in simulation or execution
     *
     * @NoAdminRequired
     * @param int $dataloadId
     * @return array|NotFoundResponse
     * @throws NotFoundException
     */
    private function getDataFromDatasource(int $dataloadId)
    {
        $dataloadMetadata = $this->DataloadMapper->getDataloadById($dataloadId);
        $datasetMetadata = $this->DatasetController->getOwnDataset($dataloadMetadata['dataset'], $dataloadMetadata['user_id']);

        if (!empty($datasetMetadata)) {
            $option = json_decode($dataloadMetadata['option'], true);
            $option['user_id'] = $dataloadMetadata['user_id'];

            //$this->logger->debug('DataLoadController 187: ' . $dataloadMetadata['option'] . '---' . json_encode($option));
            $result = $this->DataSourceController->read((int)$dataloadMetadata['datasource'], $option);
            $result['datasetId'] = $dataloadMetadata['dataset'];

            if (isset($option['timestamp']) and $option['timestamp'] === 'true') {
                // if datasource should be timestamped/snapshoted
                // shift values by one dimension
                $result['data'] = array_map(function ($tag) {
                    $columns = count($tag);
                    return array($tag[$columns - 2], $tag[$columns - 2], $tag[$columns - 1]);
                }, $result['data']);
                $result['data'] = $this->replaceDimension($result['data'], 1, date("Y-m-d H:i:s"));
            }

            return $result;
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * replace all values of one dimension
     *
     * @NoAdminRequired
     * @param $Array
     * @param $Find
     * @param $Replace
     * @return array
     */
    private function replaceDimension($Array, $Find, $Replace)
    {
        if (is_array($Array)) {
            foreach ($Array as $Key => $Val) {
                if (is_array($Array[$Key])) {
                    $Array[$Key] = $this->replaceDimension($Array[$Key], $Find, $Replace);
                } else {
                    if ($Key == $Find) {
                        $Array[$Key] = $Replace;
                    }
                }
            }
        }
        return $Array;
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
     * @param $dimension3
     * @return DataResponse|NotFoundResponse
     * @throws Exception
     */
    public function updateData(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = $errorMessage = 0;
            $action = array();
            $dimension3 = $this->floatvalue($dimension3);
            if ($dimension3 === false) {
                $errorMessage = $this->l10n->t('3rd field must be a valid number');
            } else {
                $action = $this->StorageController->update($datasetId, $dimension1, $dimension2, $dimension3);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'error' => $errorMessage,
                'validate' => $action['validate'],
            ];

            //$this->logger->error('DataLoadController 88:'.$errorMessage);
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
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
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
     * @param $dimension3
     * @return DataResponse|NotFoundResponse
     */
    public function deleteDataSimulate(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $result = $this->StorageController->deleteSimulate($datasetId, $dimension1, $dimension2, $dimension3);
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
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = $errorMessage = $errorCounter = 0;
            $delimiter = $this->detectDelimiter($import);
            $rows = str_getcsv($import, "\n");

            foreach ($rows as &$row) {
                $row = str_getcsv($row, $delimiter);
                $row[2] = $this->floatvalue($row[2]);
                if ($row[2] === false) {
                    $errorCounter++;
                } else {
                    $action = $this->StorageController->update($datasetId, $row[0], $row[1], $row[2]);
                    $insert = $insert + $action['insert'];
                    $update = $update + $action['update'];
                }
                if ($errorCounter === 2) {
                    // first error is ignored; might be due to header row
                    $errorMessage = $this->l10n->t('3rd field must be a valid number');
                    break;
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
        //$this->logger->debug('DataLoadController 378:' . $datasetId . $path);
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = 0;
            $option = array();
            $option['user_id'] = $datasetMetadata['user_id'];
            $option['path'] = $path;
            $option['link'] = $datasetMetadata['link'];
            $result = $this->DataSourceController->read(DataSourceController::DATASET_TYPE_INTERNAL_FILE, $option);

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

    private function detectDelimiter($data)
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