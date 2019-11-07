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

script('analytics', 'app');
script('analytics', 'sidebar');
script('analytics', 'jquery.dataTables.min');
script('analytics', 'jquery.csv.min');
script('analytics', 'highcharts/highcharts');
script('analytics', 'highcharts/modules/data');
script('analytics', 'highcharts/modules/exporting');
script('analytics', 'highcharts/modules/export-data');
style('analytics', 'jquery.dataTables.min');
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