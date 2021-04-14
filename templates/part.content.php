<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */
?>
<div id="analytics-content" style="width:100%; padding: 20px 5%;" hidden>
    <input type="hidden" name="sharingToken" value="<?php p($_['token']); ?>" id="sharingToken">
    <input type="hidden" name="dataset" value="" id="datasetId">
    <input type="hidden" name="advanced" value="false" id="advanced">
    <h2 id="reportHeader"></h2>
    <h3 id="reportSubHeader" hidden></h3>
    <div id="reportPlaceholder"></div>
    <div id="reportMenuBar" style="position: relative; height: 24px;">
        <span id="reportMenuIcon" class="analytics-options icon-more has-tooltip"
              title="<?php p($l->t('Options')); ?>"></span>
        <div id="reportMenu" class="popovermenu" style="top: 33px; right: -5px;">
            <ul id="reportMenuMain">
                <li id="saveIcon">
                    <button>
                        <span class="icon-analytics-save-warning"
                              title="<?php p($l->t('Report was changed - Press here to save the current state')); ?>"></span>
                        <span><?php p($l->t('Save view')); ?></span>
                    </button>
                </li>
                <li>
                    <button id="drilldownIcon">
                        <span class="icon-analytics-drilldown"></span>
                        <span><?php p($l->t('Drilldown')); ?></span>
                    </button>
                </li>
                <li>
                    <button id="chartOptionsIcon">
                        <span class="icon-analytics-chart-options"></span>
                        <span><?php p($l->t('Chart options')); ?></span>
                    </button>
                </li>
                <li>
                    <button id="forecastIcon">
                        <span class="icon-analytics-forecast"></span>
                        <span><?php p($l->t('Forecast')); ?></span>
                    </button>
                </li>
            </ul>
            <ul id="reportMenuForecast" style="display: none !important;">
                <li id="backIcon">
                    <button>
                        <span class="icon-view-previous"></span>
                        <span><?php p($l->t('Back to the menu')); ?></span>
                    </button>
                </li>
                <li>
                    <span class="menuitem">
                       <input id="check1" type="checkbox" class="checkbox"/>
                       <label for="check1"><?php p($l->t('Linear Regression')); ?></label>
                   </span>
                </li>
            </ul>
        </div>
        <div id="addFilterIcon" class="analytics-options icon-analytics-filter-add has-tooltip"
             title="<?php p($l->t('Filter')); ?>"></div>
        <div id="filterVisualisation" style="display: inline-block; float: right;"></div>
    </div>
    <div id="filterBar" style="" hidden>
        <div id="optionContainer" hidden>
            <div class="analytics-optoins icon-analytics-save-warning has-tooltip"
                 title="<?php p($l->t('Report was changed - Press here to save the current state')); ?>"></div>
            <div v-tooltip="'You have new messages.'" class="analytics-options icon-analytics-options has-tooltip"
                 title="<?php p($l->t('Options')); ?>"></div>
        </div>
        <div id="filterContainer" hidden>
            <div class="analytics-options icon-analytics-drilldown has-tooltip"
                 title="<?php p($l->t('Drilldown')); ?>"></div>
            <div class="analytics-options icon-analytics-filter-add has-tooltip"
                 title="<?php p($l->t('Filter')); ?>"></div>
        </div>
    </div>
    <div id="chartContainer">
        <canvas id="myChart"></canvas>
    </div>
    <div id="chartLegendContainer">
        <div id="chartLegend" class="icon icon-menu"><?php p($l->t('Legend')); ?></div>
    </div>
    <div id="tableSeparatorContainer"></div>
    <table id="tableContainer"></table>
    <div id="noDataContainer" hidden>
        <?php p($l->t('No data found')); ?>
    </div>
</div>
<div id="analytics-intro" style="padding: 30px" hidden>
    <h2><?php p($l->t('Analytics')); ?></h2>
    <br>
    <h3><?php p($l->t('Favorites')); ?></h3>
    <div>
        <ul id="ulAnalytics" style="width: 100%;"></ul>
    </div>
    <br>
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
<div id="analytics-loading" style="width:100%; padding: 100px 5%;" hidden>
    <div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>
</div>


