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
?>
<html class="ng-csp" data-placeholder-focus="false" lang="en" data-locale="en">
<head>
    <script nonce="<?php p($_['nonce']); ?>" defer src="<?php p($_['baseurl']); ?>/js/3rdParty/moment.js"></script>
    <script nonce="<?php p($_['nonce']); ?>" defer src="<?php p($_['baseurl']); ?>/js/3rdParty/cloner.js"></script>
    <script nonce="<?php p($_['nonce']); ?>" defer src="<?php p($_['baseurl']); ?>/js/appMin.js"></script>
    <script nonce="<?php p($_['nonce']); ?>" defer src="<?php p($_['baseurl']); ?>/js/visualization.js"></script>
    <script nonce="<?php p($_['nonce']); ?>" defer src="<?php p($_['baseurl']); ?>/js/3rdParty/chart.min.js"></script>
    <script nonce="<?php p($_['nonce']); ?>" defer src="<?php p($_['baseurl']); ?>/js/3rdParty/chartjs-adapter-moment.js"></script>
    <script nonce="<?php p($_['nonce']); ?>" defer src="<?php p($_['baseurl']); ?>/js/3rdParty/chartjs-plugin-datalabels.min.js"></script>
</head>
<input type="hidden" name="data" value="<?php p(json_encode($_['data'])); ?>" id="data">
<div id="app-content">

    <div id="chartContainer" style="min-width: 310px; height: 99%; width: 99%;"></div>

    <div id="analytics-warning" style="width:50%; padding: 50px">
        <h2>Analytics</h2>
        <br>
        <h3>Javascript issue</h3>
        <span>If you see this message, please disable AdBlock/uBlock for this domain (only).</span>
        <br>
        <span>The EasyPrivacy list is blocking some scripts because of a wildcard filter for "analytics"</span>
        <br>
        <br>
        <a href="https://github.com/Rello/analytics/wiki/EasyPrivacy-Blocklist"
           target="_blank">More Information …</a>
    </div>

</div>
</html>