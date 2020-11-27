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

class Application20 extends App implements IBootstrap
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
        $this->registerNavigationEntry();
        $this->registerNotifications();
    }

    public function boot(IBootContext $context): void
    {
        $this->getContainer()->query(FlowOperation::class)->register();
    }

    protected function registerNotifications(): void
    {
        $notificationManager = \OC::$server->getNotificationManager();
        $notificationManager->registerNotifierService(Notifier::class);
    }

    protected function registerNavigationEntry(): void
    {
        $navigationEntry = function () {
            return [
                'id' => 'analytics',
                'order' => 6,
                'name' => \OC::$server->getL10N('analytics')->t('Analytics'),
                'href' => \OC::$server->getURLGenerator()->linkToRoute('analytics.page.index'),
                'icon' => \OC::$server->getURLGenerator()->imagePath('analytics', 'app.svg'),
            ];
        };
        \OC::$server->getNavigationManager()->add($navigationEntry);
    }

}