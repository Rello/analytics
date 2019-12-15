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
use OCP\IL10N;
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
    private $l10n;

    public function __construct(
        string $AppName,
        IRequest $request,
        IL10N $l10n,
        $userId,
        ILogger $logger,
        ActivityManager $ActivityManager,
        DataSourceController $DataSourceController,
        DatasetController $DatasetController,
        StorageController $StorageController
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
     * @throws \Exception
     */
    public function update(int $datasetId, $dimension1, $dimension2, $dimension3)
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
     * @throws \Exception
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
     * @throws \Exception
     */
    public function importFile(int $datasetId, $path)
    {
        //$this->logger->error('DataLoadController 100:'.$datasetId. $path);
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (!empty($datasetMetadata)) {
            $insert = $update = 0;
            $datasetMetadata['type'] = DataSourceController::DATASET_TYPE_INTERNAL_FILE;
            $result = $this->DataSourceController->read($datasetMetadata, $path);

            if ($result['error'] === 0) {
                foreach ($result['data'] as &$row) {
                    $action = $this->StorageController->update($datasetId, $row['dimension1'], $row['dimension2'], $row['dimension3']);
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
            return number_format(floatval($val), 2);
        } else {
            return false;
        }
    }
}