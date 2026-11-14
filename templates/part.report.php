<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>
<div id="analytics-content-report" hidden>
    <input type="hidden" name="sharingToken" value="<?php p($_['token']); ?>" id="sharingToken">
    <input type="hidden" name="dataset" value="" id="datasetId">
    <div class="reportHeading">
        <div class="reportHeadingPrimary">
            <span id="reportHeader" class="reportHeader" role="heading" aria-level="2"></span>
        </div>
        <div class="reportHeadingSecondary">
            <span id="reportSubHeader" class="reportSubHeader" hidden></span>
            <span class="reportHeadingBrand" aria-hidden="true"></span>
        </div>
    </div>
    <div id="reportPlaceholder"></div>
    <div id="chartContainer">
        <div id="chartLegendContainer">
            <button id="chartLegend" class="icon icon-menu" type="button" aria-pressed="false"><?php // TRANSLATORS: Noun: the chart key whose visibility this button toggles.
                p($l->t('Legend')); ?></button>
        </div>
    </div>
    <div id="tableSeparatorContainer"></div>
    <div id="chartDataAlternative" class="hidden-visually" role="region" aria-label="<?php p($l->t('Data')); ?>" hidden></div>
    <template id="templateChartDataTable">
        <table id="chartDataTable">
            <caption></caption>
            <thead><tr>
                <th scope="col"><?php // TRANSLATORS: Noun: a named series of values plotted in a chart.
                    p($l->t('Series')); ?></th>
                <th scope="col"><?php // TRANSLATORS: Noun: the category or date identifying a chart data point.
                    p($l->t('Label')); ?></th>
                <th scope="col"><?php p($l->t('Value')); ?></th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </template>
    <table id="tableContainer"></table>
    <div id="noDataContainer" hidden>
        <?php p($l->t('No data found')); ?>
    </div>
    <div style="margin-bottom: 65px"></div>
    <div id="byAnalytics" class="byAnalytics" style="display: none;">
        <img id="byAnalyticsImg" style="width: 33px; margin-right: 7px;margin-left: 10px;" src="<?php echo image_path('analytics', 'app-color.svg') ?>">
        <span style="font-size: 12px; line-height: 14px;">created with<br>Analytics</span>
    </div>
</div>
