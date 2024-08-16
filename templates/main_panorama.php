<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\Util;

Util::addStyle('analytics', 'style');
Util::addStyle('analytics', 'sharetabview');
Util::addStyle('analytics', '3rdParty/datatables.min');
Util::addStyle('files_sharing', 'icons');
Util::addStyle('analytics', 'print');
Util::addScript('analytics', 'panorama');
Util::addScript('analytics', 'visualization');
Util::addScript('analytics', 'navigation');
Util::addScript('analytics', 'sidebar');
Util::addScript('analytics', '3rdParty/datatables.min');
Util::addScript('analytics', '3rdParty/chart.umd');
Util::addScript('analytics', '3rdParty/chartjs-adapter-moment');
Util::addScript('analytics', '3rdParty/chartjs-plugin-datalabels.min');
Util::addScript('analytics', '3rdParty/chartjs-plugin-zoom.min');
Util::addScript('analytics', '3rdParty/moment');
Util::addScript('analytics', '3rdParty/cloner');
Util::addScript('analytics', 'userGuidance');
Util::addScript('analytics', '3rdParty/jspdf.umd.min');
Util::addScript('analytics', '3rdParty/html2canvas.min');
?>

<div id="app-navigation">
    <?php print_unescaped($this->inc('part.navigation')); ?>
    <?php print_unescaped($this->inc('part.settings')); ?>
</div>

<div id="app-content">
    <div id="loading">
        <i class="ioc-spinner ioc-spin"></i>
    </div>
    <?php print_unescaped($this->inc('part.content_panorama')); ?>
    <div id="byAnalytics" class="byAnalytics analyticsFullscreen">
        <img id="byAnalyticsImg" style="width: 33px; margin-right: 7px;margin-left: 10px;" src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'app-color.svg') ?>">
        <span style="font-size: 12px; line-height: 14px;">created with<br>Analytics</span>
    </div>
</div>
<?php print_unescaped($this->inc('part.sidebar')); ?>
<div>
    <?php print_unescaped($this->inc('part.templates')); ?>
</div>
