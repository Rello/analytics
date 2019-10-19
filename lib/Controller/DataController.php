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

use OCA\data\Service\DataService;
use OCA\data\Service\DatasetService;
use OCA\data\Service\GithubService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
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
    private $rootFolder;
    private $userId;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IRootFolder $rootFolder,
        DataService $DataService,
        GithubService $GithubService,
        DatasetService $DatasetService
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->logger = $logger;
        $this->DataService = $DataService;
        $this->DatasetService = $DatasetService;
        $this->GithubService = $GithubService;
        $this->rootFolder = $rootFolder;
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @param int $datasetId
     * @param $objectDrilldown
     * @param $dateDrilldown
     * @return DataResponse
     */
    public function read(int $datasetId, $objectDrilldown, $dateDrilldown)
    {
        $dataset = $this->DatasetService->read($datasetId);
        $datasetType = $dataset['type'];

        if ($datasetType === 2) $result = $this->DataService->read($datasetId, $objectDrilldown, $dateDrilldown);
        if ($datasetType === 3) $result = $this->GithubService->read();

        $result['options'] = $dataset;

        return new DataResponse($result);
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
    public function update(int $datasetId, $object, $value, $date)
    {
        return new DataResponse($this->DataService->update($datasetId, $object, $value, $date));
    }

    /**
     * Get the items for the selected category
     *
     * @NoAdminRequired
     * @PublicPage
     * @param $token
     * @return DataResponse
     */
    public function readPublic($token, $objectDrilldown, $dateDrilldown)
    {
        $tokenArray = ['t44' => 4, 't33' => 3];
        if (array_key_exists($token, $tokenArray)) {
            $datasetId = $tokenArray[$token];
            $result = $this->DataService->read($datasetId, $objectDrilldown, $dateDrilldown, 'admin');
        } else {
            $result = ['error'];
        }

        return new DataResponse($result);

    }

}