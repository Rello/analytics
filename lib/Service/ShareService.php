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

namespace OCA\Analytics\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\ShareMapper;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

class ShareService
{
    const SHARE_TYPE_USER = 0;
    const SHARE_TYPE_GROUP = 1;
    const SHARE_TYPE_USERGROUP = 2;
    const SHARE_TYPE_LINK = 3;
    const SHARE_TYPE_ROOM = 10;

    /** @var ILogger */
    private $logger;
    /** @var ShareMapper */
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
        ShareMapper $ShareMapper,
        ActivityManager $ActivityManager,
        IGroupManager $groupManager,
        ISecureRandom $secureRandom,
        IUserSession $userSession,
        IUserManager $userManager
    )
    {
        $this->ShareMapper = $ShareMapper;
        $this->secureRandom = $secureRandom;
        $this->groupManager = $groupManager;
        $this->ActivityManager = $ActivityManager;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
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
        $sharedDatasetsByGroup = array();
        $groups = $this->groupManager->getUserGroupIds($this->userSession->getUser());

        foreach ($groups as $group) {
            $sharedDatasetByGroup = $this->ShareMapper->getDatasetsByGroup($group);
            $sharedDatasetsByGroup = array_merge($sharedDatasetsByGroup, $sharedDatasetByGroup);
        }
        $sharedDatasets = $this->ShareMapper->getSharedDatasets();

        $sharedDatasetsCombined = array_merge($sharedDatasetsByGroup, $sharedDatasets);
        foreach ($sharedDatasetsCombined as &$sharedDataset) {
            $sharedDataset['type'] = '99';
            $sharedDataset['parrent'] = '0';
        }

        return $sharedDatasetsCombined;
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
        $sharedDataset = $this->ShareMapper->getSharedDataset($id);
        if (empty($sharedDataset)) {
            $groups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
            foreach ($groups as $group) {
                $sharedDataset = $this->ShareMapper->getDatasetByGroupId($group, $id);
                break;
            }
        }
        $sharedDataset['type'] = '99';
        $sharedDataset['parrent'] = '0';
        return $sharedDataset;
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
}