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

namespace OCA\Analytics\AppInfo;

use OCA\Analytics\Dashboard\Widget;
use OCA\Analytics\Flow\FlowOperation;
use OCA\Analytics\Listener\LoadAdditionalScripts;
use OCA\Analytics\Listener\UserDeletedListener;
use OCA\Analytics\Notification\Notifier;
use OCA\Analytics\Search\SearchProvider;
use OCA\Analytics\Listener\ReferenceListener;
use OCA\Analytics\Reference\ReferenceProvider;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\User\Events\UserDeletedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use Psr\Container\ContainerInterface;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

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
        $context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
        $context->registerSearchProvider(SearchProvider::class);

        if (method_exists($context, 'registerReferenceProvider')) {
            $context->registerReferenceProvider(ReferenceProvider::class);
            $context->registerEventListener(RenderReferenceEvent::class, ReferenceListener::class);
        }

        $this->registerNotifications();

        $context->registerEventListener(RegisterOperationsEvent::class, FlowOperation::class);
    }

    public function boot(IBootContext $context): void
    {
     }

    protected function registerNotifications(): void
    {
        $notificationManager = \OC::$server->getNotificationManager();
        $notificationManager->registerNotifierService(Notifier::class);
    }
}