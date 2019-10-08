<?php
/**
 * Audio Player
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2016-2019 Marcel Scherello
 */

use OCP\Util;

//Util::addStyle('data', 'style');
//Util::addScript('data', 'app');
script('data', 'data_main');
script('data', 'jquery.dataTables.min');
script('data', 'highcharts');
script('data', 'modules/data');
script('data', 'modules/exporting');
script('data', 'modules/export-data');
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