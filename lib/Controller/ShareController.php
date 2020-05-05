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

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\ShareMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Security\ISecureRandom;

class ShareController extends Controller
{
    const SHARE_TYPE_USER = 0;
    const SHARE_TYPE_LINK = 3;

    private $logger;
    private $ShareMapper;
    private $secureRandom;
    private $ActivityManager;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        ShareMapper $ShareMapper,
        ActivityManager $ActivityManager,
        ISecureRandom $secureRandom
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ShareMapper = $ShareMapper;
        $this->secureRandom = $secureRandom;
        $this->ActivityManager = $ActivityManager;
    }

    /**
     * get all dataset by token
     *
     * @NoAdminRequired
     * @param $token
     * @return array
     */
    public function getDatasetByToken($token)
    {
        return $this->ShareMapper->getDatasetByToken($token);
    }

    /**
     * verify password hahes
     *
     * @NoAdminRequired
     * @param $password
     * @param $sharePassword
     * @return bool
     */
    public function verifyPassword($password, $sharePassword)
    {
        return password_verify($password, $sharePassword);
    }

    /**
     * get all datasets shared with user
     *
     * @NoAdminRequired
     */
    public function getSharedDatasets()
    {
        return $this->ShareMapper->getSharedDatasets();
    }

    /**
     * get metadata of a dataset, shared with current user
     *
     * @NoAdminRequired
     * @param $id
     * @return array
     */
    public function getSharedDataset($id)
    {
        return $this->ShareMapper->getSharedDataset($id);
    }

    /**
     * create a new share
     *
     * @NoAdminRequired
     * @param $datasetId
     * @param $type
     * @return bool
     */
    public function create($datasetId, $type)
    {
        $token = $this->generateToken();
        //$this->logger->error($type . $token);
        $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_SHARE);
        return $this->ShareMapper->createShare($datasetId, $type, null, $token);
    }

    /**
     * get all shares for a dataset
     *
     * @NoAdminRequired
     * @param $datasetId
     * @return DataResponse
     */
    public function read($datasetId)
    {
        return new DataResponse($this->ShareMapper->getShares($datasetId));
    }

    /**
     * update/set share password
     *
     * @NoAdminRequired
     * @param $shareId
     * @param $password
     * @return bool
     */
    public function update($shareId, $password)
    {
        //$this->logger->error($shareId . $password);
        if ($password !== '') $password = password_hash($password, PASSWORD_DEFAULT);
        else $password = null;
        return $this->ShareMapper->updateShare($shareId, $password);
    }

    /**
     * delete a share
     *
     * @NoAdminRequired
     * @param $shareId
     * @return bool
     */
    public function delete($shareId)
    {
        return $this->ShareMapper->deleteShare($shareId);
    }

    /**
     * delete all shares for a dataset
     *
     * @NoAdminRequired
     * @param $datasetId
     * @return bool
     */
    public function deleteShareByDataset($datasetId)
    {
        return $this->ShareMapper->deleteShareByDataset($datasetId);
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