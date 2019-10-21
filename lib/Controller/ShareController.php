<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\data\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\IRequest;

class ShareController extends Controller
{
    private $logger;
    private $DBController;

    public function __construct(
        $appName,
        IRequest $request,
        ILogger $logger,
        DbController $DBController
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->DBController = $DBController;
    }

    /**
     * get all datasets
     * @NoAdminRequired
     */
    public function getDatasetByToken($token)
    {
        $dataset = $this->DBController->getDatasetByToken($token);
        return $dataset;
    }

    /**
     * get all datasets
     * @NoAdminRequired
     */
    public function verifyPassword($password, $sharePassword)
    {
        return password_verify($password, $sharePassword);
    }


}