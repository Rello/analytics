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

namespace OCA\Analytics\AppInfo;

use OCA\Analytics\Notification\Notifier;
use OCP\AppFramework\App;


class Application extends App {

    public function __construct(array $urlParams = array()) {

        parent::__construct('analytics', $urlParams);
        $this->register();
    }

    public function register()
    {
        $this->registerNotificationNotifier();
        //$this->registerCommentsEntity();
    }

    protected function registerNotificationNotifier()
    {
        $notificationManager = \OC::$server->getNotificationManager();
        // as of NC17
        if (method_exists($notificationManager, 'registerNotifierService')) {
            \OC::$server->getNotificationManager()->registerNotifierService(Notifier::class);
        }
    }
}
