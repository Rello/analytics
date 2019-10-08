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
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

/**
 * Controller class for main page.
 */
class PageController extends Controller
{
    private $userId;
    private $l10n;
    private $configManager;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        IConfig $configManager,
        IL10N $l10n
    )
    {
        parent::__construct($AppName, $request);
        $this->AppName = $AppName;
        $this->userId = $userId;
        $this->configManager = $configManager;
        $this->l10n = $l10n;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index()
    {
        $response = new TemplateResponse('data', 'main');
        return $response;
    }
}