<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\Util;

Util::addStyle('analytics', 'style');
Util::addStyle('analytics', '3rdParty/datatables.min');
Util::addScript('analytics', '3rdParty/moment.min');
Util::addScript('analytics', '3rdParty/cloner');
Util::addScript('analytics', 'app');
Util::addScript('analytics', 'filter');
Util::addScript('analytics', 'visualization');
Util::addScript('analytics', 'report');
Util::addScript('analytics', 'userGuidance');
Util::addScript('analytics', '3rdParty/datatables.min');
Util::addScript('analytics', '3rdParty/chart.umd');
Util::addScript('analytics', '3rdParty/chartjs-plugin-funnel.min');
Util::addScript('analytics', '3rdParty/chartjs-adapter-moment');
Util::addScript('analytics', '3rdParty/chartjs-plugin-datalabels.min');
Util::addScript('analytics', '3rdParty/chartjs-plugin-zoom.min');
?>

<div id="app-content">
    <div id="analytics-content-loading" style="width:100%; padding: 100px 5%;" hidden>
        <div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>
    </div>
	<?php print_unescaped($this->inc('part.menu')); ?>

	<?php print_unescaped($this->inc('part.intro')); ?>
	<?php print_unescaped($this->inc('part.report')); ?>
	<?php print_unescaped($this->inc('part.panorama')); ?>
	<?php print_unescaped($this->inc('part.dataset')); ?>

    <div id="analytics-warning" style="width:50%; padding: 50px">
        <h2><?php p($l->t('Analytics')); ?></h2>
        <br>
        <h3><?php p($l->t('Javascript issue')); ?></h3>
        <span><?php p($l->t('If you see this message, please disable AdBlock/uBlock for this domain (only).')); ?></span>
        <br>
        <span><?php p($l->t('The EasyPrivacy list is blocking some scripts because of a wildcard filter for *analytics*.')); ?></span>
        <br>
        <br>
        <a href="https://github.com/Rello/analytics/wiki/EasyPrivacy-Blocklist"
           target="_blank"><?php p($l->t('More Information …')); ?></a>
    </div>
</div>