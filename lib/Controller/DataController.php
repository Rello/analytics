<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Controller;

use OCA\data\DataSession;
use OCA\data\Service\DataService;
use OCA\data\Service\DatasetService;
use OCA\data\Service\FileService;
use OCA\data\Service\GithubService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IRootFolder;
use OCP\IDbConnection;
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
    }

    /**
     * get the data when requested from internal page
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return DataResponse|NotFoundResponse
     */
    public function read(int $datasetId, $objectDrilldown, $dateDrilldown)
    {
        $datasetMetadata = $this->DatasetService->getOwnDataset($datasetId);
        if ($datasetMetadata === false) $datasetMetadata = $this->ShareController->getSharedDataset($datasetId);

        if ($datasetMetadata !== false) {
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
     * @return DataResponse|NotFoundResponse
     */
    public function readPublic($token, $objectDrilldown, $dateDrilldown)
    {
        $share = $this->ShareController->getDatasetByToken($token);
        if ($share === false) {
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
     * @param int $datasetId
     * @param $import
     * @return array
     */

    private function getData($datasetMetadata, $objectDrilldown, $dateDrilldown)
    {
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
     * @param $import
     * @return DataResponse
     */
    public function importFile(int $datasetId, $path)
    {
        $file = $this->rootFolder->getUserFolder($this->userId)->get($path);
        $import = $file->getContent();
        return new DataResponse($this->DataService->import($datasetId, $import));
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $object
     * @param $value
     * @param $date
     */
    public function update(int $datasetId, $dimension1, $dimension2, $dimension3)
    {
        return new DataResponse($this->DataService->update($datasetId, $dimension1, $dimension2, $dimension3));
    }

}