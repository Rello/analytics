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

use OCA\Analytics\Service\StoryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class StoryController extends Controller
{
    private $logger;
    private $StoryService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        StoryService $StoryService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->StoryService = $StoryService;
    }

    /**
     * get all reports
     *
     * @NoAdminRequired
     * @return DataResponse
     */
    public function index()
    {
        return new DataResponse($this->StoryService->index());
    }

    /**
     * create new blank report
     *
     * @NoAdminRequired
     * @param $name
     * @param int $type
     * @param int $parent
     * @param int $pages
     * @return DataResponse
     * @throws Exception
     */
    public function create($name, int $type, int $parent, int $pages)
    {
        return new DataResponse($this->StoryService->create($name, $type, $parent, $pages));
    }


    /**
     * get own report details
     *
     * @NoAdminRequired
     * @param int $storyId
     * @return DataResponse
     */
    public function read(int $storyId)
    {
        return new DataResponse($this->StoryService->read($storyId));
    }

    /**
     * Delete report and all depending objects
     *
     * @NoAdminRequired
     * @param int $storyId
     * @return DataResponse
     */
    public function delete(int $storyId)
    {
        if ($this->StoryService->isOwn($storyId)) {
            return new DataResponse($this->StoryService->delete($storyId));
        } else {
            return new DataResponse(false,400);
        }
    }

    /**
     * get report details
     *
     * @NoAdminRequired
     * @param int $storyId
     * @param $name
     * @param int $type
     * @param int $parent
     * @param $pages
     * @return DataResponse
     * @throws Exception
     */
    public function update(int $storyId, $name, int $type, int $parent, $pages)
    {
        $pages = json_encode($pages);
        return new DataResponse($this->StoryService->update($storyId, $name, $type, $parent, $pages));
    }
}