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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;

class ShareController extends Controller
{
    private $logger;
    private $DBController;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        DbController $DBController
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DBController = $DBController;
    }

    /**
     * get all datasets
     * @NoAdminRequired
     */
    public function getDatasetByToken($token)
    {
        return $this->DBController->getDatasetByToken($token);
    }

    /**
     * get all datasets
     * @NoAdminRequired
     */
    public function verifyPassword($password, $sharePassword)
    {
        return password_verify($password, $sharePassword);
    }

    /**
     * get all datasets shared with user
     * @NoAdminRequired
     */
    public function getSharedDatasets()
    {
        return $this->DBController->getSharedDatasets();
    }

    /**
     * get all datasets shared with user
     * @NoAdminRequired
     * @param $id
     * @return
     */
    public function getSharedDataset($id)
    {
        return $this->DBController->getSharedDataset($id);
    }

    /**
     * get all datasets shared with user
     * @NoAdminRequired
     * @param $id
     * @return
     */
    public function create($id)
    {
        return new DataResponse($this->DBController->createShare($id));
    }

    /**
     * get all datasets shared with user
     * @NoAdminRequired
     * @param $id
     * @return
     */
    public function read($datasetId)
    {
        return new DataResponse($this->DBController->getShare($datasetId));
    }

    /**
     * get all datasets shared with user
     * @NoAdminRequired
     * @param $id
     * @return
     */
    public function update($shareId)
    {
        return new DataResponse($this->DBController->updateShare($shareId));
    }

    /**
     * get all datasets shared with user
     * @NoAdminRequired
     * @param $id
     * @return
     */
    public function delete($shareId)
    {
        return new DataResponse($this->DBController->deleteShare($shareId));
    }
}