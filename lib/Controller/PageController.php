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

use OCA\Analytics\DataSession;
use OCA\Analytics\Service\ShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use OCA\Text\Event\LoadEditor;
use OCP\EventDispatcher\IEventDispatcher;

/**
 * Controller class for main page.
 */
class PageController extends Controller
{
    /** @var IConfig */
    protected $config;
    /** @var IUserSession */
    private $userSession;
    private $logger;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var DataSession */
    private $DataSession;
    /** @var ShareService */
    private $ShareService;
    /** @var OutputController */
    private $outputController;
    /** @var IInitialState */
    protected $initialState;
    private IEventDispatcher $eventDispatcher;

    public function __construct(
        string $appName,
        IRequest $request,
        LoggerInterface $logger,
        IURLGenerator $urlGenerator,
        ShareService $ShareService,
        IUserSession $userSession,
        IConfig $config,
        DataSession $DataSession,
        IInitialState $initialState,
        OutputController $outputController,
        IEventDispatcher $eventDispatcher
    )
    {
        parent::__construct($appName, $request);
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
        $this->ShareService = $ShareService;
        $this->config = $config;
        $this->userSession = $userSession;
        $this->DataSession = $DataSession;
        $this->initialState = $initialState;
        $this->outputController = $outputController;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index()
    {
        $params = array();
        $params['token'] = '';
        $user = $this->userSession->getUser();

        $this->initialState->provideInitialState(
            'wizard',
            $this->config->getUserValue($user->getUID(), 'analytics', 'wizzard', 0)
        );

        try {
            $translationAvailable = \OCP\Server::get(\OCP\Translation\ITranslationManager::class)->hasProviders();
            $translationLanguages = \OCP\Server::get(\OCP\Translation\ITranslationManager::class)->getLanguages();
        } catch (\Exception $e) {
            $translationAvailable = false;
            $translationLanguages = false;
        }

        $this->initialState->provideInitialState(
            'translationAvailable',
            $translationAvailable
        );
        $this->initialState->provideInitialState(
            'translationLanguages',
            $translationLanguages
        );

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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function panorama()
    {
        if (class_exists(LoadEditor::class)) {
            $this->eventDispatcher->dispatchTyped(new LoadEditor());
        }
        return new TemplateResponse($this->appName, 'main_panorama');
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
        $share = $this->ShareService->getReportByToken($token);

        if (empty($share)) {
            // Dataset not shared or wrong token
            return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', [
                'redirect_url' => $this->urlGenerator->linkToRoute($this->appName . '.page.index', ['token' => $token]),
            ]));
        } else {
            if ($share['password'] !== null) {
                $password = $password !== '' ? $password : (string)$this->DataSession->getPasswordForShare($token);
                $passwordVerification = $this->ShareService->verifyPassword($password, $share['password']);
                if ($passwordVerification === true) {
                    $this->DataSession->setPasswordForShare($token, $password);
                } else {
                    $this->DataSession->removePasswordForShare($token);
                    return new TemplateResponse($this->appName, 'authenticate', ['wrongpw' => $password !== '',], 'guest');
                }
            }
            $params = array();
            $params['token'] = $token;
            $response = new PublicTemplateResponse($this->appName, 'public', $params);
            $response->setHeaderTitle('Nextcloud Analytics');
            $response->setFooterVisible(false);
            return $response;
        }
    }

    /**
     * @PublicPage
     * @UseSession
     * @NoCSRFRequired
     * @param $token
     * @param string $password
     * @return TemplateResponse|RedirectResponse
     */
    public function indexPublicMin($token, string $password = '')
    {
        $share = $this->ShareService->getReportByToken($token);

        if (empty($share)) {
            // Dataset not shared or wrong token
            return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', [
                'redirect_url' => $this->urlGenerator->linkToRoute($this->appName . '.page.index', ['token' => $token]),
            ]));
        } else {
            if ($share['password'] !== null) {
                $password = $password !== '' ? $password : (string)$this->DataSession->getPasswordForShare($token);
                $passwordVerification = $this->ShareService->verifyPassword($password, $share['password']);
                if ($passwordVerification === true) {
                    $this->DataSession->setPasswordForShare($token, $password);
                } else {
                    $this->DataSession->removePasswordForShare($token);
                    return new TemplateResponse($this->appName, 'authenticate', ['wrongpw' => $password !== '',], 'guest');
                }
            }
            $params = array();
            $params['data'] = $this->outputController->getData($share);
            $params['baseurl'] = str_replace('/img/app.svg', '', $this->urlGenerator->imagePath('analytics', 'app.svg'));
            $params['nonce'] = \OC::$server->getContentSecurityPolicyNonceManager()->getNonce();
            $response = new StandaloneTemplateResponse($this->appName, 'publicMin', $params, '');
            $csp = new ContentSecurityPolicy();
            $csp->addAllowedScriptDomain('*');
            $csp->addAllowedFrameAncestorDomain($share['domain']);
            $response->setContentSecurityPolicy($csp);
            return $response;
        }
    }
}