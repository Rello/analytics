<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Activity\ActivityManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use OCP\IRequest;

class DataLoadController extends Controller
{
    private $logger;
    private $StorageController;
    private $DataSourceController;
    private $userId;
    private $ActivityManager;
    private $DatasetController;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        ActivityManager $ActivityManager,
        DataSourceController $DataSourceController,
        DatasetController $DatasetController,
        StorageController $StorageController
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->logger = $logger;
        $this->StorageController = $StorageController;
        $this->ActivityManager = $ActivityManager;
        $this->DataSourceController = $DataSourceController;
        $this->DatasetController = $DatasetController;
    }

    /**
     * update data from input form
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return DataResponse|NotFoundResponse
     */
    public function update(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        //$this->NotificationManager->triggerNotification(NotificationManager::OBJECT_DATASET, $datasetId, NotificationManager::SUBJECT_THRESHOLD, ['subject' => 'Pflanze', 'rule' => 'Hum too low']);
        //disabled for the moment
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = 0;
            $dimension3 = str_replace(',', '.', $dimension3);
            $action = $this->StorageController->update($datasetId, $dimension1, $dimension2, $dimension3);
            $insert = $insert + $action['insert'];
            $update = $update + $action['update'];

            $result = [
                'insert' => $insert,
                'update' => $update
            ];

            $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD);
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * update data from input form
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @return DataResponse|NotFoundResponse
     */
    public function delete(int $datasetId, $dimension1, $dimension2)
    {
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $result = $this->StorageController->delete($datasetId, $dimension1, $dimension2);
            return new DataResponse($result);
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
     */
    public function importClipboard($datasetId, $import)
    {
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = 0;
            $delimiter = $this->detectDelimiter($import);
            $rows = str_getcsv($import, "\n");

            foreach ($rows as &$row) {
                $row = str_getcsv($row, $delimiter);
                $row[2] = str_replace(',', '.', $row[2]);
                $action = $this->StorageController->update($datasetId, $row[0], $row[1], $row[2]);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
            }

            $result = [
                'insert' => $insert,
                'update' => $update,
                'delimiter' => $delimiter
            ];

            $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
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

    /**
     * Import data into dataset from an internal or external file
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $path
     * @return DataResponse|NotFoundResponse
     * @throws NotFoundException
     */
    public function importFile(int $datasetId, $path)
    {
        //$this->logger->error('DataLoadController 100:'.$datasetId. $path);
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = 0;
            $datasetMetadata['type'] = DataSourceController::DATASET_TYPE_INTERNAL_FILE;
            $result = $this->DataSourceController->read($datasetMetadata, $path);

            foreach ($result['data'] as &$row) {
                $action = $this->StorageController->update($datasetId, $row['dimension1'], $row['dimension2'], $row['dimension3']);
                $insert = $insert + $action['insert'];
                $update = $update + $action['update'];
            }

            $result = [
                'insert' => $insert,
                'update' => $update
            ];

            $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATA, ActivityManager::SUBJECT_DATA_ADD_IMPORT);
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }
}