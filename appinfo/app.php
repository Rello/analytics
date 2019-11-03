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

namespace OCA\data\AppInfo;

use OCP\Util;

$navigationEntry = function () {
    return [
        'id' => 'data',
        'order' => 6,
        'name' => \OC::$server->getL10N('data')->t('Analytics'),
        'href' => \OC::$server->getURLGenerator()->linkToRoute('data.page.index'),
        'icon' => \OC::$server->getURLGenerator()->imagePath('data', 'app.svg'),
    ];
};
\OC::$server->getNavigationManager()->add($navigationEntry);
