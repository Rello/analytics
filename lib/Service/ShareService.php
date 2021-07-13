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

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\ShareMapper;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class ShareService
{
    const SHARE_TYPE_USER = 0;
    const SHARE_TYPE_GROUP = 1;
    const SHARE_TYPE_USERGROUP = 2;
    const SHARE_TYPE_LINK = 3;
    const SHARE_TYPE_ROOM = 10;

    /** @var LoggerInterface */
    private $logger;
    /** @var ShareMapper */
    private $ShareMapper;
    private $secureRandom;
    private $ActivityManager;
    /** @var IGroupManager */
    private $groupManager;
    /** @var IUserManager */
    private $userManager;
    private $VariableService;

    public function __construct(
        LoggerInterface $logger,
        ShareMapper $ShareMapper,
        ActivityManager $ActivityManager,
        IGroupManager $groupManager,
        ISecureRandom $secureRandom,
        IUserManager $userManager,
        VariableService $VariableService
    )
    {
        $this->logger = $logger;
        $this->ShareMapper = $ShareMapper;
        $this->secureRandom = $secureRandom;
        $this->groupManager = $groupManager;
        $this->ActivityManager = $ActivityManager;
        $this->userManager = $userManager;
        $this->VariableService = $VariableService;
    }

    /**
     * create a new share
     *
     * @NoAdminRequired
     * @param $datasetId
     * @param $type
     * @param $user
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function create($datasetId, $type, $user)
    {
        if ((int)$type === self::SHARE_TYPE_LINK) {
            $token = $this->generateToken();
            $this->ShareMapper->createShare($datasetId, $type, $user, $token);
        } elseif ((int)$type === self::SHARE_TYPE_USER) {
            $this->ShareMapper->createShare($datasetId, $type, $user, null);
        } elseif ((int)$type === self::SHARE_TYPE_GROUP) {
            // add the entry for the group
            $parent = $this->ShareMapper->createShare($datasetId, self::SHARE_TYPE_GROUP, $user, null);

            // add entries for every user of the group
            $usersInGroup = $this->groupManager->displayNamesInGroup($user);
            foreach ($usersInGroup as $userId => $displayName) {
                $this->ShareMapper->createShare($datasetId, self::SHARE_TYPE_USERGROUP, $userId, null, $parent);
            }
        }

        $this->ActivityManager->triggerEvent($datasetId, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_SHARE);
        return true;
    }

    /**
     * get all shares for a dataset
     *
     * @NoAdminRequired
     * @param $datasetId
     * @return array
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
        return $shares;
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
        $dataset = $this->ShareMapper->getDatasetByToken($token);
        $dataset = $this->VariableService->replaceTextVariables($dataset);

        return $dataset;
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
        $sharedDatasets = $this->ShareMapper->getSharedDatasets();
        foreach ($sharedDatasets as &$sharedDataset) {
            $sharedDataset['type'] = '99';
            $sharedDataset['parrent'] = '0';
        }
        return $sharedDatasets;
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
     * delete a share
     *
     * @NoAdminRequired
     * @param $shareId
     * @return bool
     */
    public function delete($shareId)
    {
        $share = $this->ShareMapper->getShare($shareId);
        $type = $share['type'];
        $this->logger->error('share type: ' . $type);
        if ((int)$type === self::SHARE_TYPE_LINK) {
            $this->ShareMapper->deleteShare($shareId);
        } elseif ((int)$type === self::SHARE_TYPE_USER) {
            $this->ShareMapper->deleteShare($shareId);
        } elseif ((int)$type === self::SHARE_TYPE_USERGROUP) {
            $this->ShareMapper->deleteShare($shareId);
        } elseif ((int)$type === self::SHARE_TYPE_GROUP) {
            $this->ShareMapper->deleteShare($shareId);
            $this->ShareMapper->deleteShareByParent($shareId);
        }
        return true;
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
     * update/set share password
     *
     * @NoAdminRequired
     * @param $shareId
     * @param $password
     * @param $canEdit
     * @return bool
     */
    public function update($shareId, $password = null, $canEdit = null)
    {
        if ($password !== null) {
            $password = password_hash($password, PASSWORD_DEFAULT);
            return $this->ShareMapper->updateSharePassword($shareId, $password);
        }
        if ($canEdit !== null) {
            $canEdit === 'true' ? $canEdit = \OCP\Constants::PERMISSION_UPDATE : $canEdit = \OCP\Constants::PERMISSION_READ;
            return $this->ShareMapper->updateSharePermissions($shareId, $canEdit);
        }
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