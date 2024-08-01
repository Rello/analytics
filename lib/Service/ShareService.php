<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

class ShareService {
	const SHARE_TYPE_USER = 0;
	const SHARE_TYPE_GROUP = 1;
	const SHARE_TYPE_USERGROUP = 2; // obsolete
	const SHARE_TYPE_LINK = 3;
	const SHARE_TYPE_ROOM = 10;
	const SHARE_ITEM_TYPE_REPORT = 'report';
	const SHARE_ITEM_TYPE_PANORAMA = 'panorama';
	const SHARE_ITEM_TYPE_DATASET = 'dataset';

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
		IUserSession    $userSession,
		LoggerInterface $logger,
		ShareMapper     $ShareMapper,
		ReportMapper    $ReportMapper,
		ActivityManager $ActivityManager,
		IGroupManager   $groupManager,
		ISecureRandom   $secureRandom,
		IUserManager    $userManager,
		VariableService $VariableService
	) {
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
	 * @param $item_type
	 * @param $item_source
	 * @param $type
	 * @param $user
	 * @return bool
	 * @throws Exception
	 */
	public function create($item_type, $item_source, $type, $user) {
		$token = null;
		if ((int)$type === self::SHARE_TYPE_LINK) {
			$token = $this->generateToken();
		}
		$this->ShareMapper->createShare($item_type, $item_source, $type, $user, $token);

		switch ($item_type) {
			case ShareService::SHARE_ITEM_TYPE_REPORT:
				$this->ActivityManager->triggerEvent($item_source, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_SHARE);
				break;
			case ShareService::SHARE_ITEM_TYPE_DATASET:
				$this->ActivityManager->triggerEvent($item_source, ActivityManager::OBJECT_DATASET, ActivityManager::SUBJECT_DATASET_SHARE);
				break;
			case ShareService::SHARE_ITEM_TYPE_PANORAMA:
				$this->ActivityManager->triggerEvent($item_source, ActivityManager::OBJECT_PANORAMA, ActivityManager::SUBJECT_PANORAMA_SHARE);
				break;
		}
		return true;
	}

	/**
	 * get all shares for a report or panorama
	 *
	 * @param $item_source
	 * @param $item_type
	 * @return array
	 * @throws Exception
	 */
	public function read($item_type, $item_source) {

		$shares = $this->ShareMapper->getShares($item_type, $item_source);
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
	public function getReportByToken($token) {
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
	public function verifyPassword($password, $sharePassword) {
		return password_verify($password, $sharePassword);
	}

	/**
	 * get all shares for an item (report or panorama)
	 *
	 * @param $item_type
	 * @return array|false
	 * @throws Exception
	 */
	public function getSharedItems($item_type) {
		if ($item_type === self::SHARE_ITEM_TYPE_PANORAMA) {
			$sharedReports = $this->ShareMapper->getAllSharedPanoramas();
		} else {
			$sharedReports = $this->ShareMapper->getAllSharedReports();
		}
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
				$this->logger->debug('Shareservice: is user share; check against current user: ' . $this->userSession->getUser()
																													 ->getUID());
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

		// no groupings of shares exist for panoramas
		if ($item_type !== self::SHARE_ITEM_TYPE_PANORAMA) {
			foreach ($reports as $report) {
				// if it is a shared group, get all reports below
				if ((int)$report['type'] === ReportService::REPORT_TYPE_GROUP) {
					$subreport = $this->ReportMapper->getReportsByGroup($report['id']);
					$subreport = array_map(function ($report) {
						$report['isShare'] = self::SHARE_TYPE_GROUP;
						return $report;
					}, $subreport);

					$reports = array_merge($reports, $subreport);
				}
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
	public function getSharedReport($reportId) {
		$sharedReport = $this->getSharedItems(self::SHARE_ITEM_TYPE_REPORT);
		if (in_array($reportId, array_column($sharedReport, "id"))) {
			$key = array_search($reportId, array_column($sharedReport, 'id'));
			return $sharedReport[$key];
		} else {
			return [];
		}
	}

	/**
	 * get metadata of a report, shared with current user as part of a panorama
	 * used to check if user is allowed to execute current report
	 *
	 * @param $reportId
	 * @return array
	 * @throws Exception
	 */
	public function getSharedPanoramaReport($reportId) {
		$foundReportId = 0;
		$panoramaOwner = null;
		$sharedPanoramas = $this->getSharedItems(self::SHARE_ITEM_TYPE_PANORAMA);
		foreach ($sharedPanoramas as $sharedPanorama) {
			$panoramaOwner = $sharedPanorama['user_id'];
			$pages = json_decode($sharedPanorama['pages'], true);
			foreach ($pages as $page) {
				$reports = $page['reports'];
				foreach ($reports as $report) {
					// only use report type 0 = report; not text or image
					if ($report['type'] === 0 && $report['value'] === $reportId) {
						$foundReportId = $reportId;
						break 3;
					}
				}
			}
		}

		if ($foundReportId !== 0) {
			$report = $this->ReportMapper->read($foundReportId);
			// check against the original owner of the panorama
			// This is needed to prevent getting other reports by modifying the panorama properties
			if ($report['user_id'] === $panoramaOwner) {
				return $report;
			} else {
				return [];
			}
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
	public function delete($shareId) {
		$this->ShareMapper->deleteShare($shareId);
		return true;
	}

	/**
	 * delete all shares for a report or panorama
	 *
	 * @param $item_type
	 * @param $item_source
	 * @return bool
	 * @throws Exception
	 */
	public function deleteSharesByItem($item_type, $item_source) {
		return $this->ShareMapper->deleteSharesByItem($item_type, $item_source);
	}

	/**
	 * delete all shares when a share-receiving-user is deleted
	 *
	 * @param $userId
	 * @return bool
	 */
	public function deleteByUser($userId) {
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
	public function update($shareId, $password = null, $canEdit = null, $domain = null) {
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
	private function generateToken() {
		$token = $this->secureRandom->generate(15, ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_DIGITS);
		return $token;
	}
}