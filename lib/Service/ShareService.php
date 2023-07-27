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
use OCA\Analytics\Db\ReportMapper;
use OCP\DB\Exception;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class ShareService
{
    const SHARE_TYPE_USER = 0;
    const SHARE_TYPE_GROUP = 1;
    const SHARE_TYPE_USERGROUP = 2; // obsolete
    const SHARE_TYPE_LINK = 3;
    const SHARE_TYPE_ROOM = 10;

    /** @var IUserSession */
    private $userSession;
    /** @var LoggerInterface */
    private $logger;
    /** @var ShareMapper */
    private $ShareMapper;
    private $ReportMapper;
    private $secureRandom;
    private $ActivityManager;
    /** @var IGroupManager */
    private $groupManager;
    /** @var IUserManager */
    private $userManager;
    private $VariableService;

    public function __construct(
        IUserSession $userSession,
        LoggerInterface $logger,
        ShareMapper $ShareMapper,
        ReportMapper $ReportMapper,
        ActivityManager $ActivityManager,
        IGroupManager $groupManager,
        ISecureRandom $secureRandom,
        IUserManager $userManager,
        VariableService $VariableService
    )
    {
        $this->userSession = $userSession;
        $this->logger = $logger;
        $this->ShareMapper = $ShareMapper;
        $this->ReportMapper = $ReportMapper;
        $this->secureRandom = $secureRandom;
        $this->groupManager = $groupManager;
        $this->ActivityManager = $ActivityManager;
        $this->userManager = $userManager;
        $this->VariableService = $VariableService;
    }

    /**
     * create a new share
     *
     * @param $reportId
     * @param $type
     * @param $user
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function create($reportId, $type, $user)
    {
        $token = null;
        if ((int)$type === self::SHARE_TYPE_LINK) {
            $token = $this->generateToken();
        }
        $this->ShareMapper->createShare($reportId, $type, $user, $token);
        $this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_SHARE);
        return true;
    }

    /**
     * get all shares for a report
     *
     * @param $reportId
     * @return array
     * @throws Exception
     */
    public function read($reportId)
    {

        $shares = $this->ShareMapper->getShares($reportId);
        foreach ($shares as $key => $share) {
            if ((int)$share['type'] === self::SHARE_TYPE_USER) {
                if (!$this->userManager->userExists($share['uid_owner'])) {
                    $this->ShareMapper->deleteShare($share['id']);
                    unset($shares[$key]);
                    continue;
                }
                $shares[$key]['displayName'] = $this->userManager->get($share['uid_owner'])->getDisplayName();
            }
            $shares[$key]['pass'] = $share['pass'] !== null;
        }
        return $shares;
    }

    /**
     * get all report by token
     *
     * @param $token
     * @return array
     */
    public function getReportByToken($token)
    {
        $reportId = $this->ShareMapper->getReportByToken($token);
        return $this->VariableService->replaceTextVariables($reportId);
    }

    /**
     * verify password hahes
     *
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
     * @throws Exception
     */
    public function getSharedReports()
    {
        $sharedReports = $this->ShareMapper->getAllSharedReports();
        $groupsOfUser = $this->groupManager->getUserGroups($this->userSession->getUser());
        $reports = array();

        foreach ($sharedReports as $sharedReport) {
            // shared with a group?
            if ((int)$sharedReport['shareType'] === self::SHARE_TYPE_GROUP) {
                // is the current user part of this group?
                $this->logger->debug('Shareservice: is group share');
                if (array_key_exists($sharedReport['shareUid_owner'], $groupsOfUser)) {
                    // was the report not yet added to the result?
                    if (!in_array($sharedReport["id"], array_column($reports, "id"))) {
                        unset($sharedReport['shareType']);
                        unset($sharedReport['shareUid_owner']);
                        $sharedReport['isShare'] = self::SHARE_TYPE_GROUP;
                        $reports[] = $sharedReport;
                    }
                }
            // shared with a user directly?
            } elseif ((int)$sharedReport['shareType'] === self::SHARE_TYPE_USER) {
                // current user matching?
                $this->logger->debug('Shareservice: is user share; check against current user: ' . $this->userSession->getUser()->getUID());
                if ($this->userSession->getUser()->getUID() === $sharedReport['shareUid_owner']) {
                    // was the report not yet added to the result?
                    $this->logger->debug('Shareservice: Share belongs to current user');
                    if (!in_array($sharedReport["id"], array_column($reports, "id"))) {
                        $this->logger->debug('Shareservice: Share added to output');
                        unset($sharedReport['shareType']);
                        unset($sharedReport['shareUid_owner']);
                        $sharedReport['isShare'] = self::SHARE_TYPE_USER;
                        $reports[] = $sharedReport;
                    }
                }
            }
        }

        foreach ($reports as $report) {
            // if it is a shared group, get all reports below
            if ((int)$report['type'] === ReportService::REPORT_TYPE_GROUP) {
                $subreport = $this->ReportMapper->getReportsByGroup($report['id']);
                $subreport = array_map(function($report) {
                    $report['isShare'] = self::SHARE_TYPE_GROUP;
                    return $report;
                }, $subreport);

                $reports = array_merge($reports, $subreport);
            }
        }
        return $reports;
    }

    /**
     * get metadata of a report, shared with current user
     * used to check if user is allowed to execute current report
     *
     * @param $reportId
     * @return array
     * @throws Exception
     */
    public function getSharedReport($reportId)
    {
        $sharedReport = $this->getSharedReports();
        if (in_array($reportId, array_column($sharedReport, "id"))) {
            $key = array_search($reportId, array_column($sharedReport, 'id'));
            return $sharedReport[$key];
        } else {
            return [];
        }
    }

    /**
     * Delete an own share (sharee or receiver)
     *
     * @param $shareId
     * @return bool
     * @throws Exception
     */
    public function delete($shareId)
    {
        $this->ShareMapper->deleteShare($shareId);
        return true;
    }

    /**
     * delete all shares for a report
     *
     * @param $reportId
     * @return bool
     */
    public function deleteShareByReport($reportId)
    {
        return $this->ShareMapper->deleteShareByReport($reportId);
    }

    /**
     * delete all shares when a share-receiving-user is deleted
     *
     * @param $userId
     * @return bool
     */
    public function deleteByUser($userId)
    {
        return $this->ShareMapper->deleteByUser($userId);
    }

    /**
     * update/set share password
     *
     * @param $shareId
     * @param string|null $password
     * @param string|null $canEdit
     * @param string|null $domain
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
            $canEdit === true ? $canEdit = \OCP\Constants::PERMISSION_UPDATE : $canEdit = \OCP\Constants::PERMISSION_READ;
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