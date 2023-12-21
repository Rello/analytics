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

use OCA\Analytics\Service\PanoramaService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;

class PanoramaController extends Controller
{
    private $logger;
    private $PanoramaService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        PanoramaService $PanoramaService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->PanoramaService = $PanoramaService;
    }

    /**
     * get all reports
     *
     * @NoAdminRequired
     * @return DataResponse
     * @throws Exception
     * @throws PreConditionNotMetException
     */
    public function index()
    {
        return new DataResponse($this->PanoramaService->index());
    }

    /**
     * create new blank report
     *
     * @NoAdminRequired
     * @param int $type
     * @param int $parent
     * @return DataResponse
     * @throws Exception
     */
    public function create(int $type, int $parent)
    {
        return new DataResponse($this->PanoramaService->create($type, $parent));
    }


    /**
     * get own report details
     *
     * @NoAdminRequired
     * @param int $panoramaId
     * @return DataResponse
     */
    public function read(int $panoramaId)
    {
        return new DataResponse($this->PanoramaService->read($panoramaId));
    }

    /**
     * Delete report and all depending objects
     *
     * @NoAdminRequired
     * @param int $panoramaId
     * @return DataResponse
     */
    public function delete(int $panoramaId)
    {
        if ($this->PanoramaService->isOwn($panoramaId)) {
            return new DataResponse($this->PanoramaService->delete($panoramaId));
        } else {
            return new DataResponse(false,400);
        }
    }

    /**
     * get report details
     *
     * @NoAdminRequired
     * @param int $panoramaId
     * @param $name
     * @param int $type
     * @param int $parent
     * @param $pages
     * @return DataResponse
     * @throws Exception
     */
    public function update(int $panoramaId, $name, int $type, int $parent, $pages)
    {
        $pages = json_encode($pages);
        return new DataResponse($this->PanoramaService->update($panoramaId, $name, $type, $parent, $pages));
    }

    /**
     * get own reports which are marked as favorites
     *
     * @NoAdminRequired
     * @return DataResponse
     * @throws Exception
     */
    public function getOwnFavoriteReports()
    {
        return new DataResponse($this->PanoramaService->getOwnFavoriteReports());
    }

    /**
     * set/remove the favorite flag for a report
     *
     * @NoAdminRequired
     * @param int $panoramaId
     * @param string $favorite
     * @return DataResponse
     */
    public function setFavorite(int $panoramaId, string $favorite)
    {
        return new DataResponse($this->PanoramaService->setFavorite($panoramaId, $favorite));
    }

}