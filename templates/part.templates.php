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

<template id="templateReport">
    <div class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Name')); ?></div>
            <input style="display: table-cell;" id="sidebarReportName" class="sidebarInput">
            <div style="display: table-cell;">
                <a id="sidebarReportNameHint" title="<?php p($l->t('Variables')); ?>">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
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
                    <div class="icon-add" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;" id="sidebarReportDatasourceRow">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Data source')); ?></div>
            <select style="display: table-cell;" id="sidebarReportDatasource" class="sidebarInput" disabled>
                <option value="0"><?php p($l->t('Report group')); ?></option>
                <option value=""></option>
                <option value="2"><?php p($l->t('Saved Data')); ?></option>
                <option value=""></option>
            </select>
        </div>
        <div style="display: table-row;" id="sidebarReportDatasetRow">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Dataset')); ?></div>
            <select style="display: table-cell;" id="sidebarReportDataset" class="sidebarInput" disabled>
            </select>
        </div>
    </div>
    <br>
    <div id="reportDatasourceSectionHeader" class="sidebarHeaderClosed"><h3 id="reportDatasourceSectionHeaderH3"
                                                                            class="sidebarPointer"><?php p($l->t('Data source options')); ?></h3>
    </div>
    <div id="reportDatasourceSection" style="display: none; width: 100%; max-width: 500px;"></div>
    <div id="reportDimensionSectionHeader" class="sidebarHeaderClosed"><h3 id="reportDimensionSectionHeaderH3"
                                                                           class="sidebarPointer"><?php p($l->t('Column headers')); ?></h3>
    </div>
    <div id="reportDimensionSection" style="display: none; width: 100%; max-width: 500px;">
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
        <br>
    </div>
    <div id="reportVisualizationSectionHeader" class="sidebarHeaderClosed"><h3 id="reportVisualizationSectionHeaderH3"
                                                                               class="sidebarPointer"><?php p($l->t('Visualization')); ?></h3>
    </div>
    <div id="reportVisualizationSection" style="display: none; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell;  width: 100%;"><?php p($l->t('Display')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarReportVisualization" class="sidebarInput">
                    <option value="ct" selected><?php p($l->t('Chart & Table')); ?></option>
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
                    <option value="line"><?php // TRANSLATORS Short description for a 'Line Chart'
                        p($l->t('Line')); ?></option>
                    <option value="datetime"><?php p($l->t('Timeline (date in column 2)')); ?></option>
                    <option value="column"><?php // TRANSLATORS Short description for a 'Bar Chart'
                        p($l->t('Bar')); ?></option>
                    <option value="columnSt">&nbsp;<?php // TRANSLATORS Short description for a 'Stacked bar Chart'
                        p($l->t('Bar - Stacked')); ?></option>
                    <option value="columnSt100">
                        &nbsp;<?php // TRANSLATORS Short description for a 'Stacked bar Chart with 100% relations'
                        p($l->t('Bar - Stacked 100%%')); ?></option>
                    <option value="area"><?php // TRANSLATORS Short description for an 'Area Chart'
                        p($l->t('Area')); ?></option>
                    <option value="areaSt100">
                        &nbsp;<?php // TRANSLATORS Short description for an 'Area Chart with 100% relations'
                        p($l->t('Area - 100%%')); ?></option>
                    <option value="doughnut"><?php // TRANSLATORS Short description for a 'Doughnut Chart'
                        p($l->t('Doughnut')); ?></option>
                </select>
            </div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;  width: 100%; vertical-align: middle;"><?php p($l->t('Chart options')); ?></div>
            <div style="display: table-cell;"><textarea id="sidebarReportChartOptions"
                                                        placeholder="<?php p($l->t('Advanced')); ?>"
                                                        class="sidebarInput" row="1"></textarea>
            </div>
            <div style="display: table-cell; vertical-align: middle;">
                <a target="_blank" rel="noreferrer noopener" title="<?php p($l->t('Open documentation')); ?>"
                   href="https://github.com/Rello/analytics/wiki/Advanced-chart-options">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%; vertical-align: middle;"><?php p($l->t('Data options')); ?></div>
            <div style="display: table-cell;"><textarea id="sidebarReportDataOptions"
                                                        placeholder="<?php p($l->t('Advanced')); ?>"
                                                        class="sidebarInput" row="1"></textarea>
            </div>
            <div style="display: table-cell; vertical-align: middle;">
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

</template>

<template id="templateDataset">
    <div class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Name')); ?></div>
            <input style="display: table-cell;" id="sidebarDatasetName" class="sidebarInput">
        </div>
    </div>
    <br>
    <div id="datasetDimensionSectionHeader"><h3><?php p($l->t('Column headers')); ?></h3></div>
    <div id="datasetDimensionSection" class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Column')); ?>&nbsp;1</div>
            <div style="display: table-cell;"><input id="sidebarDatasetDimension1" class="sidebarInput"></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Column')); ?>&nbsp;2</div>
            <div style="display: table-cell;"><input id="sidebarDatasetDimension2" class="sidebarInput"></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Value')); ?></div>
            <div style="display: table-cell;"><input id="sidebarDatasetValue" class="sidebarInput"></div>
        </div>
    </div>
    <br>
    <div id="datasetStatusSection" class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Reports')); ?></div>
            <div style="display: table-cell;">
                <div id="sidebarDatasetStatusReports" class="sidebarInput"></div>
            </div>
        </div>
        <br>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Number of records')); ?></div>
            <div style="display: table-cell;"><span id="sidebarDatasetStatusRecords" class="sidebarInput"></span>
            </div>
        </div>
    </div>
    <div style="width:100%; float: left;">
        <br>
        <button id="sidebarDatasetUpdateButton" type="button" class="primary">
            <?php p($l->t('Update')); ?>
        </button>
        <button id="sidebarDatasetDeleteButton" type="button">
            <?php p($l->t('Delete')); ?>
        </button>
        <!--        <button id="sidebarDatasetExportButton" type="button">
            <?php /*p($l->t('Export')); */ ?>
        </button>
-->    </div>
</template>

<template id="templateData">
    <div class="sidebarHeaderOpened"><h3 id="dataManualSectionHeaderH3"
                                         class="sidebarPointer"><?php p($l->t('Manual entry')); ?></h3></div>
    <div id="dataManualSection" style="display: table; width: 100%; max-width: 500px;">
        <div class="table" style="display: table; width: 100%; max-width: 500px;">
            <div style="display: table-row;">
                <div id="DataTextDimension1"
                     style="display: table-cell; width: 100%;"><?php p($l->t('Object')); ?></div>
                <input style="display: table-cell;" class="sidebarInput" id="DataDimension1" autocomplete="off"
                       data-dropDownListIndex="0">
                <div style="display: table-cell;">
                    <a id="sidebarDataDimension1Hint" title="<?php p($l->t('Variables')); ?>">
                        <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                    </a></div>
            </div>
            <div style="display: table-row;">
                <div id="DataTextDimension2" style="display: table-cell; width: 120px;"><?php p($l->t('Date')); ?></div>
                <input style="display: table-cell;" class="sidebarInput" id="DataDimension2" autocomplete="off"
                       data-dropDownListIndex="1">
                <div style="display: table-cell;">
                    <a id="sidebarDataDimension2Hint" title="<?php p($l->t('Variables')); ?>">
                        <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                    </a></div>
            </div>
            <div style="display: table-row;">
                <div id="DataTextValue"
                     style="display: table-cell; width: 120px;"><?php p($l->t('Value')); ?></div>
                <input style="display: table-cell;" class="sidebarInput" id="DataValue">
            </div>
        </div>
        <br>
        <button id="updateDataButton" type="button" class="primary">
            <?php p($l->t('Save data')); ?>
        </button>
        <button id="deleteDataButton" type="button">
            <?php p($l->t('Delete data')); ?>
        </button>
        <br>
        <br>
    </div>
    <div class="sidebarHeaderClosed"><h3 id="dataImportSectionHeaderH3"
                                         class="sidebarPointer"><?php // TRANSLATORS Noun shown in a button
            p($l->t('Import')); ?></h3></div>
    <div id="dataImportSection" style="display: none; width: 100%; max-width: 500px;">
        <button id="importDataFileButton" type="button">
            <?php p($l->t('From file')); ?>
        </button>
        <button id="importDataClipboardButton" type="button">
            <?php p($l->t('From clipboard')); ?>
        </button>
        <br>
        <textarea id="importDataClipboardText" rows="5" cols="150" hidden></textarea>
        <br>
        <button id="importDataClipboardButtonGo" type="button" hidden>
            <?php // TRANSLATORS Noun shown in a button
            p($l->t('Import')); ?>
        </button>
        <br>
    </div>

    <div class="sidebarHeaderClosed"><h3 id="dataApiSectionHeaderH3"
                                         class="sidebarPointer"><?php p($l->t('REST API')); ?></h3></div>
    <div id="dataApiSection" style="display: none; width: 100%; max-width: 500px;">
        <div id="apiLink" class="clipboard-button icon icon-clippy" style="width: 20px;"></div>
        <input type="hidden" id="DataApiDataset">
        <br>
    </div>
    <div class="sidebarHeaderClosed"><h3 id="dataAdvancedSectionHeaderH3"
                                         class="sidebarPointer"><?php p($l->t('Data load')); ?></h3></div>
    <div id="dataAdvancedSection" style="display: none; width: 100%; max-width: 500px;">
        <button id="advancedButton" type="button">
            <?php p($l->t('Advanced configuration')); ?>
        </button>
    </div>
</template>

<template id="templateThreshold">
    <h1><?php p($l->t('Thresholds can trigger notifications and color coding in reports.')); ?></h1>
    <a href="https://github.com/Rello/analytics/wiki/Thresholds"
       target="_blank"><?php p($l->t('More information …')); ?></a>
    <br>
    <br>
    <div class="table" style="display: table;">
        <div style="display: table-row;">
            <div id="sidebarThresholdTextDimension1"
                 style="display: table-cell; width: 100%;"><?php p($l->t('Object')); ?></div>
            <div style="display: table-cell;">
                <input id="sidebarThresholdDimension1" class="sidebarInput"
                       placeholder="<?php p($l->t('single value or *')); ?>" autocomplete="off"
                       data-dropDownListIndex="0"></div>
            <div style="display: table-cell;">
                <a id="sidebarThresholdHint" title="<?php p($l->t('Variables')); ?>">
                    <div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>
                </a></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;"><?php p($l->t('Operator')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarThresholdOption" class="sidebarInput">
                    <option value="=" selected><?php // TRANSLATORS description in a dropdown; limited space
                        p($l->t('= equal')); ?></option>
                    <option value=">"><?php // TRANSLATORS description in a dropdown; limited space
                        p($l->t('> greater')); ?></option>
                    <option value="<"><?php // TRANSLATORS description in a dropdown; limited space
                        p($l->t('< less')); ?></option>
                    <option value="<="><?php // TRANSLATORS description in a dropdown; limited space
                        p($l->t('<= less equal')); ?></option>
                    <option value=">="><?php // TRANSLATORS description in a dropdown; limited space
                        p($l->t('>= greater equal')); ?></option>
                    <option value="!="><?php // TRANSLATORS description in a dropdown; limited space
                        p($l->t('!= not equal')); ?></option>
                </select>
            </div>
        </div>
        <div style="display: table-row;">
            <div id="sidebarThresholdTextValue"
                 style="display: table-cell; width: 120px;"><?php p($l->t('Value')); ?></div>
            <div style="display: table-cell;"><input id="sidebarThresholdValue" class="sidebarInput"></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;"><?php p($l->t('Severity')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarThresholdSeverity" class="sidebarInput">
                    <option value="1" selected><?php p($l->t('Notification')); ?></option>
                    <option value="2"><?php p($l->t('Red')); ?></option>
                    <option value="3"><?php p($l->t('Yellow')); ?></option>
                    <option value="4"><?php p($l->t('Green')); ?></option>
                </select>
            </div>
        </div>
    </div>
    <br>
    <button id="sidebarThresholdCreateButton" type="button" class="primary">
        <?php p($l->t('Save threshold')); ?>
    </button>
    <br>
    <br>
    <div id="sidebarThresholdCreateNewHeader"><h3><?php // TRANSLATORS Noun shown in a button. German "Weitere"
            p($l->t('Others')); ?></h3></div>
    <button id="sidebarThresholdCreateNewButton" type="button" class="secondary">
        <?php p($l->t('Notification for new records')); ?>
    </button>
    <br>
    <br>
    <div id="sidebarThresholdList"></div>
</template>

<template id="templateDataload">
    <div style="display: flex;">
        <div style="width: 25%;">
            <div class="wizardHeader"><?php p($l->t('Selection')); ?></div>
            <h3 style="padding-top: 20px;"><?php p($l->t('Data load')); ?></h3>
            <div style="display: table-row;">
                <div style="display: table-cell;">
                    <div id="createDataloadButton" class="icon-add icon sidebarPointer" style="padding: 0 0px 0 44px;">
                    </div>
                </div>
                <div style="display: table-cell;">
                    <select id="datasourceSelect" class="input150">
                    </select>
                </div>
            </div>
            <div id="dataLoadList"></div>
            <h3 style="padding-top: 20px;"><?php p($l->t('Data deletion')); ?></h3>
            <div style="display: table-row;">
                <div style="display: table-cell;">
                    <div id="createDataDeletionButton" class="icon-add icon sidebarPointer"
                         style="padding: 0 0px 0 44px;">
                    </div>
                </div>
                <div style="display: table-cell;"><?php p($l->t('New deletion')); ?>
                </div>
            </div>
            <div id="dataDeleteList"></div>
            <br>
            <br>
            <a href="https://github.com/Rello/analytics/wiki/Automation-(load-&-delete)"
               target="_blank"><?php p($l->t('More information …')); ?></a>
        </div>
        <div style="width: 5%;">
            <div class="wizardHeader icon-view-next"></div>
        </div>
        <div style="width: 40%;">
            <div class="wizardHeader"><?php p($l->t('Setting')); ?></div>
            <div id="dataloadDetail">
                <div id="dataloadDetailHeader" hidden>
                    <div style="display: table-row;">
                        <div style="display: table-cell; width: 100%"><?php p($l->t('Title')); ?></div>
                        <input class="sidebarInput" id="dataloadName" style="display: table-cell;">
                        <div style="display: table-cell; min-width: 20px;"></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; width: 100%"></div>
                        <div id="dataloadType" class="userGuidance" style="display: table-cell;"></div>
                        <div style="display: table-cell; min-width: 20px;"></div>
                    </div>
                </div>
                <br>
                <div id="dataloadDetailItems">
                </div>
                <br>
                <div id="dataloadDetailDelete" hidden>
                    <!--                    <div style="display: table-row;"> //to be implemented later?
                        <div style="display: table-cell; width: 100%;"><?php /*p($l->t('Aggregation')); */ ?></div>
                        <select class="sidebarInput" id="aggregation" style="display: table-cell;">
                            <option value="overwrite" selected><?php /*p($l->t('Overwrite')); */ ?></option>
                            <option value="summation"><?php /*p($l->t('Summation')); */ ?></option>
                        </select></div>
-->
                    <div style="display: table-row;">
                        <div style="display: table-cell; width: 100%;"><?php p($l->t('Delete data before load')); ?></div>
                        <select class="sidebarInput" id="delete" style="display: table-cell;">
                            <option value="false"><?php p($l->t('No')); ?></option>
                            <option value="true"><?php p($l->t('Yes')); ?></option>
                        </select>
                        <div style="display: table-cell; min-width: 20px;"></div>
                    </div>
                </div>
                <div id="dataloadDetailButtons" hidden>
                    <button id="dataloadDeleteButton" style="padding: 15px;" title="<?php p($l->t('Delete')); ?>"
                            class="icon-delete"></button>
                    <button id="dataloadCopyButton" style="padding: 15px;" title="<?php p($l->t('Copy')); ?>"
                            class="icon-analytics-copy"></button>
                    <button id="dataloadUpdateButton" style="padding: 15px;" title="<?php p($l->t('Update')); ?>"
                            class="icon-checkmark"></button>
                </div>
            </div>
        </div>
        <div style="width: 5%;">
            <div class="wizardHeader icon-view-next"></div>
        </div>
        <div style="width: 24%;">
            <div class="wizardHeader"><?php p($l->t('Execution')); ?></div>
            <div id="dataloadRun" hidden>
                <button id="dataloadExecuteButton"><?php p($l->t('Load now')); ?></button>
                <input type="checkbox" id="testrunCheckbox" class="checkbox" checked><label
                        for="testrunCheckbox"><?php p($l->t('Test run')); ?></label>
                <br><br>
                <span><?php // TRANSLATORS description of a textbox
                    p($l->t('Schedule in background')); ?></span>
                <br>
                <select id="dataloadSchedule" class="input150">
                    <option value="" selected><?php p($l->t('Not scheduled')); ?></option>
                    <option value="d"><?php p($l->t('Daily')); ?></option>
                    <option value="e"><?php p($l->t('Daily - End of day')); ?></option>
                    <option value="b"><?php p($l->t('Daily - Start of day')); ?></option>
                    <option value="h"><?php p($l->t('Hourly')); ?></option>
                </select>
                <br><br>
                <span><?php p($l->t('Load via OCC command:')); ?></span>
                <br>
                <span id="dataloadOCC"></span>
                <br>
                <br>
                <a href="https://github.com/Rello/analytics/wiki/Scheduled-dataloads"
                   target="_blank"><?php p($l->t('More information …')); ?></a>
            </div>
        </div>
    </div>

</template>

<template id="templateNavigationMenu">
    <div id="navigationMenu" class="app-navigation-entry-menu">
        <ul>
            <li><a href="#" id="navigationMenuEdit"><span class="icon-rename"></span><span></span></a></li>
            <li><a href="#" id="navigationMenuAdvanced"><span
                            class="icon-category-customization"></span><span></span></a></li>
            <li><a href="#" id="navigationMenuNewGroup"><span class="icon-add"></span><span></span></a></li>
            <li>
                <a href="#" id="navigationMenueFavorite">
                    <span class="icon icon-star"></span>
                    <span></span>
                </a>
            </li>
            <li id="navigationMenueSeparator" class="action-separator"></li>
            <li><a href="#" id="navigationMenuDelete"><span
                            class="icon-delete"></span><span><?php p($l->t('Delete')); ?></span></a></li>
            <li><a href="#" id="navigationMenuUnshare"><span
                            class="icon-close"></span><span><?php p($l->t('Unshare')); ?></span></a></li>
        </ul>
    </div>
</template>

<template id="templateSidebarShare">
    <input type="text" id="shareInput" placeholder="<?php p($l->t('Name')); ?>"
           style="width: 100%; margin-bottom: 0px;" ;>
    <ul id="shareSearchResult" class="shareWithList" style="display: none;">
    </ul>
    <div class="linkShareView subView">
        <div id="linkShareList" class="shareWithList">
        </div>
    </div>
    <div class="shareeListView subView">
        <div id="shareeList" class="shareWithList"></div>
    </div>
    <div style="height: 150px"></div>
</template>

<template id="templateSidebarShareShareeRow">
    <li id="row">
        <div id="avatar" class="avatar imageplaceholderseed"
             style="width: 32px; height: 32px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 32px; font-size: 17.6px;"></div>
        <span id="username" class="username" data-share-type="" data-user=""></span>
        <span class="sharingOptionsGroup">
        <span id="icon" class="" style="opacity: 0.5;"></span>
            <div id="shareMenu" class="share-menu">
                <a id="icon-more" class="icon icon-more"></a>
                <div class="popovermenu menu" style="display: none;">
                    <ul>
                        <li>
                            <span class="menuitem">
                                <input type="checkbox" name="shareEditing" id="shareEditing"
                                       class="checkbox showPasswordCheckbox">
                                <label for="shareEditing"><?php p($l->t('can navigate')); ?></label>
                            </span>
                        </li>
                        <li class="action-separator"></li>
                       <li>
                            <a href="#" class="unshare" id="deleteShare">
                                <span class="icon icon-close" id="deleteShareIcon"></span>
                                <span><?php p($l->t('Unshare')); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </span>
    </li>
</template>

<template id="templateSidebarShareLinkRow">
    <li id="row">
        <div class="avatar icon-public-white"></div>
        <span id="shareLinkTitle" class="username"></span>
        <span id="sharingOptionsGroupNew" class="sharingOptionsGroup">
            <a id="newLinkShare" class="icon-add icon new-share" href="#" data-share-type="3"></a>
        </span>
        <span id="sharingOptionsGroupMenu" class="sharingOptionsGroup">
            <a id="shareOpenDirect" target="_blank" hidden></a>
            <a id="shareClipboard" class="clipboard-button icon icon-clippy" target="_blank"></a>
            <textarea id="shareClipboardLink" hidden></textarea>
            <div class="share-menu">
                <a class="icon icon-more" id="moreIcon"></a>
                <div class="popovermenu menu" style="display: none;">
                    <ul>
                        <li>
                            <span class="menuitem">
                                <input type="checkbox" name="showPassword" id="showPassword"
                                       class="checkbox showPasswordCheckbox">
                                <label for="showPassword"><?php p($l->t('Password protection')); ?></label>
                            </span>
                        </li>
                        <li id="linkPassMenu" class="linkPassMenu hidden">
                            <span class="menuitem icon-password">
                                <input type="password" placeholder="<?php p($l->t('Password')); ?>"
                                       class="linkPassText">
                                <input id="linkPassSubmit" type="submit" value="" class="icon-confirm share-pass-submit"
                                       style="width: auto !important">
                            </span>
                        </li>
                        <li>
                            <span class="menuitem">
                                <input type="checkbox" name="shareEditing" id="shareEditing"
                                       class="checkbox showPasswordCheckbox">
                                <label for="shareEditing"><?php p($l->t('can navigate')); ?></label>
                            </span></li>
                        <li>
                            <span class="menuitem">
                                <input type="checkbox" name="shareChart" id="shareChart"
                                       class="checkbox showPasswordCheckbox">
                                <label for="shareChart"><?php p($l->t('Share chart for external website')); ?></label>
                            </span></li>
                         <li id="shareChartMenuHint" class="linkPassMenu hidden">
                            <span class="menuitem icon-info" style="white-space: initial;line-height: 20px;">
                                <?php p($l->t('To including a chart in an external website, that domain needs to be allowed (CSP policy)')); ?>
                            </span></li>
                          <li id="shareChartMenuCopy" class="linkPassMenu hidden">
                             <span class="menuitem icon-clippy">
                                 <a id="shareChartClipboard" target="_blank"><?php p($l->t('Copy link')); ?></a>
                                  <textarea id="shareChartLink" hidden></textarea>
                             </span>
                         </li>
                      <li id="shareChartMenuDomain" class="linkPassMenu hidden">
                            <span class="menuitem icon-timezone">
                                <input id="shareChartDomain" type="input"
                                       placeholder="<?php p($l->t('Domain of external site')); ?>"
                                       class="linkPassText">
                                <input id="shareChartSubmit" type="submit" value=""
                                       class="icon-confirm share-pass-submit"
                                       style="width: auto !important">
                            </span>
                        </li>
                        <li class="action-separator"></li>
                        <li>
                            <a href="#" class="unshare" id="deleteShare">
                                <span class="icon icon-close" id="deleteShareIcon"></span>
                                <span><?php p($l->t('Unshare')); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </span>
    </li>
</template>

<template id="templateTableOptions">
    <div class="tableOptionsLayout">
        <div class="dummy"></div>
        <div id="tableOptionsLayoutRows">
            <p><?php p($l->t('Rows')); ?></p>
            <div id="rows" class="columnSection"></div>
        </div>
        <div id="tableOptionsLayoutColumns">
            <p><?php p($l->t('Columns')); ?></p>
            <div id="columns" class="columnSection"></div>
        </div>
        <div id="tableOptionsLayoutMeasures">
            <p><?php // TRANSLATORS "Measures" means key figures and refers to the display area of a data table
                p($l->t('Measures')); ?></p>
            <div id="measures" class="columnSection"></div>
        </div>
        <div id="tableOptionsLayoutTotals">
            <p><?php p($l->t('Show totals')); ?></p>
            <div id="totalsSwitch">
                <label class="toggle-option">
                    <input type="radio" name="totalOption" value="true" checked><?php p($l->t('Yes')); ?>
                </label>
                <label class="toggle-option">
                    <input type="radio" name="totalOption" value="false"><?php p($l->t('No')); ?>
                </label></div>
        </div>
        <div id="tableOptionsLayoutAvailable">
            <p><?php p($l->t('Not required')); ?></p>
            <div id="hidden" class="columnSection"></div>
        </div>
    </div>
    <button id="tableOptionsResetState">
        <span class="icon-analytics-reset"></span>
        <span><?php p($l->t('Reset table')); ?></span>
    </button>
    <br><br>
    <h3><?php p($l->t('Calculated Columns')); ?> - *beta*</h3>
    <textarea id="tableOptionsCalculatedColumns"></textarea>
</template>