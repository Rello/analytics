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

$version = Util::getVersion()[0];
if ($version >= 20) {
    class Application extends Application20
    {
        public const APP_ID = 'analytics';
    }
} else {
    class Application extends Application19
    {
        public const APP_ID = 'analytics';
    }
}