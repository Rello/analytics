<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

use OCP\Util;

script('data', 'app');
script('data', 'jquery.dataTables.min');
script('data', 'highcharts/highcharts');
script('data', 'highcharts/modules/data');
script('data', 'highcharts/modules/exporting');
script('data', 'highcharts/modules/export-data');
style('data', 'jquery.dataTables.min');
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

<div id="app-sidebar" class="details-view scroll-container disappear" data-trackid="">
    <?php print_unescaped($this->inc('content/sidebar')); ?>
</div>