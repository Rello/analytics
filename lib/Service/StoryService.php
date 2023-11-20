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
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Db\StoryMapper;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\ITagManager;
use OCP\IConfig;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\IL10N;

class StoryService
{
    /** @var IConfig */
    protected $config;
    private $userId;
    private $logger;
    private $tagManager;
    private $ShareService;
    private $DatasetService;
    private $StorageMapper;
    private $StoryMapper;
    private $ThresholdMapper;
    private $DataloadMapper;
    private $ActivityManager;
    private $rootFolder;
    private $VariableService;
    private $l10n;

    const REPORT_TYPE_GROUP = 0;

    public function __construct(
        $userId,
        IL10N $l10n,
        LoggerInterface $logger,
        ITagManager $tagManager,
        ShareService $ShareService,
        DatasetService $DatasetService,
        StoryMapper $StoryMapper,
        ActivityManager $ActivityManager,
        IRootFolder $rootFolder,
        IConfig $config,
        VariableService $VariableService
    )
    {
        $this->userId = $userId;
        $this->logger = $logger;
        $this->tagManager = $tagManager;
        $this->ShareService = $ShareService;
        $this->DatasetService = $DatasetService;
        $this->StoryMapper = $StoryMapper;
        $this->ActivityManager = $ActivityManager;
        $this->rootFolder = $rootFolder;
        $this->VariableService = $VariableService;
        $this->config = $config;
        $this->l10n = $l10n;
    }

    /**
     * get all reports
     *
     * @return array
     * @throws PreConditionNotMetException
     * @throws Exception
     */
    public function index(): array
    {
        $ownReports = $this->StoryMapper->index();
        //$sharedReports = $this->ShareService->getSharedReports();
        $sharedReports = null;
        $keysToKeep = array('id', 'name', 'dataset', 'favorite', 'parent', 'type', 'isShare', 'shareId');

        // get shared reports and remove duplicates
        foreach ($sharedReports as $sharedReport) {
            if (!array_search($sharedReport['id'], array_column($ownReports, 'id'))) {
                // just keep the necessary fields
                $ownReports[] = array_intersect_key($sharedReport, array_flip($keysToKeep));;
            }
        }
        return $ownReports;
    }

    /**
     * get own report details
     *
     * @param int $storyId
     * @return array
     * @throws Exception
     */
    public function read(int $storyId)
    {
        $ownReport = $this->StoryMapper->readOwn($storyId);
        return $ownReport;
    }

    /**
     * check if own report
     *
     * @param int $storyId
     * @return bool
     */
    public function isOwn(int $storyId)
    {
        $ownReport = $this->StoryMapper->readOwn($storyId);
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
    public function create($name, int $type, int $parent, $pages): int
    {
        $reportId = $this->StoryMapper->create($name, $type, $parent, $pages);
        //$this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_ADD);
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
    public function update(int $id, $name, int $type, int $parent, $pages)
    {
        return $this->StoryMapper->update($id, $name, $type, $parent, $pages);
    }

    /**
     * Delete Dataset and all depending objects
     *
     * @param int $reportId
     * @return string
     * @throws Exception
     */
    public function delete(int $reportId)
    {
        $this->StoryMapper->delete($reportId);
        return 'true';
    }

    /**
     * get dataset by user
     *
     * @param string $userId
     * @return array|bool
     * @throws Exception
     */
    public function deleteByUser(string $userId)
    {
        $reports = $this->ReportMapper->indexByUser($userId);
        foreach ($reports as $report) {
            $this->ShareService->deleteShareByReport($report['id']);
            $this->ThresholdMapper->deleteThresholdByReport($report['id']);
            $this->setFavorite($report['id'], 'false');
            $this->ReportMapper->delete($report['id']);
        }
        return true;
    }
    /**
     * search for reports
     *
     * @param string $searchString
     * @return array
     */
    public function search(string $searchString)
    {
        return $this->StoryMapper->search($searchString);
    }
}
