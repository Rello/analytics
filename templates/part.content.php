<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */
?>
<div id="analytics-content" style="width:100%; padding: 20px 40px;" hidden>
    <input type="hidden" name="sharingToken" value="<?php p($_['token']); ?>" id="sharingToken">
    <input type="hidden" name="dataset" value="" id="datasetId">
    <input type="hidden" name="advanced" value="false" id="advanced">
    <h2 id="reportHeader"></h2>
    <h3 id="reportSubHeader" style="display: none"></h3>
    <div id="reportPlaceholder"></div>
    <div id="filterContainer" style="display: none">
        <div id="drilldownIcon" class="icon-analytics-drilldown has-tooltip"
             title="<?php p($l->t('Change drilldown')); ?>"></div>
        <div id="addFilterIcon" class="icon-analytics-filter-add" title="<?php p($l->t('Add filter')); ?>"></div>
        <input id="filterOptions" type="hidden" style="display: inline-block; float: right;">
        <input id="filterDimensions" type="hidden" style="display: inline-block; float: right;">
        <div id="filterVisualisation" style="display: inline-block; float: right;">
        </div>
    </div>
    <div id="chartContainer">
        <canvas id="myChart"></canvas>
    </div>
    <div id="chartMenuContainer">
        <div id="chartLegend" class="icon icon-menu"><?php p($l->t('Legend')); ?></div>
    </div>
    <table id="tableContainer"></table>
    <div id="noDataContainer" style="display: none">
        <?php p($l->t('No data found')); ?>
    </div>
</div>
<div id="analytics-intro" style="width:50%; padding: 50px" hidden>
    <h2><?php p($l->t('Analytics')); ?></h2>
    <br>
    <h3><?php p($l->t('Quickstart')); ?></h3>
    <a href="#" id="createDemoReport">-&nbsp;<?php p($l->t('Create demo report on external CSV data')); ?></a>
    <br>
    <h3><?php p($l->t('Recent')); ?></h3>
    <span>-&nbsp;<?php p($l->t('coming soon')); ?></span>
    <br>
    <h3><?php p($l->t('Updates')); ?></h3>
    <span>-&nbsp;<?php p($l->t('coming soon')); ?></span>
</div>
<div id="analytics-warning" style="width:50%; padding: 50px">
    <h2><?php p($l->t('Analytics')); ?></h2>
    <br>
    <h3>Javascript issue</h3>
    <span>If you see this message, please disable AdBlock/uBlock for this domain (only).</span>
    <br>
    <span>The EasyPrivacy list is blocking some scripts because of a wildcard filter for "analytics"</span>
    <br>
    <br>
    <a href="https://github.com/Rello/analytics/wiki/EasyPrivacy-Blocklist"
       target="_blank"><?php p($l->t('More Information ...')); ?></a>
</div>
