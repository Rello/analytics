<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
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
     * @param $reportId
     * @param $type
     * @param $user
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function create($reportId, $type, $user)
    {
        if ((int)$type === self::SHARE_TYPE_LINK) {
            $token = $this->generateToken();
            $this->ShareMapper->createShare($reportId, $type, $user, $token);
        } elseif ((int)$type === self::SHARE_TYPE_USER) {
            $this->ShareMapper->createShare($reportId, $type, $user, null);
        } elseif ((int)$type === self::SHARE_TYPE_GROUP) {
            // add the entry for the group
            $parent = $this->ShareMapper->createShare($reportId, self::SHARE_TYPE_GROUP, $user, null);

            // add entries for every user of the group
            $usersInGroup = $this->groupManager->displayNamesInGroup($user);
            foreach ($usersInGroup as $userId => $displayName) {
                $this->ShareMapper->createShare($reportId, self::SHARE_TYPE_USERGROUP, $userId, null, $parent);
            }
        }

        $this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_SHARE);
        return true;
    }

    /**
     * get all shares for a report
     *
     * @NoAdminRequired
     * @param $reportId
     * @return array
     */
    public function read($reportId)
    {
        $shares = $this->ShareMapper->getShares($reportId);
        foreach ($shares as &$share) {
            if ($share['type'] === 0) {
                $share['displayName'] = $this->userManager->get($share['uid_owner'])->getDisplayName();
            }
            $share['pass'] = $share['pass'] !== null;
        }
        return $shares;
    }

    /**
     * get all report by token
     *
     * @NoAdminRequired
     * @param $token
     * @return array
     */
    public function getReportByToken($token)
    {
        $reportId = $this->ShareMapper->getReportByToken($token);
        $reportId = $this->VariableService->replaceTextVariables($reportId);

        return $reportId;
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
     * get all reports shared with user
     *
     * @NoAdminRequired
     */
    public function getSharedReports()
    {
        $sharedReports = $this->ShareMapper->getSharedReports();
        foreach ($sharedReports as &$sharedReport) {
            $sharedReport['type'] = '99';
            $sharedReport['parrent'] = '0';
        }
        return $sharedReports;
    }

    /**
     * get metadata of a report, shared with current user
     *
     * @NoAdminRequired
     * @param $reportId
     * @return array
     */
    public function getSharedReport($reportId)
    {
        return $this->ShareMapper->getSharedReport($reportId);
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
     * delete all shares for a report
     *
     * @NoAdminRequired
     * @param $reportId
     * @return bool
     */
    public function deleteShareByReport($reportId)
    {
        return $this->ShareMapper->deleteShareByReport($reportId);
    }

    /**
     * update/set share password
     *
     * @NoAdminRequired
     * @param $shareId
     * @param null $password
     * @param null $canEdit
     * @param null $domain
     * @return bool
     */
    public function update($shareId, $password = null, $canEdit = null, $domain = null)
    {
        if ($password !== null) {
            $password = password_hash($password, PASSWORD_DEFAULT);
            return $this->ShareMapper->updateSharePassword($shareId, $password);
        }
        if ($domain !== null) {
            return $this->ShareMapper->updateShareDomain($shareId, $domain);
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