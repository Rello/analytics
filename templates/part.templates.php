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

<template id="templateDataset">
    <div class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Report title')); ?></div>
            <input style="display: table-cell;" id="sidebarDatasetName" class="sidebarInput">
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Subheader')); ?></div>
            <input style="display: table-cell;" id="sidebarDatasetSubheader" class="sidebarInput">
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Report group')); ?></div>
            <select style="display: table-cell;" id="sidebarDatasetParent" class="sidebarInput">
                <option value="0"></option>
            </select>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Datasource')); ?></div>
            <div style="display: table-cell;">
                <select style="display: table-cell;" id="sidebarDatasetDatasource" class="sidebarInput">
                    <option value="" selected></option>
                    <option value="0"><?php p($l->t('No Data / Group')); ?></option>
                    <option value="2"><?php p($l->t('Internal Database')); ?></option>
                </select>
            </div>
        </div>
    </div>
    <br>
    <div id="datasetDatasourceSectionHeader"><h3><?php p($l->t('Datasource options')); ?></h3></div>
    <div id="datasetDatasourceSection" class="table" style="display: table; width: 100%; max-width: 500px;"></div>
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
    <div id="datasetVisualizationSectionHeader"><h3><?php p($l->t('Visualization')); ?></h3></div>
    <div id="datasetVisualizationSection" class="table" style="display: table; width: 100%; max-width: 500px;">
        <div style="display: table-row;">
            <div style="display: table-cell;  width: 100%;"><?php p($l->t('Display')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarDatasetVisualization" class="sidebarInput">
                    <option value="ct" selected><?php p($l->t('Chart') . ' & ' . $l->t('Table')); ?></option>
                    <option value="table"><?php p($l->t('Table')); ?></option>
                    <option value="chart"><?php p($l->t('Chart')); ?></option>
                </select>
            </div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell; width: 100%;"><?php p($l->t('Chart type')); ?></div>
            <div style="display: table-cell;">
                <select id="sidebarDatasetChart" class="sidebarInput">
                    <option value="" selected></option>
                    <option value="line"><?php p($l->t('Line')); ?></option>
                    <option value="datetime"><?php p($l->t('Timeline (Date in column 2)')); ?></option>
                    <option value="column"><?php p($l->t('Column')); ?></option>
                    <option value="area"><?php p($l->t('Area')); ?></option>
                    <option value="doughnut"><?php p($l->t('Doughnut')); ?></option>
                </select>
            </div>
        </div>
        <div style="display: table-row;">
            <div style="display: table-cell;  width: 100%;"><?php p($l->t('Chart options')); ?></div>
            <div style="display: table-cell;"><input id="sidebarDatasetChartOptions"
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
            <div style="display: table-cell;"><input id="sidebarDatasetDataOptions"
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
    <button id="sidebarDatasetUpdateButton" type="button">
        <?php p($l->t('Update Report')); ?>
    </button>
    <button id="sidebarDatasetDeleteButton" type="button">
        <?php p($l->t('Delete Report')); ?>
    </button>
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
    <button id="updateDataButton" type="button">
        <?php p($l->t('Save Data')); ?>
    </button>
    <button id="deleteDataButton" type="button">
        <?php p($l->t('Delete Data')); ?>
    </button>
    <br>
    <br>
    <div><h3><?php p($l->t('Import')); ?></h3></div>
    <button id="importDataFileButton" type="button">
        <?php p($l->t('From File')); ?>
    </button>
    <button id="importDataClipboardButton" type="button">
        <?php p($l->t('From Clipboard')); ?>
    </button>
    <br>
    <textarea id="importDataClipboardText" rows="5" cols="50" hidden></textarea>
    <br>
    <button id="importDataClipboardButtonGo" type="button" hidden>
        <?php p($l->t('Import')); ?>
    </button>
    <div><h3><?php p($l->t('REST API')); ?></h3></div>
    <div id="apiLink" class="clipboard-button icon icon-clippy" style="width: 20px;"></div>
    <br>
    <div><h3><?php p($l->t('Dataload')); ?></h3></div>
    <button id="advancedButton" type="button">
        <?php p($l->t('Advanced Configuration')); ?>
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
    <button id="createThresholdButton" type="button">
        <?php p($l->t('Save Threshold')); ?>
    </button>
    <br>
    <br>
    <div id="sidebarThresholdList"></div>
</template>

<template id="templateDataload">
    <div style="display: flex;">
        <div style="width: 25%;">
            <div class="wizzardHeader"><?php p($l->t('Selection')); ?></div>
            <div id="dataloadList"></div>
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
            <div class="wizzardHeader icon-view-next"></div>
        </div>
        <div style="width: 40%;">
            <div class="wizzardHeader"><?php p($l->t('Setting')); ?></div>
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
                <div id="dataloadDetailDelete" style="display: table-row;">
                    <div style="display: table-cell; width: 100%;"><?php p($l->t('Delete data before load')); ?></div>
                    <select class="sidebarInput" id="delete" style="display: table-cell;">
                        <option value="false"><?php p($l->t('No')); ?></option>
                        <option value="true"><?php p($l->t('Yes')); ?></option>
                    </select></div>

                <div id="dataloadDetailButtons" hidden>
                    <button id="dataloadDeleteButton" style="padding: 15px;" class="icon-delete"></button>
                    <button id="dataloadUpdateButton" style="padding: 15px;" class="icon-checkmark"></button>
                </div>
            </div>
        </div>
        <div style="width: 5%;">
            <div class="wizzardHeader icon-view-next"></div>
        </div>
        <div style="width: 24%;">
            <div class="wizzardHeader"><?php p($l->t('Execution')); ?></div>
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
            <li><a href="#" id="navigationMenuAdvanced"><span></span><span></span></a></li>
            <li>
                <a href="#" id="navigationMenueFavorite">
                    <span class="icon icon-star"></span>
                    <span></span>
                </a>
            </li>
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
                                <input disabled type="checkbox" name="shareEditing" id="shareEditing"
                                       class="checkbox showPasswordCheckbox">
                                <label for="shareEditing"><?php p($l->t('Can change filters')); ?></label>
                            </span>
                        </li>
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