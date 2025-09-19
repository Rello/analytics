<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\Service\PanoramaService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

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
     * @return DataResponse
     * @throws Exception
     * @throws PreConditionNotMetException
     */
    #[NoAdminRequired]
    public function index()
    {
        return new DataResponse($this->PanoramaService->index());
    }

    /**
     * create new blank report
     *
     * @param int $type
     * @param int $parent
     * @return DataResponse
     * @throws Exception
     */
    #[NoAdminRequired]
    public function create(int $type, int $parent)
    {
        return new DataResponse($this->PanoramaService->create($type, $parent));
    }


    /**
     * get own report details
     *
     * @param int $panoramaId
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function read(int $panoramaId)
    {
        return new DataResponse($this->PanoramaService->read($panoramaId));
    }

    /**
     * Delete report and all depending objects
     *
     * @param int $panoramaId
     * @return DataResponse
     */
    #[NoAdminRequired]
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
     * @param int $panoramaId
     * @param $name
     * @param int $type
     * @param int $parent
     * @param $pages
     * @return DataResponse
     * @throws Exception
     */
    #[NoAdminRequired]
    public function update(int $panoramaId, $name, int $type, int $parent, $pages)
    {
        $pages = json_encode($pages);
        return new DataResponse($this->PanoramaService->update($panoramaId, $name, $type, $parent, $pages));
    }

    /**
     * create panorama group
     *
     * @param int $parent
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function createGroup(int $parent)
    {
        return new DataResponse($this->PanoramaService->createGroup($parent));
    }

    /**
     * update panorama group assignment
     *
     * @param int $panoramaId
     * @param int $groupId
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function updateGroup(int $panoramaId, int $groupId)
    {
        return new DataResponse($this->PanoramaService->updateGroup($panoramaId, $groupId));
    }

    /**
     * rename panorama
     *
     * @param int $panoramaId
     * @param string $name
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function rename(int $panoramaId, string $name)
    {
        return new DataResponse($this->PanoramaService->rename($panoramaId, $name));
    }

    /**
     * get own reports which are marked as favorites
     *
     * @return DataResponse
     * @throws Exception
     */
    #[NoAdminRequired]
    public function getOwnFavoriteReports()
    {
        return new DataResponse($this->PanoramaService->getOwnFavoriteReports());
    }

    /**
     * set/remove the favorite flag for a report
     *
     * @param int $panoramaId
     * @param string $favorite
     * @return DataResponse
     */
    #[NoAdminRequired]
    public function setFavorite(int $panoramaId, string $favorite)
    {
        return new DataResponse($this->PanoramaService->setFavorite($panoramaId, $favorite));
    }

}