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
<h3 style="text-align: center;"><?php p($l->t('New Report Wizard')); ?></h3>

    <div class="table" style="display: table; width: 100%; max-width: 800px;">
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Choose a name for the report')); ?></div>
            <input style="display: table-cell;" id="sidebarReportName" class="sidebarInput">
            <div style="display: table-cell;">
                <?php p($l->t('Text variables can be used in the name or subheader.<br>They are replaced when the report is executed.')); ?>
                <br><br>'
                %lastUpdateDate%<br>
                %lastUpdateTime%<br>
                %currentDate%<br>
                %currentTime%<br>
                %owner%
            </div>
        </div>
        <div style="display: table-row;" id="sidebarReportSubheaderRow">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Subheader')); ?></div>
            <input style="display: table-cell;" id="sidebarReportSubheader" class="sidebarInput">
            <div style="display: table-cell;">
                <a id="sidebarReportSubheaderHint" title="<?php p($l->t('Variables')); ?>">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;" id="sidebarReportParentRow">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Report group')); ?></div>
            <select style="display: table-cell;" id="sidebarReportParent" class="sidebarInput">
                <option value="0"></option>
            </select>
            <div style="display: table-cell;">
                <a id="sidebarReportGroupHint" title="<?php p($l->t('Report group')); ?>">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Data source')); ?></div>
            <select style="display: table-cell;" id="sidebarReportDatasource" class="sidebarInput" disabled>
                <option value="0"><?php p($l->t('Report group')); ?></option>
                <option value=""></option>
                <option value="2"><?php p($l->t('Saved Data')); ?></option>
                <option value=""></option>
            </select>
            <div style="display: table-cell;">
                <a id="sidebarReportDatasourceHint" title="<?php p($l->t('Data source')); ?>">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;" id="sidebarReportDatasetRow">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Dataset')); ?></div>
            <select style="display: table-cell;" id="sidebarReportDataset" class="sidebarInput" disabled>
            </select>
            <div style="display: table-cell;">
                <a id="sidebarReportDatasetHint" title="<?php p($l->t('Dataset')); ?>">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>

    </div>
    <br>
    <div id="reportDatasourceSectionHeader"><h3><?php p($l->t('Data source options')); ?></h3></div>
    <div id="reportDatasourceSection" class="table" style="display: table; width: 100%; max-width: 500px;"></div>
    <div id="reportDimensionSectionHeader"><h3><?php p($l->t('Column headers')); ?></h3></div>
    <div id="reportDimensionSection" class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Column')); ?>&nbsp;1</div>
            <div style="display: table-cell;"><input id="sidebarReportDimension1" class="sidebarInput"></div>
            <div style="display: table-cell;">
                <a id="sidebarReportDimensionHint" title="<?php p($l->t('Dataset')); ?>">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Column')); ?>&nbsp;2</div>
            <div style="display: table-cell;"><input id="sidebarReportDimension2" class="sidebarInput"></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Value')); ?></div>
            <div style="display: table-cell;"><input id="sidebarReportValue" class="sidebarInput"></div>
        </div>
    </div>
    <br>
    <div id="reportVisualizationSectionHeader"><h3><?php p($l->t('Visualization')); ?></h3></div>
    <div id="reportVisualizationSection" class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell;  width: 100%;"><?php p($l->t('Display')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarReportVisualization" class="sidebarInput">
                    <option value="ct" selected><?php p($l->t('Chart') . ' & ' . $l->t('Table')); ?></option>
                    <option value="table"><?php p($l->t('Table')); ?></option>
                    <option value="chart"><?php p($l->t('Chart')); ?></option>
                </select>
            </div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Chart type')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarReportChart" class="sidebarInput">
                    <option value="" selected></option>
                    <option value="line"><?php p($l->t('Line')); ?></option>
                    <option value="datetime"><?php p($l->t('Timeline (date in column 2)')); ?></option>
                    <option value="column"><?php p($l->t('Bar')); ?></option>
                    <option value="area"><?php p($l->t('Area')); ?></option>
                    <option value="doughnut"><?php p($l->t('Doughnut')); ?></option>
                </select>
            </div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;  width: 100%;"><?php p($l->t('Chart options')); ?></div>
            <div style="display: table-cell;"><input id="sidebarReportChartOptions"
                                                     placeholder="<?php p($l->t('advanced')); ?>" class="sidebarInput">
            </div>
            <div style="display: table-cell;">
                <a target="_blank" rel="noreferrer noopener" title="<?php p($l->t('Open documentation')); ?>"
                   href="https://github.com/Rello/analytics/wiki/Advanced-chart-options">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;  width: 100%;"><?php p($l->t('Data options')); ?></div>
            <div style="display: table-cell;"><input id="sidebarReportDataOptions"
                                                     placeholder="<?php p($l->t('advanced')); ?>" class="sidebarInput">
            </div>
            <div style="display: table-cell;">
                <a target="_blank" rel="noreferrer noopener" title="<?php p($l->t('Open documentation')); ?>"
                   href="https://github.com/Rello/analytics/wiki/Advanced-chart-options">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
    </div>
    <br>
    <button id="sidebarReportUpdateButton" type="button" class="primary">
        <?php p($l->t('Update')); ?>
    </button>
    <button id="sidebarReportDeleteButton" type="button">
        <?php p($l->t('Delete')); ?>
    </button>
    <button id="sidebarReportExportButton" type="button">
        <?php p($l->t('Export')); ?>
    </button>

