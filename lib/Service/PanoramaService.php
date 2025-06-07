<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Activity\ActivityManager;
use OCA\Analytics\Db\PanoramaMapper;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\DB\Exception;
use OCP\ITagManager;
use OCP\IConfig;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\IL10N;

class PanoramaService {
	/** @var IConfig */
	protected $config;
	private $userId;
	private $logger;
	private $tagManager;
	private $ShareService;
	private $PanoramaMapper;
	private $VariableService;
	private $l10n;
	private $ActivityManager;

	const REPORT_TYPE_GROUP = 0;

	public function __construct(
		$userId,
		IL10N $l10n,
		LoggerInterface $logger,
		ITagManager $tagManager,
		ShareService $ShareService,
		PanoramaMapper $PanoramaMapper,
		IConfig $config,
		VariableService $VariableService,
		ActivityManager $ActivityManager,
	) {
		$this->userId = $userId;
		$this->logger = $logger;
		$this->tagManager = $tagManager;
		$this->ShareService = $ShareService;
		$this->PanoramaMapper = $PanoramaMapper;
		$this->VariableService = $VariableService;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->ActivityManager = $ActivityManager;
	}

	/**
	 * get all reports
	 *
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws Exception
	 */
	public function index(): array {
		$ownPanorama = $this->PanoramaMapper->index();
		// Set permissions for all own panoramas
		foreach ($ownPanorama as &$panorama) {
			$panorama['permissions'] = \OCP\Constants::PERMISSION_UPDATE;
		}
		unset($panorama);

		$sharedPanoramas = $this->ShareService->getSharedItems(ShareService::SHARE_ITEM_TYPE_PANORAMA);
		$keysToKeep = array('id', 'name', 'dataset', 'favorite', 'parent', 'type', 'pages', 'isShare', 'shareId', 'permissions');

		// get shared reports and remove duplicates
		foreach ($sharedPanoramas as $sharedPanorama) {
			if (!array_search($sharedPanorama['id'], array_column($ownPanorama, 'id'))) {
				// ToDo: panoramas do not have an edit logic. to be added later
				$sharedPanorama['permissions'] = \OCP\Constants::PERMISSION_READ;
				// just keep the necessary fields
				$ownPanorama[] = array_intersect_key($sharedPanorama, array_flip($keysToKeep));;
			}
		}

		$favorites = $this->tagManager->load('analyticsPanorama')->getFavorites();
		foreach ($ownPanorama as &$ownReport) {
			$hasTag = 0;
			if (is_array($favorites) and in_array($ownReport['id'], $favorites)) {
				$hasTag = 1;
			}
			$ownReport['favorite'] = $hasTag;
			$ownReport['item_type'] = ShareService::SHARE_ITEM_TYPE_PANORAMA;
			$ownReport = $this->VariableService->replaceTextVariables($ownReport);
		}

		return $ownPanorama;
	}

	/**
	 * get own report details
	 *
	 * @param int $panoramaId
	 * @return array
	 * @throws Exception
	 */
	public function read(int $panoramaId) {
		$ownReport = $this->PanoramaMapper->readOwn($panoramaId);
		return $ownReport;
	}

	/**
	 * check if own report
	 *
	 * @param int $panoramaId
	 * @return bool
	 */
	public function isOwn(int $panoramaId) {
		$ownReport = $this->PanoramaMapper->readOwn($panoramaId);
		if (!empty($ownReport)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * create new blank report
	 *
	 * @param $name
	 * @param int $type
	 * @param int $parent
	 * @param $pages
	 * @return int
	 * @throws Exception
	 */
	public function create(int $type, int $parent): int {
		$reportId = $this->PanoramaMapper->create($this->l10n->t('New'), $type, $parent, '[]');
		$this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_PANORAMA, ActivityManager::SUBJECT_PANORAMA_ADD);
		return $reportId;
	}

	/**
	 * update report details
	 *
	 * @param int $id
	 * @param $name
	 * @param $subheader
	 * @param int $type
	 * @param int $parent
	 * @param $pages
	 * @return bool
	 * @throws Exception
	 */
	public function update(int $id, $name, int $type, int $parent, $pages) {
		return $this->PanoramaMapper->update($id, $name, $type, $parent, $pages);
	}

	/**
	 * Delete Dataset and all depending objects
	 *
	 * @param int $reportId
	 * @return string
	 * @throws Exception
	 */
	public function delete(int $reportId) {
		$this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_PANORAMA, ActivityManager::SUBJECT_PANORAMA_DELETE);
		$this->PanoramaMapper->delete($reportId);
		return 'true';
	}

	/**
	 * get dataset by user
	 *
	 * @param string $userId
	 * @return array|bool
	 * @throws Exception
	 */
	public function deleteByUser(string $userId) {
		$panoramas = $this->PanoramaMapper->indexByUser($userId);
		foreach ($panoramas as $panorama) {
			$this->ShareService->deleteSharesByItem(ShareService::SHARE_ITEM_TYPE_PANORAMA, $panorama['id']);
			$this->setFavorite($panorama['id'], 'false');
		}
		return true;
	}

	/**
	 * get own reports which are marked as favorites
	 *
	 * @return array|bool
	 * @throws Exception
	 */
	public function getOwnFavoriteReports() {
		$ownReports = $this->PanoramaMapper->index();
		//$sharedReports = $this->ShareService->getSharedItems(ShareService::SHARE_ITEM_TYPE_REPORT);
		$sharedReports = [];
		$favorites = $this->tagManager->load('analyticsPanorama')->getFavorites();

		// remove the favorite if the report is not existing anymore
		foreach ($favorites as $favorite) {
			if (!in_array($favorite, array_column($ownReports, 'id')) && !in_array($favorite, array_column($sharedReports, 'id'))) {
				unset($favorites[$favorite]);
				$this->tagManager->load('analyticsPanorama')->removeFromFavorites($favorite);
			}
		}
		return $favorites;
	}

	/**
	 * set/remove the favorite flag for a report
	 *
	 * @param int $panoramaId
	 * @param string $favorite
	 * @return bool
	 */
	public function setFavorite(int $panoramaId, string $favorite) {
		if ($favorite === 'true') {
			$return = $this->tagManager->load('analyticsPanorama')->addToFavorites($panoramaId);
		} else {
			$return = $this->tagManager->load('analyticsPanorama')->removeFromFavorites($panoramaId);
		}
		return $return;
	}

	/**
	 * search for reports
	 *
	 * @param string $searchString
	 * @return array
	 */
	public function search(string $searchString) {
		return $this->PanoramaMapper->search($searchString);
	}
}
