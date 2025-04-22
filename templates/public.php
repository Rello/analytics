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
Util::addScript('analytics', 'userGuidance');
Util::addScript('analytics', '3rdParty/datatables.min');
Util::addScript('analytics', '3rdParty/chart.umd');
Util::addScript('analytics', '3rdParty/chartjs-plugin-funnel.min');
Util::addScript('analytics', '3rdParty/chartjs-adapter-moment');
Util::addScript('analytics', '3rdParty/chartjs-plugin-datalabels.min');
Util::addScript('analytics', '3rdParty/chartjs-plugin-zoom.min');
?>

<div id="app-content">
    <?php print_unescaped($this->inc('part.content')); ?>
</div>