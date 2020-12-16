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
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

class ShareController extends Controller
{
    const SHARE_TYPE_USER = 0;
    const SHARE_TYPE_GROUP = 1;
    const SHARE_TYPE_USERGROUP = 2;
    const SHARE_TYPE_LINK = 3;
    const SHARE_TYPE_ROOM = 10;

    private $logger;
    private $ShareMapper;
    private $secureRandom;
    private $ActivityManager;
    /** @var IGroupManager */
    private $groupManager;
    /** @var IUserSession */
    private $userSession;
    /** @var IUserManager */
    private $userManager;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        ShareMapper $ShareMapper,
        ActivityManager $ActivityManager,
        IGroupManager $groupManager,
        ISecureRandom $secureRandom,
        IUserSession $userSession,
        IUserManager $userManager
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ShareMapper = $ShareMapper;
        $this->secureRandom = $secureRandom;
        $this->groupManager = $groupManager;
        $this->ActivityManager = $ActivityManager;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
    }

    /**
     * create a new share
     *
     * @NoAdminRequired
     * @param $datasetId
     * @param $type
     * @param $user
     * @return bool
     */
    public function create($datasetId, $type, $user)
    {
        if ((int)$type === self::SHARE_TYPE_LINK) {
            $token = $this->generateToken();
        } else {
            $token = null;
        }
        $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_SHARE);
        return $this->ShareMapper->createShare($datasetId, $type, $user, $token);
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
        $shares = $this->ShareMapper->getShares($datasetId);
        foreach ($shares as &$share) {
            if ($share['type'] === 0) {
                $share['displayName'] = $this->userManager->get($share['uid_owner'])->getDisplayName();
            }
            $share['pass'] = $share['pass'] !== null;
        }
        return new DataResponse($shares);
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