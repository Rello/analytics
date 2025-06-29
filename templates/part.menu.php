<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>
<div id="menuBar">
    <div id="optionsMenuIcon" class="analytics-options icon-analytics-more has-tooltip"
         title="<?php p($l->t('Options')); ?>"></div>
    <div id="fullscreenToggle" class="analytics-options icon-analytics-fullscreen"></div>

    <div id="optionsMenu" class="popovermenu">
        <ul id="optionsMenuMainReport" style="display: none !important;">
            <li>
                <button id="optionsMenuColumnSelection" class="has-tooltip" title="<?php p($l->t('Select columns')); ?>">
                    <span class="icon-analytics-drilldown"></span>
                    <span><?php p($l->t('Column selection')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuSort" class="has-tooltip" title="<?php p($l->t('Sort data ascending or descending')); ?>">
                    <span class="icon-analytics-sort"></span>
                    <span><?php p($l->t('Sort order')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuTopN" class="has-tooltip" title="<?php p($l->t('Show top N items and group others together')); ?>">
                    <span class="icon-analytics-group"></span>
                    <span><?php p($l->t('Top N')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuTimeAggregation" class="has-tooltip" title="<?php p($l->t('Aggregate daily data into weeks, months, or years')); ?>">
                    <span class="icon-analytics-timeAggregation"></span>
                    <span><?php p($l->t('Time aggregation')); ?></span>
                </button>
            </li>
            <li class="action-separator"></li>
            <li>
                <button id="optionsMenuChartOptions">
                    <span class="icon-analytics-chart-options"></span>
                    <span><?php p($l->t('Chart options')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuTableOptions">
                    <span class="icon-analytics-tableOptions"></span>
                    <span><?php p($l->t('Table options')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuAnalysis">
                    <span class="icon-analytics-forecast"></span>
                    <span><?php p($l->t('Analysis')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuTranslate">
                    <span class="icon-analytics-translate"></span>
                    <span><?php p($l->t('Translate')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuRefresh">
                    <span class="icon-analytics-refresh"></span>
                    <span><?php p($l->t('Auto refresh')); ?></span>
                </button>
            </li>
            <li class="action-separator"></li>
            <li>
                <button id="optionsMenuDownload">
                    <span class="icon-analytics-download"></span>
                    <span><?php p($l->t('Download chart')); ?></span>
                    <a id="downloadChartLink" href='' download="Chart.png" hidden>-</a>
                </button>
            </li>
            <li id="optionsMenuSave">
                <button>
                    <span class="icon-add" title="<?php p($l->t('Save as new report')); ?>"></span>
                    <span><?php p($l->t('Save as new report')); ?></span>
                </button>
            </li>
        </ul>
        <ul id="optionsMenuMainPanorama" style="display: none !important;">
            <li>
                <button id="optionsMenuPanoramaEdit">
                    <span id="optionsMenuEditIcon" class="icon-rename"></span>
                    <span id="optionsMenuEditText"><?php p($l->t('Edit')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuPanoramaLayout">
                    <span class="icon-analytics-drilldown"></span>
                    <span><?php p($l->t('Change layout')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuPanoramaDeletePage">
                    <span class="icon-delete"></span>
                    <span><?php p($l->t('Delete current page')); ?></span>
                </button>
            </li>
            <li>
                <button id="optionsMenuPanoramaPdf">
                    <span class="icon-analytics-pdf"></span>
                    <span><?php p($l->t('Export as PDF')); ?></span>
                </button>
            </li>

        </ul>
        <ul id="optionsMenuSubAnalysis" style="display: none !important;">
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
        <ul id="optionsMenuSubRefresh" style="display: none !important;">
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
        <ul id="optionsMenuSubTranslate" style="display: none !important;">
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
    <div id="saveIcon" style="display: none;" class="analytics-options icon-analytics-save-warning has-tooltip"
         title="<?php p($l->t('Report was changed - Press here to save the current state')); ?>"></div>

    <div id="addFilterIcon" class="analytics-options icon-analytics-filter has-tooltip"
         title="<?php p($l->t('Filter')); ?>"></div>
    <div id="filterVisualisation" style="display: inline-block; float: right;"></div>
</div>
