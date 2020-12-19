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
use OCA\Analytics\Service\ShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
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
    private $ShareService;
    private $DatasourceController;
    private $StorageController;
    private $ThresholdController;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IRootFolder $rootFolder,
        DatasetController $DatasetController,
        ShareService $ShareService,
        DataSession $DataSession,
        DatasourceController $DatasourceController,
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
        $this->ShareService = $ShareService;
        $this->DatasourceController = $DatasourceController;
        $this->StorageController = $StorageController;
        $this->ThresholdController = $ThresholdController;
    }

    /**
     * get the data when requested from internal page
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $filteroptions
     * @return DataResponse|NotFoundResponse
     * @throws NotFoundException
     */
    public function read(int $datasetId, $filteroptions)
    {
        $datasetMetadata = $this->DatasetController->getOwnDataset($datasetId);
        if (empty($datasetMetadata)) $datasetMetadata = $this->ShareService->getSharedDataset($datasetId);

        if (!empty($datasetMetadata)) {
            $datasetMetadata = $this->evaluateCanFilter($datasetMetadata, $filteroptions);
            $result = $this->getData($datasetMetadata);

            $response = new DataResponse($result, HTTP::STATUS_OK);
            //$response->setETag(md5('123'));
            return $response;
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
     * @return array|NotFoundException
     * @throws NotFoundException
     */
    private function getData($datasetMetadata)
    {
        $datasourceId = (int)$datasetMetadata['type'];

        if ($datasourceId === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
            $result = $this->StorageController->read($datasetMetadata);
        } else {
            $option = array();
            // before 3.1.0, the options were in another format. as of 3.1.0 the standard option array is used
            if ($datasetMetadata['link'][0] !== '{') {
                $option['link'] = $datasetMetadata['link'];
            } else {
                $option = json_decode($datasetMetadata['link'], true);
            }
            $option['user_id'] = $datasetMetadata['user_id'];

            $result = $this->DatasourceController->read($datasourceId, $option);
            unset($result['error']);
        }

        $result['thresholds'] = $this->ThresholdController->read($datasetMetadata['id']);

        unset($datasetMetadata['parent']
            , $datasetMetadata['user_id']
            , $datasetMetadata['link']
            , $datasetMetadata['dimension1']
            , $datasetMetadata['dimension2']
            , $datasetMetadata['dimension3']
            , $datasetMetadata['value']
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
     * @param $filteroptions
     * @return DataResponse|NotFoundResponse
     * @throws NotFoundException
     */
    public function readPublic($token, $filteroptions)
    {
        $share = $this->ShareService->getDatasetByToken($token);
        if (empty($share)) {
            // Dataset not shared or wrong token
            return new NotFoundResponse();
        } else {
            if ($share['password'] !== null) {
                $password = $this->DataSession->getPasswordForShare($token);
                $passwordVerification = $this->ShareService->verifyPassword($password, $share['password']);
                if ($passwordVerification === false) {
                    return new NotFoundResponse();
                }
            }
            $share = $this->evaluateCanFilter($share, $filteroptions);
            $result = $this->getData($share);
            return new DataResponse($result);
        }
    }

    /**
     * evaluate if the user did filter in the report and if he is allowed to filter (shared reports)
     *
     * @param $metadata
     * @param $filteroptions
     * @return mixed
     */
    private function evaluateCanFilter($metadata, $filteroptions)
    {
        if ($filteroptions and $filteroptions !== '' and $metadata['permissions'] === \OCP\Constants::PERMISSION_UPDATE) {
            // send current user filteroptions to the datarequest
            // only if the report has update-permissions
            // if nothing is changed by the user, the filter which is stored for the report, will be used
            $metadata['filteroptions'] = $filteroptions;
        }
        return $metadata;
    }
}