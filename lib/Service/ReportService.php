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
use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\IRootFolder;
use OCP\ITagManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ReportService
{
    /** @var IConfig */
    protected $config;
    private $userId;
    private $logger;
    private $tagManager;
    private $ShareService;
    private $DatasetService;
    private $StorageMapper;
    private $ReportMapper;
    private $ThresholdMapper;
    private $DataloadMapper;
    private $ActivityManager;
    private $rootFolder;
    private $VariableService;

    public function __construct(
        $userId,
        LoggerInterface $logger,
        ITagManager $tagManager,
        ShareService $ShareService,
        DatasetService $DatasetService,
        StorageMapper $StorageMapper,
        ReportMapper $ReportMapper,
        ThresholdMapper $ThresholdMapper,
        DataloadMapper $DataloadMapper,
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
        $this->ThresholdMapper = $ThresholdMapper;
        $this->StorageMapper = $StorageMapper;
        $this->ReportMapper = $ReportMapper;
        $this->DataloadMapper = $DataloadMapper;
        $this->ActivityManager = $ActivityManager;
        $this->rootFolder = $rootFolder;
        $this->VariableService = $VariableService;
        $this->config = $config;
    }

    /**
     * get all reports
     *
     * @return DataResponse
     * @throws \OCP\PreConditionNotMetException
     */
    public function index()
    {
        $ownReports = $this->ReportMapper->index();

        // get dataload indicators for icons shown in the advanced screen
        $dataloads = $this->DataloadMapper->getAllDataloadMetadata();
        foreach ($dataloads as $dataload) {
            $key = array_search($dataload['dataset'], array_column($ownReports, 'dataset'));
            if ($key !== '') {
                if ($dataload['schedules'] !== '' and $dataload['schedules'] !== null) {
                    $dataload['schedules'] = 1;
                } else {
                    $dataload['schedules'] = 0;
                }
                $ownReports[$key]['dataloads'] = $dataload['dataloads'];
                $ownReports[$key]['schedules'] = $dataload['schedules'];
            }
        }

        // get shared reports and remove doublicates
        $sharedReports = $this->ShareService->getSharedReports();
        foreach ($sharedReports as $sharedReport) {
            if (!array_search($sharedReport['id'], array_column($ownReports, 'id'))) {
                $sharedReport['type'] = '99';
                $sharedReport['parrent'] = '0';
                array_push($ownReports, $sharedReport);
            }
        }

        $favoriteMigration = $this->config->getUserValue($this->userId, 'analytics', 'favMig', '0');
        if ($favoriteMigration === '0') {
            $this->logger->info('Favorite migration being performed');
            $this->favoriteMigration($ownReports);
            $this->config->setUserValue($this->userId, 'analytics', 'favMig', 3.7);
        }

        $favorites = $this->tagManager->load('analytics')->getFavorites();
        foreach ($ownReports as &$ownReport) {
            $hasTag = 0;
            if (is_array($favorites) and in_array($ownReport['id'], $favorites)) {
                $hasTag = 1;
            }
            $ownReport['favorite'] = $hasTag;
            $ownReport = $this->VariableService->replaceTextVariables($ownReport);
        }

        return $ownReports;
    }

    /**
     * migrate old favorite ids
     *
     * @param $ownReports
     * @return bool
     */
    private function favoriteMigration($ownReports) {
        $favorites = $this->tagManager->load('analytics')->getFavorites();
        foreach ($favorites as $favorite) {
            $key = array_search($favorite, array_column($ownReports, 'dataset'));
            if ($key) {
                $this->logger->info('Favorite was migrated from '. $ownReports[$key]['dataset'] . ' to new report ' . $ownReports[$key]['id']);
                $this->tagManager->load('analytics')->removeFromFavorites($ownReports[$key]['dataset']);
                $this->tagManager->load('analytics')->addToFavorites($ownReports[$key]['id']);
            }
        }
        return true;
    }

    /**
     * get own report details
     *
     * @param int $reportId
     * @param string|null $user_id
     * @return array
     */
    public function read(int $reportId, string $user_id = null)
    {
        $ownReport = $this->ReportMapper->read($reportId, $user_id);
        if (!empty($ownReport)) {
            $ownReport['permissions'] = \OCP\Constants::PERMISSION_UPDATE;
            $ownReport = $this->VariableService->replaceTextVariables($ownReport);

            if ($ownReport['type'] === DatasourceController::DATASET_TYPE_INTERNAL_DB && $ownReport['dataset'] !== 0) {
                $dataset = $this->DatasetService->read($ownReport['dataset'],  $user_id);
                $ownReport['dimension1'] = $dataset['dimension1'];
                $ownReport['dimension2'] = $dataset['dimension2'];
                $ownReport['value'] = $dataset['value'];
            }

        }
        return $ownReport;
    }

    /**
     * get own reports which are marked as favorites
     *
     * @return array|bool
     */
    public function getOwnFavoriteReports()
    {
        $ownReports = $this->ReportMapper->index();
        $favorites = $this->tagManager->load('analytics')->getFavorites();
        $sharedReports = $this->ShareService->getSharedReports();

        foreach ($favorites as $favorite) {
            if (array_search($favorite, array_column($ownReports, 'id')) === false
                && array_search($favorite, array_column($sharedReports, 'id')) === false) {
                unset($favorites[$favorite]);
                $this->tagManager->load('analytics')->removeFromFavorites($favorite);
            }
        }

        return $favorites;
    }

    /**
     * create new blank report
     *
     * @return int
     */
    public function create($name, $subheader, $parent, $type, int $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value): int
    {
        if ($type === DatasourceController::DATASET_TYPE_GROUP) {
            $parent = 0;
        }
        if ($type === DatasourceController::DATASET_TYPE_INTERNAL_DB && $dataset === 0) { // New dataset
            $dataset = $this->DatasetService->create($name, $dimension1, $dimension2, $value);
        }
        $reportId = $this->ReportMapper->create($name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value);
        $this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_ADD);
        return $reportId;
    }

    /**
     * copy an existing report with the current navigation status
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return int
     */
    public function createCopy(int $reportId, $chartoptions, $dataoptions, $filteroptions)
    {

        $newId = $this->ReportMapper->create();
        $template = $this->ReportMapper->read($reportId);
        $this->ReportMapper->update($newId,
            $template['name'] . ' copy',
            $template['subheader'],
            $template['parent'],
            $template['type'],
            $template['dataset'],
            $template['link'],
            $template['visualization'],
            $template['chart'],
            $template['chartoptions'],
            $template['dataoptions'],
            $template['dimension1'],
            $template['dimension2'],
            $template['value']);
        $this->ReportMapper->updateOptions($newId, $chartoptions, $dataoptions, $filteroptions);
        return $newId;
    }

    /**
     * create new report
     *
     * @param string $file
     * @return int
     */
    public function createFromFile($file = '')
    {
        $this->ActivityManager->triggerEvent(0, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_ADD);
        $reportId = $this->ReportMapper->create();

        if ($file !== '') {
            $name = explode('.', end(explode('/', $file)))[0];
            $subheader = $file;
            $parent = 0;
            $type = DatasourceController::DATASET_TYPE_FILE;
            $link = $file;
            $visualization = 'table';
            $chart = 'line';
            $this->update($reportId, $name, $subheader, $parent, $type, $link, $visualization, $chart, '', '');
        }
        return $reportId;
    }

    /**
     * update report details
     *
     * @param int $reportId
     * @param $name
     * @param $subheader
     * @param int $parent
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $chartoptions
     * @param $dataoptions
     * @param null $dimension1
     * @param null $dimension2
     * @param null $value
     * @return bool
     * @throws \OCP\DB\Exception
     */
    public function update(int $reportId, $name, $subheader, int $parent, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1 = null, $dimension2 = null, $value = null)
    {
        return $this->ReportMapper->update($reportId, $name, $subheader, $parent, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value);
    }

    /**
     * set/remove the favorite flag for a report
     *
     * @param int $reportId
     * @param string $favorite
     * @return bool
     */
    public function setFavorite(int $reportId, string $favorite)
    {
        if ($favorite === 'true') {
            $return = $this->tagManager->load('analytics')->addToFavorites($reportId);
        } else {
            $return = $this->tagManager->load('analytics')->removeFromFavorites($reportId);
        }
        return $return;
    }

    /**
     * Import Report from File
     *
     * @param string|null $path
     * @param string|null $raw
     * @return int
     * @throws \OCP\Files\NotFoundException
     * @throws \OCP\Files\NotPermittedException
     */
    public function import(string $path = null, string $raw = null)
    {
        if ($path !== '') {
            $file = $this->rootFolder->getUserFolder($this->userId)->get($path);
            $data = $file->getContent();
        } else if ($raw !== null) {
            $data = $raw;
        } else {
            return false;
        }
        $data = json_decode($data, true);

        $dataset = $data['dataset'];
        isset($dataset['name']) ? $name = $dataset['name'] : $name = '';
        isset($dataset['subheader']) ? $subheader = $dataset['subheader'] : $subheader = '';
        $parent = 0;
        isset($dataset['type']) ? $type = $dataset['type'] : $type = null;
        isset($dataset['link']) ? $link = $dataset['link'] : $link = null;
        isset($dataset['visualization']) ? $visualization = $dataset['visualization'] : $visualization = null;
        isset($dataset['chart']) ? $chart = $dataset['chart'] : $chart = null;
        isset($dataset['chartoptions']) ? $chartoptions = $dataset['chartoptions'] : $chartoptions = null;
        isset($dataset['dataoptions']) ? $dataoptions = $dataset['dataoptions'] : $dataoptions = null;
        isset($dataset['filteroptions']) ? $filteroptions = $dataset['filteroptions'] : $filteroptions = null;
        isset($dataset['dimension1']) ? $dimension1 = $dataset['dimension1'] : $dimension1 = null;
        isset($dataset['dimension2']) ? $dimension2 = $dataset['dimension2'] : $dimension2 = null;
        isset($dataset['value']) ? $value = $dataset['value'] : $value = null;

        $reportId = $this->ReportMapper->create();
        $this->ReportMapper->update($reportId, $name, $subheader, $parent, $type, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value, $filteroptions);

        foreach ($data['dataload'] as $dataload) {
            isset($dataload['datasource']) ? $datasource = $dataload['datasource'] : $datasource = null;
            isset($dataload['name']) ? $name = $dataload['name'] : $name = null;
            isset($dataload['option']) ? $option = $dataload['option'] : $option = null;
            $schedule = null;

            /**todo**/
            $dataloadId = $this->DataloadMapper->create($reportId, $datasource);
            $this->DataloadMapper->update($dataloadId, $name, $option, $schedule);
        }

        foreach ($data['threshold'] as $threshold) {
            isset($threshold['dimension1']) ? $dimension1 = $threshold['dimension1'] : $dimension1 = null;
            isset($threshold['value']) ? $value = $threshold['value'] : $value = null;
            isset($threshold['option']) ? $option = $threshold['option'] : $option = null;
            isset($threshold['severity']) ? $severity = $threshold['severity'] : $severity = null;
            $this->ThresholdService->create($reportId, $dimension1, $option, $value, $severity);
        }

        foreach ($data['data'] as $dData) {
            isset($dData[0]) ? $dimension1 = $dData[0] : $dimension1 = null;
            isset($dData[1]) ? $dimension2 = $dData[1] : $dimension2 = null;
            isset($dData[2]) ? $value = $dData[2] : $value = null;
            $this->StorageMapper->create($reportId, $dimension1, $dimension2, $value);
        }

        if (isset($data['favorite'])) {
            $this->setFavorite($reportId, $data['favorite']);
        }

        return $reportId;
    }

    /**
     * Export Report
     *
     * @param int $reportId
     * @return DataDownloadResponse
     */
    public function export(int $reportId)
    {
        /**todo**/
        $result = array();
        $result['dataset'] = $this->ReportMapper->read($reportId);
        $result['dataload'] = $this->DataloadMapper->read($datasetId);
        $result['threshold'] = $this->ThresholdService->read($reportId);
        $result['favorite'] = '';

        if ($result['dataset']['type'] === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
            $result['data'] = $this->StorageMapper->read($datasetId);
        }

        unset($result['dataset']['id'], $result['dataset']['user_id'], $result['dataset']['user_id'], $result['dataset']['parent']);
        $data = json_encode($result);
        return new DataDownloadResponse($data, $result['dataset']['name'] . '.export.txt', 'text/plain; charset=utf-8');
    }

    /**
     * Delete Dataset and all depending objects
     *
     * @param int $reportId
     * @return bool
     */
    public function delete(int $reportId): bool
    {
        $metadata = $this->read($reportId);
        $this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_DELETE);
        $this->ShareService->deleteShareByReport($reportId);
        $this->ThresholdMapper->deleteThresholdByReport($reportId);
        $this->setFavorite($reportId, 'false');
        $this->ReportMapper->delete($reportId);

        if (empty($this->reportsForDataset($metadata['dataset'])) && $metadata['type'] === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Update report options
     *
     * @param int $reportId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return bool
     */
    public function updateOptions(int $reportId, $chartoptions, $dataoptions, $filteroptions)
    {
        return $this->ReportMapper->updateOptions($reportId, $chartoptions, $dataoptions, $filteroptions);
    }

    /**
     * get report refresh options
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $refresh
     * @return bool
     */
    public function updateRefresh(int $reportId, $refresh)
    {
        return $this->ReportMapper->updateRefresh($reportId, $refresh);
    }

    /**
     * search for reports
     *
     * @param string $searchString
     * @return array
     */
    public function search(string $searchString)
    {
        return $this->ReportMapper->search($searchString);
    }

    /**
     * @throws \OCP\DB\Exception
     */
    public function reportsForDataset($datasetId) {
        return $this->ReportMapper->reportsForDataset($datasetId);
    }
}