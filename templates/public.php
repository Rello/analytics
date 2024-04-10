<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

use OCP\Util;

Util::addStyle('analytics', 'style');
Util::addStyle('analytics', '3rdParty/datatables.min');
Util::addScript('analytics', '3rdParty/moment');
Util::addScript('analytics', '3rdParty/cloner');
Util::addScript('analytics', 'app');
Util::addScript('analytics', 'filter');
Util::addScript('analytics', 'visualization');
Util::addScript('analytics', 'userGuidance');
Util::addScript('analytics', '3rdParty/datatables.min');
Util::addScript('analytics', '3rdParty/chart.min');
Util::addScript('analytics', '3rdParty/chartjs-adapter-moment');
Util::addScript('analytics', '3rdParty/chartjs-plugin-datalabels.min');
Util::addScript('analytics', '3rdParty/chartjs-plugin-zoom.min');
?>

<div id="app-content">
    <?php print_unescaped($this->inc('part.content')); ?>
</div>