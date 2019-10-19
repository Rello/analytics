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
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

/**
 * Controller class for main page.
 */
class PageController extends Controller
{
    private $userId;
    private $l10n;
    private $configManager;
    private $logger;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IConfig $configManager,
        IL10N $l10n
    )
    {
        parent::__construct($AppName, $request);
        $this->AppName = $AppName;
        $this->userId = $userId;
        $this->configManager = $configManager;
        $this->l10n = $l10n;
        $this->logger = $logger;
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

    /**
     * @PublicPage
     * @NoCSRFRequired
     * @param string $token
     * @return TemplateResponse
     */
    public function indexPublic($token)
    {
        $tokenArray = ['t44' => 4, 't33' => 3];

        if (array_key_exists($token, $tokenArray)) {
            $params['token'] = $token;
            $response = new TemplateResponse('data', 'public', $params);
            return $response;
        } else {
            return new NotFoundResponse();
        }
    }


}