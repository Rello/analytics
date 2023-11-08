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
     * @param $subheader
     * @param int $type
     * @param int $page
     * @param int $parent
     * @param $reports
     * @param $layout
     * @return DataResponse
     * @throws Exception
     */
    public function create($name, $subheader, int $type, int $page, int $parent, $reports, $layout)
    {
        return new DataResponse($this->StoryService->create($name, $subheader, $type, $page, $parent, $reports, $layout));
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
     * @param int $id
     * @param $name
     * @param $subheader
     * @param int $type
     * @param int $page
     * @param int $parent
     * @param $reports
     * @param $layout
     * @return DataResponse
     * @throws Exception
     */
    public function update(int $id, $name, $subheader, int $type, int $page, int $parent, $reports, $layout)
    {
        return new DataResponse($this->StoryService->update($id, $name, $subheader, $type, $page, $parent, $reports, $layout));
    }
}