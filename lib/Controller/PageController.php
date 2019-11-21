<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\DataSession;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Controller class for main page.
 */
class PageController extends Controller
{
    private $userId;
    private $l10n;
    private $configManager;
    private $logger;
    private $share;
    /** @var IURLGenerator */
    private $url;
    /** @var DataSession */
    private $DataSession;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IConfig $configManager,
        IL10N $l10n,
        IURLGenerator $url,
        ShareController $share,
        DataSession $DataSession
    )
    {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->configManager = $configManager;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->url = $url;
        $this->share = $share;
        $this->DataSession = $DataSession;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index()
    {
        $response = new TemplateResponse('analytics', 'main');
        return $response;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     * @UseSession
     *
     * @param string $token
     * @param string $password
     * @return RedirectResponse|TemplateResponse
     */
    public function authenticatePassword(string $token, string $password = '')
    {
        return $this->indexPublic($token, $password);
    }

    /**
     * @PublicPage
     * @UseSession
     * @NoCSRFRequired
     * @param $token
     * @param string $password
     * @return TemplateResponse|RedirectResponse
     */
    public function indexPublic($token, string $password = '')
    {
        $share = $this->share->getDatasetByToken($token);

        if ($share === null) {
            // Dataset not shared or wrong token
            return new RedirectResponse($this->url->linkToRoute('core.login.showLoginForm', [
                'redirect_url' => $this->url->linkToRoute('analytics.page.index', ['token' => $token]),
            ]));
        } else {
            if ($share['password'] !== null) {
                $password = $password !== '' ? $password : (string)$this->DataSession->getPasswordForShare($token);
                $passwordVerification = $this->share->verifyPassword($password, $share['password']);
                if ($passwordVerification === true) {
                    $this->DataSession->setPasswordForShare($token, $password);
                } else {
                    $this->DataSession->removePasswordForShare($token);
                    return new TemplateResponse('analytics', 'authenticate', ['wrongpw' => $password !== '',], 'guest');
                }
            }
            $params = array();
            $params['token'] = $token;
            $response = new TemplateResponse('analytics', 'public', $params);
            return $response;
        }
    }
}