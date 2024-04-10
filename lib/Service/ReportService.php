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
use OCA\Analytics\Db\DataloadMapper;
use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\StorageMapper;
use OCA\Analytics\Db\ThresholdMapper;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\ITagManager;
use OCP\IConfig;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\IL10N;

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
    private $l10n;

    const REPORT_TYPE_GROUP = 0;

    public function __construct(
        $userId,
        IL10N $l10n,
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
        $this->l10n = $l10n;
    }

    /**
     * get all reports
     *
     * @return array
     * @throws PreConditionNotMetException
     */
    public function index(): array
    {
        $ownReports = $this->ReportMapper->index();
        $sharedReports = $this->ShareService->getSharedReports();
        $keysToKeep = array('id', 'name', 'dataset', 'favorite', 'parent', 'type', 'isShare', 'shareId');

        // get shared reports and remove duplicates
        foreach ($sharedReports as $sharedReport) {
            if (!array_search($sharedReport['id'], array_column($ownReports, 'id'))) {
                // just keep the necessary fields
                $ownReports[] = array_intersect_key($sharedReport, array_flip($keysToKeep));;
            }
        }
        if (count($ownReports) === 0) return $ownReports;

        // get data load indicators for icons shown in the advanced screen
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
     * get own report details
     *
     * @param int $reportId
     * @return array
     * @throws Exception
     */
    public function read(int $reportId, $replace = true)
    {
        $ownReport = $this->ReportMapper->readOwn($reportId);
        if (!empty($ownReport)) {
            $ownReport['permissions'] = \OCP\Constants::PERMISSION_UPDATE;
            if ($replace) $ownReport = $this->VariableService->replaceTextVariables($ownReport);

            if ($ownReport['type'] === DatasourceController::DATASET_TYPE_INTERNAL_DB && $ownReport['dataset'] !== 0) {
                $dataset = $this->DatasetService->readOwn($ownReport['dataset']);
                $ownReport['dimension1'] = $dataset['dimension1'];
                $ownReport['dimension2'] = $dataset['dimension2'];
                $ownReport['value'] = $dataset['value'];
            }

        }
        return $ownReport;
    }

    /**
     * check if own report
     *
     * @param int $reportId
     * @return bool
     */
    public function isOwn(int $reportId)
    {
        $ownReport = $this->ReportMapper->readOwn($reportId);
        if (!empty($ownReport)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * create new blank report
     *
     * @return int
     * @throws Exception
     */
    public function create($name, $subheader, $parent, $type, int $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value, $addReport = null): int
    {
        $array = json_decode($link, true);
        if (is_array($array)){
            foreach ($array as $key => $value) {
                $array[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
            }
        }
        $link = json_encode($array);

        if ($type === DatasourceController::DATASET_TYPE_GROUP) {
            $parent = 0;
        }
        if ($type === DatasourceController::DATASET_TYPE_INTERNAL_DB && $dataset === 0) { // New dataset
            $dataset = $this->DatasetService->create($name, $dimension1, $dimension2, $value);
        }
        $reportId = $this->ReportMapper->create($name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value);
        $this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_ADD);

        if ($addReport !== null && $addReport !== '') {
            $this->updateGroup($addReport, $reportId);
        }
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
     * @throws Exception
     */
    public function createCopy(int $reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions)
    {

        $template = $this->ReportMapper->readOwn($reportId);
        $newId = $this->ReportMapper->create(
        // TRANSLATORS Noun
            $template['name'] . ' - ' . $this->l10n->t('copy'),
            $template['subheader'],
            $template['parent'],
            $template['type'],
            $template['dataset'],
            $template['link'],
            $template['visualization'],
            $template['chart'],
            $template['dimension1'],
            $template['dimension2'],
            $template['value']);
        $this->ReportMapper->updateOptions($newId, $chartoptions, $dataoptions, $filteroptions, $tableoptions);
        return $newId;
    }

    /**
     * create new report
     *
     * @param string $file
     * @return int
     */
    public function createFromDataFile($file = '')
    {
        $this->ActivityManager->triggerEvent(0, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_ADD);

        if ($file !== '') {
            $name = explode('.', end(explode('/', $file)))[0];
            $subheader = $file;
            $parent = 0;
            $dataset = 0;
            $type = DatasourceController::DATASET_TYPE_FILE;
            $link = $file;
            $visualization = 'table';
            $chart = 'line';
            $reportId = $this->ReportMapper->create($name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, '', '', '');
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
     * @param $options
     * @param $visualization
     * @param $chart
     * @param $chartoptions
     * @param $dataoptions
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return bool
     */
    public function update(int $reportId, $name, $subheader, int $parent, $options, $visualization, $chart, $chartoptions, $dataoptions, $dimension1 = null, $dimension2 = null, $value = null)
    {
        $array = json_decode($options, true);
        foreach ($array as $key => $value) {
            $array[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
        }
        $options = json_encode($array);

        return $this->ReportMapper->update($reportId, $name, $subheader, $parent, $options, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value);
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
        $metadata = $this->ReportMapper->readOwn($reportId);
        //$this->ActivityManager->triggerEvent($reportId, ActivityManager::OBJECT_REPORT, ActivityManager::SUBJECT_REPORT_DELETE);
        $this->ShareService->deleteShareByReport($reportId);
        $this->ThresholdMapper->deleteThresholdByReport($reportId);
        $this->setFavorite($reportId, 'false');
        $this->ReportMapper->delete($reportId);

        $report = $this->reportsForDataset((int)$metadata['dataset']);
        if (empty($report) && (int)$metadata['type'] === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
            return $metadata['dataset'];
        } else {
            return 'true';
        }
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
     * get own reports which are marked as favorites
     *
     * @return array|bool
     * @throws Exception
     */
    public function getOwnFavoriteReports()
    {
        $ownReports = $this->ReportMapper->index();
        $sharedReports = $this->ShareService->getSharedReports();
        $favorites = $this->tagManager->load('analytics')->getFavorites();

        // remove the favorite if the report is not existing anymore
        foreach ($favorites as $favorite) {
            if (!in_array($favorite, array_column($ownReports, 'id'))
                && !in_array($favorite, array_column($sharedReports, 'id'))) {
                unset($favorites[$favorite]);
                $this->tagManager->load('analytics')->removeFromFavorites($favorite);
            }
        }

        return $favorites;
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
        if ($path !== '' and $path !== null) {
            $file = $this->rootFolder->getUserFolder($this->userId)->get($path);
            $data = $file->getContent();
        } else if ($raw !== null) {
            $data = $raw;
        } else {
            return 0;
        }
        $data = json_decode($data, true);

        $report = $data['report'];
        isset($report['name']) ? $name = $report['name'] : $name = '';
        isset($report['subheader']) ? $subheader = $report['subheader'] : $subheader = '';
        $parent = 0;
        $dataset = 0;
        isset($report['type']) ? $type = (int)$report['type'] : $type = null;
        isset($report['link']) ? $link = $report['link'] : $link = null;
        isset($report['visualization']) ? $visualization = $report['visualization'] : $visualization = null;
        isset($report['chart']) ? $chart = $report['chart'] : $chart = null;
        isset($report['chartoptions']) ? $chartoptions = $report['chartoptions'] : $chartoptions = null;
        isset($report['dataoptions']) ? $dataoptions = $report['dataoptions'] : $dataoptions = null;
        isset($report['filteroptions']) ? $filteroptions = $report['filteroptions'] : $filteroptions = null;
        isset($report['tableoptions']) ? $tableoptions = $report['tableoptions'] : $tableoptions = null;
        isset($report['dimension1']) ? $dimension1 = $report['dimension1'] : $dimension1 = null;
        isset($report['dimension2']) ? $dimension2 = $report['dimension2'] : $dimension2 = null;
        isset($report['value']) ? $value = $report['value'] : $value = null;

        if ($type === DatasourceController::DATASET_TYPE_INTERNAL_DB) { // New dataset
            $dataset = $this->DatasetService->create($name, $dimension1, $dimension2, $value);
        }
        $reportId = $this->create($name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value);
        $this->updateOptions($reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions);
        $report = $this->ReportMapper->readOwn($reportId);
        $datasetId = $report['dataset'];

        $this->DataloadMapper->beginTransaction();

        foreach ($data['dataload'] as $dataload) {
            isset($dataload['datasource']) ? $datasource = $dataload['datasource'] : $datasource = null;
            isset($dataload['name']) ? $name = $dataload['name'] : $name = null;
            isset($dataload['option']) ? $option = $dataload['option'] : $option = null;
            $schedule = null;

            $dataloadId = $this->DataloadMapper->create($datasetId, $datasource);
            $this->DataloadMapper->update($dataloadId, $name, $option, $schedule);
        }

        foreach ($data['threshold'] as $threshold) {
            isset($threshold['dimension1']) ? $dimension1 = $threshold['dimension1'] : $dimension1 = null;
            isset($threshold['value']) ? $value = $threshold['value'] : $value = null;
            isset($threshold['option']) ? $option = $threshold['option'] : $option = null;
            isset($threshold['severity']) ? $severity = $threshold['severity'] : $severity = null;
            $value = $this->floatvalue($value);
            $this->ThresholdMapper->create($reportId, $dimension1, $value, $option, $severity);
        }

        foreach ($data['data'] as $dData) {
            isset($dData[0]) ? $dimension1 = $dData[0] : $dimension1 = null;
            isset($dData[1]) ? $dimension2 = $dData[1] : $dimension2 = null;
            isset($dData[2]) ? $value = $dData[2] : $value = null;
            $this->StorageMapper->create($datasetId, $dimension1, $dimension2, $value);
        }

        $this->DataloadMapper->commit();

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
        $result = array();
        $result['report'] = $this->ReportMapper->readOwn($reportId);
        $datasetId = $result['report']['dataset'];
        $result['dataload'] = $this->DataloadMapper->read($datasetId);
        $result['threshold'] = $this->ThresholdMapper->getThresholdsByReport($reportId);
        $result['favorite'] = '';

        if ($result['report']['type'] === DatasourceController::DATASET_TYPE_INTERNAL_DB) {
            $result['data'] = $this->StorageMapper->read($datasetId);
        }

        unset($result['report']['id'], $result['report']['user_id'], $result['report']['user_id'], $result['report']['parent'], $result['report']['dataset']);
        $data = json_encode($result);
        return new DataDownloadResponse($data, $result['report']['name'] . '.export.txt', 'text/plain; charset=utf-8');
    }

    /**
     * Update report options
     *
     * @param int $reportId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @param $tableoptions
     * @return bool
     * @throws Exception
     */
    public function updateOptions(int $reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions)
    {
        return $this->ReportMapper->updateOptions($reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions);
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
     * update report group assignment (from drag & drop)
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $groupId
     * @return bool
     */
    public function updateGroup(int $reportId, $groupId)
    {
        return $this->ReportMapper->updateGroup($reportId, $groupId);
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
     * @throws Exception
     */
    public function reportsForDataset($datasetId) {
        return $this->ReportMapper->reportsForDataset($datasetId);
    }

    private function floatvalue($val)
    {
        // if value is a 3 digit comma number with one leading zero like 0,111, it should not go through the 1000 separator removal
        if (preg_match('/(?<=\b0)\,(?=\d{3}\b)/', $val) === 0 && preg_match('/(?<=\b0)\.(?=\d{3}\b)/', $val) === 0) {
            // remove , as 1000 separator
            $val = preg_replace('/(?<=\d)\,(?=\d{3}\b)/', '', $val);
            // remove . as 1000 separator
            $val = preg_replace('/(?<=\d)\.(?=\d{3}\b)/', '', $val);
        }
        // convert remaining comma to decimal point
        $val = str_replace(",", ".", $val);
        if (is_numeric($val)) {
            return number_format(floatval($val), 2, '.', '');
        } else {
            return false;
        }
    }

}
