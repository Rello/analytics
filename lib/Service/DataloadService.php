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
use OCA\Analytics\Controller\StorageController;
use OCA\Analytics\Db\DataloadMapper;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ILogger;

class DataloadService
{
    private $logger;
    private $StorageController;
    private $DatasourceController;
    private $ActivityManager;
    private $DatasetService;
    private $l10n;
    private $DataloadMapper;

    public function __construct(
        IL10N $l10n,
        ILogger $logger,
        ActivityManager $ActivityManager,
        DatasourceController $DatasourceController,
        DatasetService $DatasetService,
        StorageController $StorageController,
        DataloadMapper $DataloadMapper
    )
    {
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->StorageController = $StorageController;
        $this->ActivityManager = $ActivityManager;
        $this->DatasourceController = $DatasourceController;
        $this->DatasetService = $DatasetService;
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
            $this->StorageController->delete($datasetId, '*', '*');
            $bulkInsert = true;
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
                $action = $this->StorageController->update($datasetId, $row[0], $row[1], $row[2], $dataloadMetadata['user_id'], $bulkInsert);
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
        $datasetMetadata = $this->DatasetService->getOwnDataset($dataloadMetadata['dataset'], $dataloadMetadata['user_id']);

        if (!empty($datasetMetadata)) {
            $dataloadMetadata['link'] = $dataloadMetadata['option']; //remap until datasource table is renamed link=>option
            $result = $this->DatasourceController->read((int)$dataloadMetadata['datasource'], $dataloadMetadata);
            $result['datasetId'] = $dataloadMetadata['dataset'];
            return $result;
        } else {
            return new NotFoundResponse();
        }
    }
}