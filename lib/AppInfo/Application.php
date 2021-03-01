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

namespace OCA\Analytics\AppInfo;

use OCA\Analytics\Dashboard\Widget;
use OCA\Analytics\Flow\FlowOperation;
use OCA\Analytics\Listener\LoadAdditionalScripts;
use OCA\Analytics\Notification\Notifier;
use OCA\Analytics\Search\Provider;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'analytics';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerDashboardWidget(Widget::class);
        $context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScripts::class);
        $context->registerSearchProvider(Provider::class);
        $this->registerNotifications();
    }

    public function boot(IBootContext $context): void
    {
        $server = $context->getServerContainer();

        /** @var IEventDispatcher $dispatcher */
        $dispatcher = $server->query(IEventDispatcher::class);
        FlowOperation::register($dispatcher);
    }

    protected function registerNotifications(): void
    {
        $notificationManager = \OC::$server->getNotificationManager();
        $notificationManager->registerNotifierService(Notifier::class);
    }
}