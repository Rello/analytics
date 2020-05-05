<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Controller;

use OCA\Analytics\DataSession;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Controller class for main page.
 */
class PageController extends Controller
{
    protected $appName;
    private $userId;
    private $configManager;
    private $logger;
    private $ShareController;
    /** @var IURLGenerator */
    private $url;
    /** @var DataSession */
    private $DataSession;

    public function __construct(
        string $appName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IConfig $configManager,
        IURLGenerator $url,
        ShareController $ShareController,
        DataSession $DataSession
    )
    {
        parent::__construct($appName, $request);
        $this->appName = $appName;
        $this->userId = $userId;
        $this->configManager = $configManager;
        $this->logger = $logger;
        $this->url = $url;
        $this->ShareController = $ShareController;
        $this->DataSession = $DataSession;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index()
    {
        $params = array();
        $params['token'] = '';
        return new TemplateResponse($this->appName, 'main', $params);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function advanced()
    {
        return new TemplateResponse($this->appName, 'main_advanced');
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
        $share = $this->ShareController->getDatasetByToken($token);

        if (empty($share)) {
            // Dataset not shared or wrong token
            return new RedirectResponse($this->url->linkToRoute('core.login.showLoginForm', [
                'redirect_url' => $this->url->linkToRoute($this->appName . '.page.index', ['token' => $token]),
            ]));
        } else {
            if ($share['password'] !== null) {
                $password = $password !== '' ? $password : (string)$this->DataSession->getPasswordForShare($token);
                $passwordVerification = $this->ShareController->verifyPassword($password, $share['password']);
                if ($passwordVerification === true) {
                    $this->DataSession->setPasswordForShare($token, $password);
                } else {
                    $this->DataSession->removePasswordForShare($token);
                    return new TemplateResponse($this->appName, 'authenticate', ['wrongpw' => $password !== '',], 'guest');
                }
            }
            $params = array();
            $params['token'] = $token;
            return new TemplateResponse($this->appName, 'public', $params);
        }
    }
}