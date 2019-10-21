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

use OCA\data\DataSession;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\NotFoundResponse;
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
    private $dataSession;

    public function __construct(
        string $AppName,
        IRequest $request,
        $userId,
        ILogger $logger,
        IConfig $configManager,
        IL10N $l10n,
        IURLGenerator $url,
        ShareController $share,
        DataSession $session
    )
    {
        parent::__construct($AppName, $request);
        $this->AppName = $AppName;
        $this->userId = $userId;
        $this->configManager = $configManager;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->url = $url;
        $this->share = $share;
        $this->dataSession = $session;
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
     * @param $token
     * @return TemplateResponse|RedirectResponse
     */
    public function indexPublic($token, string $password = '')
    {
        $share = $this->share->getDatasetByToken($token);
        $this->logger->error('sessionpw: ' . $this->dataSession->getPasswordForShare($token));

        if ($share === false) {
            // Dataset not shared or wrong token
            return new RedirectResponse($this->url->linkToRoute('core.login.showLoginForm', [
                'redirect_url' => $this->url->linkToRoute('data.page.index', ['token' => $token]),
            ]));
        } else {
            if ($share['password'] !== '') {
                $password = $password !== '' ? $password : (string)$this->dataSession->getPasswordForShare($token);
                $passwordVerification = $this->share->verifyPassword($password, $share['password']);
                if ($passwordVerification === true) {
                    $this->dataSession->setPasswordForShare($token, $password);
                } else {
                    $this->dataSession->removePasswordForShare($token);
                    return new TemplateResponse('data', 'authenticate', [
                        'wrongpw' => $password !== '',
                    ], 'guest');
                }
            }
            $params['token'] = $token;
            $response = new TemplateResponse('data', 'public', $params);
            return $response;
        }
    }


}