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
Util::addStyle('analytics', 'sharetabview');
Util::addStyle('analytics', 'jquery.dataTables.min');
Util::addScript('analytics', 'app');
Util::addScript('analytics', 'sidebar');
Util::addScript('analytics', 'jquery.dataTables.min');
Util::addScript('analytics', 'jquery.csv.min');
Util::addScript('analytics', 'highcharts/highcharts');
//Util::addScript('analytics', 'highcharts/highcharts-more');
Util::addScript('analytics', 'highcharts/modules/data');
//Util::addScript('analytics', 'highcharts/modules/exporting');
//Util::addScript('analytics', 'highcharts/modules/export-data');
?>

<div id="app-navigation">
    <?php print_unescaped($this->inc('part.navigation')); ?>
    <?php print_unescaped($this->inc('part.settings')); ?>
</div>

<div id="app-content">
    <div id="loading">
        <i class="ioc-spinner ioc-spin"></i>
    </div>
    <?php print_unescaped($this->inc('part.content')); ?>
</div>
    <?php print_unescaped($this->inc('part.sidebar')); ?>