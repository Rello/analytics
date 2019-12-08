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

use OCP\Util;

Util::addStyle('analytics', 'style');
Util::addStyle('analytics', 'jquery.dataTables.min');
Util::addScript('analytics', 'app');
Util::addScript('analytics', 'jquery.dataTables.min');
Util::addScript('analytics', 'highcharts/highcharts');
//Util::addScript('analytics', 'jquery.csv.min');
//Util::addScript('analytics', 'highcharts/modules/data');
//Util::addScript('analytics', 'highcharts/modules/exporting');
//Util::addScript('analytics', 'highcharts/modules/export-data');
?>

<header>
    <div id="header">
        <div id="header-left">
            <a href="#"
               title="" id="nextcloud">
                <div class="logo logo-icon"></div>
                <h1 class="header-appname" id="header-appname">
                    Data Analytics for Nextcloud - Public Share
                </h1>
            </a>
        </div>
    </div>
</header>
<div id="app-content">
    <?php print_unescaped($this->inc('part.content')); ?>
</div>