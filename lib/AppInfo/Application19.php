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

use OCA\Analytics\Flow\Operation;
use OCA\Analytics\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\QueryException;
use OCP\EventDispatcher\IEventDispatcher;

class Application19 extends App
{
    public const APP_ID = 'analytics';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register()
    {
        $this->registerNotifications();
        $this->registerNavigationEntry();

        $server = $this->getContainer()->getServer();
        /** @var IEventDispatcher $dispatcher */
        try {
            $dispatcher = $server->query(IEventDispatcher::class);
        } catch (QueryException $e) {
            // no action
        }

        Operation::register($dispatcher);
    }

    public function registerNotifications(): void
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