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

Util::addStyle('data', 'style');
script('data', 'app');
script('data', 'jquery.dataTables.min');
script('data', 'jquery.csv.min');
script('data', 'highcharts/highcharts');
script('data', 'highcharts/modules/data');
script('data', 'highcharts/modules/exporting');
script('data', 'highcharts/modules/export-data');
style('data', 'jquery.dataTables.min');
?>

<header>
    <div id="header">
        <div id="header-left">
            <a href="..."
               title="" id="nextcloud">
                <div class="logo logo-icon"></div>
                <h1 class="header-appname">
                    Nextcloud Analytics - Demo Share
                </h1>
            </a>
        </div>

        <!--<div id="header-right" class="header-right">
			<a href="111" id="download" class="button">
				<span class="icon icon-public"></span>
				<span id="download-text"><?php p($l->t('Subscribe')) ?></span>
			</a>
			&nbsp;
			<a href="222" id="download" class="button">
				<span class="icon icon-download"></span>
				<span id="download-text"><?php p($l->t('Download')) ?></span>
			</a>
		</div>-->
    </div>
</header>
<div id="app-content">
    <?php print_unescaped($this->inc('part.content')); ?>
</div>