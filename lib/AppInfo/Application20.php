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
use OCA\Analytics\Flow\Operation;
use OCA\Analytics\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\QueryException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IServerContainer;

class Application20 extends App implements IBootstrap
{
    public const APP_ID = 'analytics';

    /** @var IServerContainer */
    private $server;

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerDashboardWidget(Widget::class);
        $this->registerNavigationEntry();
        $this->registerNotifications();
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

    public function registerNotifications(): void
    {
        $notificationManager = \OC::$server->getNotificationManager();
        $notificationManager->registerNotifierService(Notifier::class);
    }

    public function boot(IBootContext $context): void
    {

        $server = $this->getContainer()->getServer();
        /** @var IEventDispatcher $dispatcher */
        try {
            $dispatcher = $server->query(IEventDispatcher::class);
        } catch (QueryException $e) {
        }

        Operation::register($dispatcher);
    }

}