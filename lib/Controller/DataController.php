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

use OCA\Analytics\DataSession;
use OCA\Analytics\Datasource\FileService;
use OCA\Analytics\Datasource\GithubService;
use OCA\Analytics\Service\DataService;
use OCA\Analytics\Service\DatasetService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ILogger;
use OCP\IRequest;

class DataController extends Controller
{
    private $logger;
    private $DataService;
    private $DatasetService;
    private $GithubService;
    private $FileService;
    private $rootFolder;
    private $userId;
    /** @var DataSession */
    private $DataSession;
    private $ShareController;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IRootFolder $rootFolder,
        DataService $DataService,
        GithubService $GithubService,
        DatasetService $DatasetService,
        FileService $FileService,
        ShareController $ShareController,
        DataSession $DataSession
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->logger = $logger;
        $this->DataService = $DataService;
        $this->DatasetService = $DatasetService;
        $this->GithubService = $GithubService;
        $this->FileService = $FileService;
        $this->rootFolder = $rootFolder;
        $this->DataSession = $DataSession;
        $this->ShareController = $ShareController;
    }

    /**
     * get the data when requested from internal page
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return DataResponse|NotFoundResponse
     * @throws NotFoundException
     */
    public function read(int $datasetId, $objectDrilldown, $dateDrilldown)
    {

        $datasetMetadata = $this->DatasetService->getOwnDataset($datasetId);
        if (empty($datasetMetadata)) $datasetMetadata = $this->ShareController->getSharedDataset($datasetId);

        if (!empty($datasetMetadata)) {
            //$this->logger->error('test');
            $result = $this->getData($datasetMetadata, $objectDrilldown, $dateDrilldown);
            return new DataResponse($result);
        } else {
            return new NotFoundResponse();
        }
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
            $result = $this->getData($share, $objectDrilldown, $dateDrilldown);
            return new DataResponse($result);
        }
    }

    /**
     * Get the data from backend;
     * pre-evaluation of valid datasetId within read & readPublic is trusted here
     *
     * @NoAdminRequired
     * @param $datasetMetadata
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return array
     * @throws NotFoundException
     */
    private function getData($datasetMetadata, $objectDrilldown, $dateDrilldown)
    {
        $datasetMetadata['type'] = (int)$datasetMetadata['type'];
        if ($datasetMetadata['type'] === 1) $result = $this->FileService->read($datasetMetadata);
        elseif ($datasetMetadata['type'] === 2) $result = $this->DataService->read($datasetMetadata, $objectDrilldown, $dateDrilldown);
        elseif ($datasetMetadata['type'] === 3) $result = $this->GithubService->read($datasetMetadata);

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
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $import
     * @return DataResponse
     */
    public function importCSV(int $datasetId, $import)
    {
        return new DataResponse($this->DataService->import($datasetId, $import));
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $path
     * @return DataResponse
     * @throws NotFoundException
     */
    public function importFile(int $datasetId, $path)
    {
        return new DataResponse($this->FileService->import($datasetId, $path));
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $dimension1
     * @param $dimension2
     * @param $dimension3
     * @return DataResponse
     */
    public function update(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        return new DataResponse($this->DataService->update($datasetId, $dimension1, $dimension2, $dimension3));
    }
}