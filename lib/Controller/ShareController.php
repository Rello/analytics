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

use OCA\Analytics\Service\ShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ShareController extends Controller
{
    const SHARE_TYPE_USER = 0;
    const SHARE_TYPE_GROUP = 1;
    const SHARE_TYPE_USERGROUP = 2;
    const SHARE_TYPE_LINK = 3;
    const SHARE_TYPE_ROOM = 10;

    /** @var LoggerInterface */
    private $logger;
    private $ShareService;

    public function __construct(
        $appName,
        IRequest $request,
        LoggerInterface $logger,
        ShareService $ShareService
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->ShareService = $ShareService;
    }

    /**
     * create a new share
     *
     * @NoAdminRequired
     * @param $reportId
     * @param $type
     * @param $user
     * @return DataResponse
     */
    public function create($reportId, $type, $user)
    {
        return new DataResponse($this->ShareService->create($reportId, $type, $user));
    }

    /**
     * get all shares for a dataset
     *
     * @NoAdminRequired
     * @param $reportId
     * @return DataResponse
     */
    public function read($reportId)
    {
        return new DataResponse($this->ShareService->read($reportId));
    }

    /**
     * update/set share password
     *
     * @NoAdminRequired
     * @param $shareId
     * @param null $password
     * @param null $canEdit
     * @param null $domain
     * @return DataResponse
     */
    public function update($shareId, $password = null, $canEdit = null, $domain = null)
    {
        return new DataResponse($this->ShareService->update($shareId, $password, $canEdit, $domain));
    }

    /**
     * delete a share
     *
     * @NoAdminRequired
     * @param $shareId
     * @return DataResponse
     */
    public function delete($shareId)
    {
        return new DataResponse($this->ShareService->delete($shareId));
    }
}