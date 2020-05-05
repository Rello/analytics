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

\OC::$server->getEventDispatcher()->addListener(
    'OCA\Files::loadAdditionalScripts',
    function () {
        Util::addScript('analytics', 'viewer');
    }
);
