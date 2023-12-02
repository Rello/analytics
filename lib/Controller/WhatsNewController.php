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

use OCA\Analytics\WhatsNew\WhatsNewCheck;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class WhatsNewController extends Controller
{

    /** @var IConfig */
    protected $config;
    /** @var IUserSession */
    private $userSession;
    /** @var WhatsNewCheck */
    private $whatsNewService;
    /** @var IFactory */
    private $langFactory;
    private $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        IConfig $config,
        WhatsNewCheck $whatsNewService,
        IFactory $langFactory,
        LoggerInterface $logger
    )
    {
        parent::__construct($appName, $request);
        $this->appName = $appName;
        $this->config = $config;
        $this->userSession = $userSession;
        $this->whatsNewService = $whatsNewService;
        $this->langFactory = $langFactory;
        $this->logger = $logger;
    }

    /**
     * @NoAdminRequired
     */
    public function get(): DataResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException("Acting user cannot be resolved");
        }
        $lastRead = $this->config->getUserValue($user->getUID(), $this->appName, 'whatsNewLastRead', 0);
        $currentVersion = $this->whatsNewService->normalizeVersion($this->config->getAppValue($this->appName, 'installed_version'));

        if (version_compare($lastRead, $currentVersion, '>=')) {
            return new DataResponse([], Http::STATUS_NO_CONTENT);
        }

        try {
            $iterator = $this->langFactory->getLanguageIterator();
            $whatsNew = $this->whatsNewService->getChangesForVersion($currentVersion);

            $resultData = [
                'changelogURL' => $whatsNew['changelogURL'],
                'product' => 'Analytics',
                'version' => $currentVersion,
            ];
            do {
                $lang = $iterator->current();
                if (isset($whatsNew['whatsNew'][$lang])) {
                    $resultData['whatsNew'] = $whatsNew['whatsNew'][$lang];
                    break;
                }
                $iterator->next();
            } while ($lang !== 'en' && $iterator->valid());
            return new DataResponse($resultData);
        } catch (DoesNotExistException $e) {
            return new DataResponse([], Http::STATUS_NO_CONTENT);
        }
    }

    /**
     * @NoAdminRequired
     *
     * @throws \OCP\PreConditionNotMetException
     * @throws DoesNotExistException
     */
    public function dismiss(string $version): DataResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException("Acting user cannot be resolved");
        }
        $version = $this->whatsNewService->normalizeVersion($version);
        // checks whether it's a valid version, throws an Exception otherwise
        $this->whatsNewService->getChangesForVersion($version);
        $this->config->setUserValue($user->getUID(), $this->appName, 'whatsNewLastRead', $version);
        return new DataResponse();
    }
}