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

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ReportController extends Controller
{
    private $logger;
    private $ReportService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        ReportService $ReportService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ReportService = $ReportService;
    }

    /**
     * get all reports
     *
     * @NoAdminRequired
     * @return DataResponse
     */
    public function index()
    {
        return $this->ReportService->index();
    }

    /**
     * create new blank report
     *
     * @NoAdminRequired
     * @return int
     */
    public function create()
    {
        return $this->ReportService->create();
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
        return $this->ReportService->createCopy($reportId, $chartoptions, $dataoptions, $filteroptions);
    }

    /**
     * create new report from file
     *
     * @NoAdminRequired
     * @param string $file
     * @return int
     */
    public function createFromFile($file = '')
    {
        //**todo**//
        //still needed?
        return $this->ReportService->create($file);
    }

    /**
     * get own report details
     *
     * @NoAdminRequired
     * @param int $reportId
     * @return array
     */
    public function read(int $reportId)
    {
        return $this->ReportService->read($reportId);
    }

    /**
     * Delete report and all depending objects
     *
     * @NoAdminRequired
     * @param int $reportId
     * @return bool
     */
    public function delete(int $reportId)
    {
        return $this->ReportService->delete($reportId);
    }

    /**
     * get report details
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $name
     * @param $subheader
     * @param int $parent
     * @param int $type
     * @param int $dataset
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
    public function update(int $reportId, $name, $subheader, int $parent, int $type, int $dataset, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1 = null, $dimension2 = null, $value = null)
    {
        return $this->ReportService->update($reportId, $name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value);
    }

    /**
     * update report options
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @return bool
     */
    public function updateOptions(int $reportId, $chartoptions, $dataoptions, $filteroptions)
    {
        return $this->ReportService->updateOptions($reportId, $chartoptions, $dataoptions, $filteroptions);
    }

    /**
     * update report refresh details
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $refresh
     * @return bool
     */
    public function updateRefresh(int $reportId, $refresh)
    {
        return $this->ReportService->updateRefresh($reportId, $refresh);
    }

    /**
     * get own reports which are marked as favorites
     *
     * @NoAdminRequired
     * @return array|bool
     */
    public function getOwnFavoriteReports()
    {
        return $this->ReportService->getOwnFavoriteReports();
    }

    /**
     * set/remove the favorite flag for a report
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param string $favorite
     * @return bool
     */
    public function setFavorite(int $reportId, string $favorite)
    {
        return $this->ReportService->setFavorite($reportId, $favorite);
    }

    /**
     * Export report
     *
     * @NoCSRFRequired
     * @NoAdminRequired
     * @param int $reportId
     * @return \OCP\AppFramework\Http\DataDownloadResponse
     */
    public function export(int $reportId)
    {
        return $this->ReportService->export($reportId);
    }

    /**
     * Import report
     *
     * @NoAdminRequired
     * @param string|null $path
     * @param string|null $raw
     * @return DataResponse
     * @throws \OCP\Files\NotFoundException
     * @throws \OCP\Files\NotPermittedException
     */
    public function import(string $path = null, string $raw = null)
    {
        return new DataResponse($this->ReportService->import($path, $raw));
    }

}