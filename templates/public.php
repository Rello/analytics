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
Util::addScript('analytics', 'jquery.csv.min');
Util::addScript('analytics', 'highcharts/highcharts');
Util::addScript('analytics', 'highcharts/modules/data');
//Util::addScript('analytics', 'highcharts/modules/exporting');
//Util::addScript('analytics', 'highcharts/modules/export-data');
?>

<header>
    <div id="header">
        <div id="header-left">
            <a href="..."
               title="" id="nextcloud">
                <div class="logo logo-icon"></div>
                <h1 class="header-appname">
                    Data Analytics - Public Share
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