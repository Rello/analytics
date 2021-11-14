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
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Datasource')); ?></div>
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
    <div id="reportDatasourceSectionHeader"><h3><?php p($l->t('Datasource options')); ?></h3></div>
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
                    <option value="datetime"><?php p($l->t('Timeline (Date in column 2)')); ?></option>
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
        <?php p($l->t('Export'));?>
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
            <?php /*p($l->t('Export')); */?>
        </button>
-->    </div>
</template>

<template id="templateData">
    <div><h3><?php p($l->t('Manual entry')); ?></h3></div>
    <div class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div id="DataTextDimension1"
                 style="display: table-cell; width: 100%;"><?php p($l->t('Object')); ?></div>
            <input style="display: table-cell;" class="sidebarInput" id="DataDimension1">
        </div>
        <div style="display: table-row;">
            <div id="DataTextDimension2" style="display: table-cell; width: 120px;"><?php p($l->t('Date')); ?></div>
            <input style="display: table-cell;" class="sidebarInput" id="DataDimension2">
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
    <div><h3><?php p($l->t('Import')); ?></h3></div>
    <button id="importDataFileButton" type="button">
        <?php p($l->t('From file')); ?>
    </button>
    <button id="importDataClipboardButton" type="button">
        <?php p($l->t('From clipboard')); ?>
    </button>
    <br>
    <textarea id="importDataClipboardText" rows="5" cols="50" hidden></textarea>
    <br>
    <button id="importDataClipboardButtonGo" type="button" hidden>
        <?php p($l->t('Import')); ?>
    </button>
    <div><h3><?php p($l->t('REST API')); ?></h3></div>
    <div id="apiLink" class="clipboard-button icon icon-clippy" style="width: 20px;"></div>
    <input type="hidden" id="DataApiDataset">
    <br>
    <div><h3><?php p($l->t('Dataload')); ?></h3></div>
    <button id="advancedButton" type="button">
        <?php p($l->t('Advanced configuration')); ?>
    </button>
</template>

<template id="templateThreshold">
    <h1><?php p($l->t('Thresholds can trigger notifications and color coding in reports')); ?></h1>
    <a href="https://github.com/Rello/analytics/wiki/Thresholds"
       target="_blank"><?php p($l->t('More information…')); ?></a>
    <br>
    <br>
    <div class="table" style="display: table;">
        <div style="display: table-row;">
            <div id="sidebarThresholdTextDimension1"
                 style="display: table-cell; width: 120px;"><?php p($l->t('Object')); ?></div>
            <div style="display: table-cell;"><input id="sidebarThresholdDimension1" class="input150"
                                                     placeholder="<?php p($l->t('single value or *')); ?>"></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;"><?php p($l->t('Operator')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarThresholdOption" class="input150">
                    <option value="=" selected><?php p($l->t('= equal')); ?></option>
                    <option value=">"><?php p($l->t('> greater')); ?></option>
                    <option value="<"><?php p($l->t('< less')); ?></option>
                    <option value="<="><?php p($l->t('<= less equal')); ?></option>
                    <option value=">="><?php p($l->t('>= greater equal')); ?></option>
                    <option value="!="><?php p($l->t('!= not equal')); ?></option>
                </select>
            </div>
        </div>
        <div style="display: table-row;">
            <div id="sidebarThresholdTextValue"
                 style="display: table-cell; width: 120px;"><?php p($l->t('Value')); ?></div>
            <div style="display: table-cell;"><input id="sidebarThresholdValue" class="input150"></div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;"><?php p($l->t('Severity')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarThresholdSeverity" class="input150">
                    <option value="1" selected><?php p($l->t('Notification')); ?></option>
                    <option value="2"><?php p($l->t('red')); ?></option>
                    <option value="3"><?php p($l->t('yellow')); ?></option>
                    <option value="4"><?php p($l->t('green')); ?></option>
                </select>
            </div>
        </div>
    </div>
    <br>
    <button id="createThresholdButton" type="button" class="primary">
        <?php p($l->t('Save threshold')); ?>
    </button>
    <br>
    <br>
    <div id="sidebarThresholdList"></div>
</template>

<template id="templateDataload">
    <div style="display: flex;">
        <div style="width: 25%;">
            <div class="wizardHeader"><?php p($l->t('Selection')); ?></div>
            <div id="dataloadList">No dataload created yet</div>
            <div style="display: table-row;">
                <div style="display: table-cell;">
                    <div id="createDataloadButton" class="icon-add icon" style="padding: 0 0px 0 44px;">
                    </div>
                </div>
                <div style="display: table-cell;">
                    <select id="datasourceSelect" class="input150">
                    </select>
                </div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell;">
                    <div class="icon" style="padding: 0 0px 0 44px;">
                    </div>
                </div>
                <div style="display: table-cell;">
                    <a href="https://github.com/Rello/analytics/wiki/Datasources"
                       target="_blank"><?php p($l->t('More information…')); ?></a>
                </div>
            </div>
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
                    </div>
                </div>
                <br>
                <div id="dataloadDetailItems">
                </div>
                <div id="dataloadDetailDelete" hidden>
                    <div style="display: table-row;">
                        <div style="display: table-cell; width: 100%;"><?php p($l->t('Delete data before load')); ?></div>
                        <select class="sidebarInput" id="delete" style="display: table-cell;">
                            <option value="false"><?php p($l->t('No')); ?></option>
                            <option value="true"><?php p($l->t('Yes')); ?></option>
                        </select></div>
                </div>
                <div id="dataloadDetailButtons" hidden>
                    <button id="dataloadDeleteButton" style="padding: 15px;" class="icon-delete"></button>
                    <button id="dataloadUpdateButton" style="padding: 15px;" class="icon-checkmark"></button>
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
                        for="testrunCheckbox"><?php p($l->t('Testrun')); ?></label>
                <br><br>
                <span><?php p($l->t('Schedule in background')); ?></span>
                <br>
                <select id="dataloadSchedule" class="input150">
                    <option value="" selected><?php p($l->t('not scheduled')); ?></option>
                    <option value="d"><?php p($l->t('daily')); ?></option>
                    <option value="h"><?php p($l->t('hourly')); ?></option>
                </select>
                <br><br>
                <span><?php p($l->t('Load via occ command:')); ?></span>
                <br>
                <span id="dataloadOCC"></span>
                <br>
                <br>
                <a href="https://github.com/Rello/analytics/wiki/Scheduled-dataloads"
                   target="_blank"><?php p($l->t('More information…')); ?></a>
            </div>
        </div>
    </div>

</template>

<template id="templateNavigationMenu">
    <div id="navigationMenu" class="app-navigation-entry-menu">
        <ul>
            <li><a href="#" id="navigationMenuEdit"><span class="icon-rename"></span><span></span></a></li>
<!--            <li><a href="#" id="navigationMenuAdvanced"><span></span><span></span></a></li>-->
            <li>
                <a href="#" id="navigationMenueFavorite">
                    <span class="icon icon-star"></span>
                    <span></span>
                </a>
            </li>
            <li class="action-separator"></li>
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