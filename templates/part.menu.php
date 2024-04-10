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
<div id="reportMenuBar" style="position: relative; height: 24px;">
        <span id="reportMenuIcon" class="analytics-options icon-more has-tooltip"
              title="<?php p($l->t('Options')); ?>"></span>
    <div id="reportMenu" class="popovermenu" style="top: 33px; right: -5px;">
        <ul id="reportMenuMain">
            <li>
                <button id="reportMenuDrilldown">
                    <span class="icon-analytics-drilldown"></span>
                    <span><?php p($l->t('Drilldown')); ?></span>
                </button>
            </li>
            <li>
                <button id="reportMenuChartOptions">
                    <span class="icon-analytics-chart-options"></span>
                    <span><?php p($l->t('Chart options')); ?></span>
                </button>
            </li>
            <li>
                <button id="reportMenuTableOptions">
                    <span class="icon-analytics-tableOptions"></span>
                    <span><?php p($l->t('Table options')); ?></span>
                </button>
            </li>
            <li>
                <button id="reportMenuAnalysis">
                    <span class="icon-analytics-forecast"></span>
                    <span><?php p($l->t('Analysis')); ?></span>
                </button>
            </li>
            <li>
                <button id="reportMenuTranslate">
                    <span class="icon-analytics-translate"></span>
                    <span><?php p($l->t('Translate')); ?></span>
                </button>
            </li>
            <li>
                <button id="reportMenuRefresh">
                    <span class="icon-analytics-refresh"></span>
                    <span><?php p($l->t('Auto refresh')); ?></span>
                </button>
            </li>
            <li>
                <button id="reportMenuDownload">
                    <span class="icon-analytics-download"></span>
                    <span><?php p($l->t('Download chart')); ?></span>
                    <a id="downloadChartLink" href='' download="Chart.png" hidden>-</a>
                </button>
            </li>
            <li style="display: none;">
                <button id="reportMenuResetTableState">
                    <span class="icon-analytics-reset"></span>
                    <span><?php p($l->t('Reset table')); ?></span>
                </button>
            </li>
            <li class="action-separator"></li>
            <li id="reportMenuSave">
                <button>
                    <span class="icon-add" title="<?php p($l->t('Save as new report')); ?>"></span>
                    <span><?php p($l->t('Save as new report')); ?></span>
                </button>
            </li>
        </ul>
        <ul id="reportMenuSubAnalysis" style="display: none !important;">
            <li id="backIcon">
                <button>
                    <span class="icon-view-previous"></span>
                    <span><?php p($l->t('Back')); ?></span>
                </button>
            </li>
            <li>
                <button id="trendIcon">
                    <span><?php p($l->t('Trend')); ?></span>
                </button>
            </li>
            <li>
                <button id="aggregateIcon">
                    <span><?php p($l->t('Aggregate values')); ?></span>
                </button>
            </li>
            <li>
                <button id="disAggregateIcon">
                    <span><?php p($l->t('Disaggregate values')); ?></span>
                </button>
            </li>
            <li>
                <button id="linearRegressionIcon" class="menuitem" disabled>
                    <span><?php p($l->t('Linear Regression')); ?></span>
                </button>
            </li>
        </ul>
        <ul id="reportMenuSubRefresh" style="display: none !important;">
            <li id="backIcon2">
                <button href="#">
                    <span class="icon-view-previous"></span>
                    <span><?php p($l->t('Back')); ?></span>
                </button>
            </li>
            <li>
                <span class="menuitem">
                    <input type="radio" name="refresh" id="refresh0"
                           class="radio" checked>
                    <label for="refresh0" style="font-size: 13px;"><?php p($l->t('none')); ?></label>
                </span>
            </li>
            <li>
                <span class="menuitem">
                    <input type="radio" name="refresh" id="refresh1"
                           class="radio">
                    <label for="refresh1" style="font-size: 13px;"><?php p($l->t('1 min')); ?></label>
                </span>
            </li>
            <li>
                <span class="menuitem">
                    <input type="radio" name="refresh" id="refresh10"
                           class="radio">
                    <label for="refresh10" style="font-size: 13px;"><?php p($l->t('10 min')); ?></label>
                </span>
            </li>
            <li>
                <span class="menuitem">
                    <input type="radio" name="refresh" id="refresh30"
                           class="radio">
                    <label for="refresh30" style="font-size: 13px;"><?php p($l->t('30 min')); ?></label>
                </span>
            </li>
        </ul>
        <ul id="reportMenuSubTranslate" style="display: none !important;">
            <li id="backIcon3">
                <button>
                    <span class="icon-view-previous"></span>
                    <span><?php p($l->t('Back')); ?></span>
                </button>
            </li>
            <li>
                <span class="menuitem icon-analytics-translate">
                    <select id="translateLanguage" class="menueInput" style="margin-left: 0px;">
                    </select>
                </span>
            </li>
       </ul>
    </div>
    <div id="saveIcon" class="analytics-options icon-analytics-save-warning has-tooltip"
         title="<?php p($l->t('Report was changed - Press here to save the current state')); ?>"></div>

    <div id="addFilterIcon" class="analytics-options icon-analytics-filter-add has-tooltip"
         title="<?php p($l->t('Filter')); ?>"></div>
    <div id="filterVisualisation" style="display: inline-block; float: right;"></div>
</div>
