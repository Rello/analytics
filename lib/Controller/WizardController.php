<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class WizardController extends Controller
{

    /** @var IConfig */
    protected $config;
    /** @var IUserSession */
    private $userSession;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IConfig $config
    )
    {
        parent::__construct($appName, $request);
        $this->appName = $appName;
        $this->config = $config;
        $this->userSession = $userSession;
    }


    /**
     * @NoAdminRequired
     *
     * @return DataResponse
     * @throws \OCP\PreConditionNotMetException
     */
    public function dismiss(): DataResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException("Acting user cannot be resolved");
        }
        $this->config->setUserValue($user->getUID(), $this->appName, 'wizzard', 1);
        return new DataResponse();
    }
}