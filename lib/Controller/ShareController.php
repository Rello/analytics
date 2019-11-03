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

namespace OCA\data\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Security\ISecureRandom;

class ShareController extends Controller
{
    private $logger;
    private $DBController;
    private $secureRandom;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        DbController $DBController,
        ISecureRandom $secureRandom
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DBController = $DBController;
        $this->secureRandom = $secureRandom;
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
    public function create($datasetId, $type)
    {
        if ($type === '3') { // Link Share
            $token = $this->generateToken();
        }
        $this->logger->error($type . $token);

        return new DataResponse($this->DBController->createShare($datasetId, $type, null, $token));
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
    public function update($shareId, $password)
    {
        $this->logger->error($shareId . $password);
        if ($password !== '') $password = password_hash($password, PASSWORD_DEFAULT);
        else $password = null;
        $this->logger->error($shareId . $password);
        return new DataResponse($this->DBController->updateShare($shareId, $password));
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

    /**
     * generate to token used to authenticate federated shares
     *
     * @return string
     */
    private function generateToken()
    {
        $token = $this->secureRandom->generate(
            15,
            ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_DIGITS);
        return $token;
    }
}