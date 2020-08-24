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

use OCP\Util;

$app = \OC::$server->query(\OCA\Analytics\AppInfo\Application::class);
$app->register();

\OC::$server->getEventDispatcher()->addListener(
    'OCA\Files::loadAdditionalScripts',
    function () {
        Util::addScript('analytics', 'viewer');
    }
);
