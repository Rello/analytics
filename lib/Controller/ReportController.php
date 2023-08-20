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

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\ReportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
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
        return new DataResponse($this->ReportService->index());
    }

    /**
     * create new blank report
     *
     * @NoAdminRequired
     * @param $name
     * @param $subheader
     * @param int $parent
     * @param int $type
     * @param int $dataset
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return DataResponse
     */
    public function create($name, $subheader, int $parent, int $type, int $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value, $addReport = null)
    {
        return new DataResponse($this->ReportService->create($name, $subheader, $parent, $type, $dataset, $link, $visualization, $chart, $dimension1, $dimension2, $value, $addReport));
    }

    /**
     * copy an existing report with the current navigation status
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @param $tableoptions
     * @return DataResponse
     * @throws Exception
     */
    public function createCopy(int $reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions)
    {
        return new DataResponse($this->ReportService->createCopy($reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions));
    }

    /**
     * create new report from file
     *
     * @NoAdminRequired
     * @param string $file
     * @return DataResponse
     */
    public function createFromDataFile($file = '')
    {
        return new DataResponse($this->ReportService->createFromDataFile($file));
    }

    /**
     * get own report details
     *
     * @NoAdminRequired
     * @param int $reportId
     * @return DataResponse
     */
    public function read(int $reportId)
    {
        return new DataResponse($this->ReportService->read($reportId, false));
    }

    /**
     * Delete report and all depending objects
     *
     * @NoAdminRequired
     * @param int $reportId
     * @return DataResponse
     */
    public function delete(int $reportId)
    {
        if ($this->ReportService->isOwn($reportId)) {
            return new DataResponse($this->ReportService->delete($reportId));
        } else {
            return new DataResponse(false,400);
        }
    }

    /**
     * get report details
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $name
     * @param $subheader
     * @param int $parent
     * @param $link
     * @param $visualization
     * @param $chart
     * @param $chartoptions
     * @param $dataoptions
     * @param $dimension1
     * @param $dimension2
     * @param $value
     * @return DataResponse
     * @throws \OCP\DB\Exception
     */
    public function update(int $reportId, $name, $subheader, int $parent, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1 = null, $dimension2 = null, $value = null)
    {
        return new DataResponse($this->ReportService->update($reportId, $name, $subheader, $parent, $link, $visualization, $chart, $chartoptions, $dataoptions, $dimension1, $dimension2, $value));
    }

    /**
     * update report options
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $chartoptions
     * @param $dataoptions
     * @param $filteroptions
     * @param $tableoptions
     * @return DataResponse
     * @throws Exception
     */
    public function updateOptions(int $reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions)
    {
        return new DataResponse($this->ReportService->updateOptions($reportId, $chartoptions, $dataoptions, $filteroptions, $tableoptions));
    }

    /**
     * update report refresh details
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $refresh
     * @return DataResponse
     */
    public function updateRefresh(int $reportId, $refresh)
    {
        return new DataResponse($this->ReportService->updateRefresh($reportId, $refresh));
    }

    /**
     * update report group assignment (from drag & drop)
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param $groupId
     * @return DataResponse
     */
    public function updateGroup(int $reportId, $groupId)
    {
        return new DataResponse($this->ReportService->updateGroup($reportId, $groupId));
    }

    /**
     * get own reports which are marked as favorites
     *
     * @NoAdminRequired
     * @return DataResponse
     */
    public function getOwnFavoriteReports()
    {
        return new DataResponse($this->ReportService->getOwnFavoriteReports());
    }

    /**
     * set/remove the favorite flag for a report
     *
     * @NoAdminRequired
     * @param int $reportId
     * @param string $favorite
     * @return DataResponse
     */
    public function setFavorite(int $reportId, string $favorite)
    {
        return new DataResponse($this->ReportService->setFavorite($reportId, $favorite));
    }

    /**
     * Export report
     *
     * @NoCSRFRequired
     * @NoAdminRequired
     * @param int $reportId
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