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

use OCA\Analytics\DataSession;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use OCP\IRequest;

class OutputController extends Controller
{
    private $logger;
    private $DatasetController;
    private $rootFolder;
    private $userId;
    private $DataSession;
    private $ShareController;
    private $DataSourceController;
    private $StorageController;
    private $ThresholdController;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IRootFolder $rootFolder,
        DatasetController $DatasetController,
        ShareController $ShareController,
        DataSession $DataSession,
        DataSourceController $DataSourceController,
        StorageController $StorageController,
        ThresholdController $ThresholdController
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->logger = $logger;
        $this->DatasetController = $DatasetController;
        $this->rootFolder = $rootFolder;
        $this->DataSession = $DataSession;
        $this->ShareController = $ShareController;
        $this->DataSourceController = $DataSourceController;
        $this->StorageController = $StorageController;
        $this->ThresholdController = $ThresholdController;
    }

    /**
     * get the data when requested from internal page
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param array $options
     * @return DataResponse|NotFoundResponse
     * @throws NotFoundException
     */
    public function read(int $datasetId, $options)
    {
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (empty($datasetMetadata)) $datasetMetadata = $this->ShareController->getSharedDataset($datasetId);

        if (!empty($datasetMetadata)) {
            $result = $this->getData($datasetMetadata, $options);
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
    }

    /**
     * Get the data from backend;
     * pre-evaluation of valid datasetId within read & readPublic is trusted here
     *
     * @NoAdminRequired
     * @param $datasetMetadata
     * @param $options
     * @return array|NotFoundException
     * @throws NotFoundException
     */
    private function getData($datasetMetadata, $options)
    {
        //$this->logger->error('dataset csv result: ' . $result);
        $datasource = (int)$datasetMetadata['type'];
        if ($datasource === DataSourceController::DATASET_TYPE_INTERNAL_DB) {
            $result = $this->StorageController->read($datasetMetadata, $options);
        } else {
            $option = array();
            $option['user_id'] = $datasetMetadata['user_id'];
            $option['link'] = $datasetMetadata['link'];
            $result = $this->DataSourceController->read($datasource, $option);
            unset($result['error']);
        }

        $thresholds = $this->ThresholdController->read($datasetMetadata['id']);
        $result['thresholds'] = $thresholds;

        unset($datasetMetadata['id']
            , $datasetMetadata['parent']
            , $datasetMetadata['user_id']
            , $datasetMetadata['link']
            , $datasetMetadata['dimension1']
            , $datasetMetadata['dimension2']
            , $datasetMetadata['dimension3']
            , $datasetMetadata['password']
        );
        $result['options'] = $datasetMetadata;

        return $result;
    }

    /**
     * get the data when requested from public page
     *
     * @NoAdminRequired
     * @PublicPage
     * @UseSession
     * @param $token
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return DataResponse|NotFoundResponse
     * @throws NotFoundException
     */
    public function readPublic($token, $objectDrilldown, $dateDrilldown)
    {
        $share = $this->ShareController->getDatasetByToken($token);
        if (empty($share)) {
            // Dataset not shared or wrong token
            return new NotFoundResponse();
        } else {
            if ($share['password'] !== null) {
                $password = $this->DataSession->getPasswordForShare($token);
                $passwordVerification = $this->ShareController->verifyPassword($password, $share['password']);
                if ($passwordVerification === false) {
                    return new NotFoundResponse();
                }
            }
            //TODO: umstellung auf Options
            $result = $this->getData($share, $objectDrilldown, $dateDrilldown);
            return new DataResponse($result);
        }
    }
}