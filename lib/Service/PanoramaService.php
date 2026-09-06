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
use OCP\Files\IRootFolder;
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
	private $rootFolder;

	const REPORT_TYPE_GROUP = 0;
	private const PICTURE_MIME_TYPES = ['image/png', 'image/x-png', 'image/jpeg'];

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
		IRootFolder $rootFolder,
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
		$this->rootFolder = $rootFolder;
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
		$keysToKeep = array('id', 'name', 'dataset', 'favorite', 'parent', 'type', 'pages', 'filters', 'isShare', 'shareId', 'permissions');

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
		if ($ownReport) {
			$ownReport['permissions'] = \OCP\Constants::PERMISSION_UPDATE;
		}
		return $ownReport;
	}

	/**
	 * Resolve a selected panorama picture to its file id.
	 *
	 * @param string $path
	 * @return int
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function resolvePictureFile(string $path): int {
		$path = ltrim($path, '/');
		if ($path === '') {
			throw new \InvalidArgumentException('Picture path must not be empty');
		}

		$file = $this->rootFolder->getUserFolder($this->userId)->get($path);
		if (!in_array($file->getMimeType(), self::PICTURE_MIME_TYPES, true)) {
			throw new \InvalidArgumentException('Selected node is not a supported picture');
		}

		return (int)$file->getId();
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
		if (!$this->isValidParent($parent)) {
			return 0;
		}
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
    public function update(int $id, $name, int $type, int $parent, $pages, $filters = null) {
		if (!$this->isOwn($id) || !$this->isValidParent($parent, $id)) {
			return false;
		}
		$normalizedPages = $this->normalizePages($pages);
		if ($normalizedPages === null) {
			return false;
		}
        if ($filters !== null) {
            try {
                $filters = json_encode(PanoramaFilterService::normalize($filters, $normalizedPages));
            } catch (\InvalidArgumentException $e) {
                return false;
            }
        }
        return $this->PanoramaMapper->update($id, $name, $type, $parent, json_encode($normalizedPages), $filters);
    }

    public function createGroup(int $parent = 0): int {
		if (!$this->isValidParent($parent)) {
			return 0;
		}
        return $this->PanoramaMapper->create($this->l10n->t('New'), self::REPORT_TYPE_GROUP, $parent, '[]');
    }

    public function updateGroup(int $panoramaId, int $groupId): bool {
		if (!$this->isOwn($panoramaId) || !$this->isValidParent($groupId, $panoramaId)) {
			return false;
		}
        return $this->PanoramaMapper->updateGroup($panoramaId, $groupId);
    }

    /**
     * rename panorama
     *
     * @param int $panoramaId
     * @param string $name
     * @return bool
     */
    public function rename(int $panoramaId, string $name): bool {
        if ($this->isOwn($panoramaId)) {
            return $this->PanoramaMapper->updateName($panoramaId, $name);
        }
        return false;
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
		return $this->PanoramaMapper->deleteByUser($userId);
	}

	private function isValidParent(int $parentId, ?int $itemId = null): bool {
		if ($parentId === 0) {
			return true;
		}
		if ($itemId !== null && $parentId === $itemId) {
			return false;
		}

		$visited = [];
		$currentId = $parentId;
		while ($currentId !== 0) {
			if (isset($visited[$currentId]) || ($itemId !== null && $currentId === $itemId)) {
				return false;
			}
			$visited[$currentId] = true;
			$parent = $this->PanoramaMapper->readOwn($currentId);
			if (empty($parent) || (int)$parent['type'] !== self::REPORT_TYPE_GROUP) {
				return false;
			}
			$currentId = (int)$parent['parent'];
		}
		return true;
	}

	private function normalizePages(mixed $pages): ?array {
		if (is_string($pages)) {
			$pages = json_decode($pages, true);
		}
		if (!is_array($pages)) {
			return null;
		}
		foreach ($pages as &$page) {
			if (!is_array($page)) {
				return null;
			}
			$layoutId = filter_var($page['layoutId'] ?? 0, FILTER_VALIDATE_INT);
			if ($layoutId === false || $layoutId < 0 || $layoutId > 6) {
				return null;
			}
			$page['layoutId'] = $layoutId;
			unset($page['layout']);
		}
		unset($page);
		return $pages;
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
				$this->removeFavoriteTag('analyticsPanorama', $favorite);
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
			$return = $this->addFavoriteTag('analyticsPanorama', $panoramaId);
		} else {
			$return = $this->removeFavoriteTag('analyticsPanorama', $panoramaId);
		}
		return $return;
	}

	private function addFavoriteTag(string $scope, int $objectId): bool {
		try {
			return $this->tagManager->load($scope)->addToFavorites($objectId);
		} catch (\Throwable $exception) {
			// Nextcloud writes the favorite relation before its special favorite-tag
			// follow-up tries to resolve the object id as a file node. Analytics uses
			// app-local ids here, so that lookup can throw even though the favorite
			// relation was already stored successfully.
			return true;
		}
	}

	private function removeFavoriteTag(string $scope, int $objectId): bool {
		try {
			return $this->tagManager->load($scope)->removeFromFavorites($objectId);
		} catch (\Throwable $exception) {
			// Nextcloud removes the stored favorite relation before its special
			// favorite-tag cleanup tries to resolve the object id as a file node.
			// Analytics ids are not file node ids, so the follow-up can throw after
			// the unfavorite already succeeded from the app's point of view.
			return true;
		}
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
