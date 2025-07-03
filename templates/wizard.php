<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>
<template id="wizardDialog">
    <div class="modal-mask" id="analyticsWizard"
         style="touch-action: pan-y; user-select: none; -webkit-user-drag: none; -webkit-tap-highlight-color: rgba(0, 0, 0, 0);">
        <div class="modal-wrapper modal-wrapper--normal" style="">
            <a class="prev" id="wizardPrevious">
                <svg fill="currentColor" width="40" height="40" viewBox="0 0 24 24" class="material-design-icon__svg">
                    <path d="M15.41,16.58L10.83,12L15.41,7.41L14,6L8,12L14,18L15.41,16.58Z"><!----></path>
                </svg>
            </a>
            <div class="modal-container">
                <div class="modal-header">
                    <div class="firstrunwizard-header">
                        <div class="logo"><p class="hidden-visually">Analytics</p></div>
                        <h2></h2>
                        <p></p>
                    </div>
                </div>
                <div id="pageBody" class="modal-body"></div>
                <div class="modal-footer" id="wizardFooter">
                </div>
                <button id="wizardClose" class="wizardClose">
                    <svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24"
                         class="material-design-icon__svg">
                        <path
                            d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z">
                            <!----></path>
                    </svg>
                </button>
            </div>
            <a class="next" id="wizardNext">
                <svg fill="currentColor" width="40" height="40" viewBox="0 0 24 24" style="">
                    <path d="M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z"><!----></path>
                </svg>
            </a>
        </div>
</template>

<template id="wizard-start">
    <div class="page" style="display: none;">
        <div class="content content-values">
            <h3><?php p($l->t('Nextcloud Analytics makes your data visible and helps you to evaluate them - from financial data to IoT logs.')); ?>
                <br><?php p($l->t('Give your numbers a meaning.')); ?></h3>
            <ul id="wizard-values">
                <li>
                    <span class="icon-timezone"></span>
                    <h3><?php p($l->t('Data is processed inside Nextcloud.')); ?></h3>
                </li>
                <li>
                    <span class="icon-shared"></span>
                    <h3><?php p($l->t('Share your reports and insights')); ?></h3>
                </li>
                <li>
                    <span class="icon-projects"></span>
                    <h3><?php p($l->t('Fully integrated into Nextcloud')); ?></h3>
                </li>
                <li>
                    <span class="icon-user"></span>
                    <h3>100% <?php p($l->t('Open Source')); ?></h3>
                </li>
            </ul>
        </div>
    </div>
</template>
<template id="wizard-charts">
    <div class="page" style="display: none;">
        <div class="image"><img
                src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_charts.png') ?>">
        </div>
        <div class="description">
            <p id="wizardNewHeader1"><?php p($l->t('Visualize any data with ease')); ?></p>
            <ul>
                <li><?php p($l->t('Showcase key insights with Panoramas')); ?></li>
                <li><?php p($l->t('Various chart types, including lines, columns, and doughnuts')); ?></li>
                <li><?php p($l->t('Gain a comprehensive view of your data with Nextcloud Dashboard')); ?></li>
                <li><?php p($l->t('Interactive tables for deeper analysis')); ?></li>
                <li><?php p($l->t('Set thresholds to highlight exceptions and receive Nextcloud notifications')); ?></li>
            </ul>
        </div>
    </div>
</template>
<template id="wizard-filter">
    <div class="page" style="display: none;">
        <div class="image"><img
                src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_filter.gif') ?>">
        </div>
        <div class="description">
            <p id="wizardNewHeader2"><?php p($l->t('Slice and dice your data with precision')); ?></p>
            <ul>
                <li><?php p($l->t('Apply flexible filters, including dynamic date variables')); ?></li>
                <li><?php p($l->t('Top N, sorting and time aggregation')); ?></li>
                <li><?php p($l->t('Customize charts with primary and secondary axes')); ?></li>
                <li><?php p($l->t('Mix and match chart types for each data series')); ?></li>
                <li><?php p($l->t('Save default filters for quick access to your preferred views')); ?></li>
            </ul>
        </div>
    </div>
</template>
<template id="wizard-datasource">
    <div class="page" style="display: none;">
        <div class="image"><img
                src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_datasources.gif') ?>">
        </div>
        <div class="description">
            <p id="wizardNewHeader3"><?php p($l->t('Utilize data from multiple sources with ease')); ?></p>
            <ul>
                <li><?php p($l->t('Data is persisted in the database for maximum flexibility and performance')); ?></li>
                <li><?php p($l->t('Monitor real-time download statistics with GitHub integration')); ?></li>
                <li><?php p($l->t('Leverage the JSON data source to pull data from external services')); ?></li>
                <li><?php p($l->t('Extract data from virtually any website using the HTML-Grabber')); ?></li>
                <li><?php p($l->t('Visualize data from internal Nextcloud applications instantly')); ?></li>
            </ul>
        </div>
    </div>
</template>
<template id="wizard-dataload">
    <div class="page" style="display: none;">
        <div class="image"><img
                src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_dataload.gif') ?>">
        </div>
        <div class="description">
            <p id="wizardNewHeader4"><?php p($l->t('Automate your data workflows seamlessly')); ?></p>
            <ul>
                <li><?php p($l->t('Persist data from any source directly in Nextcloud')); ?></li>
                <li><?php p($l->t('Utilize timestamps to create historical records')); ?></li>
                <li><?php p($l->t('Perform full or incremental delta loads')); ?></li>
                <li><?php p($l->t('Schedule automatic data loads in the background')); ?></li>
                <li><?php p($l->t('Trigger data loads with scripts using the occ command')); ?></li>
                <li><?php p($l->t('Push data into Analytics effortlessly via the REST API')); ?></li>
            </ul>
        </div>
    </div>
</template>
<template id="wizardFinal">
    <div class="page content-final" style="display: none;">
        <div class="description-wide">
            <div class="description-block">
                <h3 class="icon-info"><?php p($l->t('Get more information')); ?></h3>
                <ul>
                    <li><a href="https://github.com/Rello/analytics/wiki" target="_blank"
                           rel="noreferrer noopener"><?php p($l->t('Wiki')); ?></a>
                    </li>
                    <li><a href="https://help.nextcloud.com/c/apps/analytics/159" target="_blank"
                           rel="noreferrer noopener"><?php p($l->t('Nextcloud forum')); ?></a></li>
                </ul>
            </div>
            <br>
            <div class="description-block">
                <p><?php p($l->t('You can open this introduction again by selecting "Introduction" in the Analytics settings section.')); ?></p>
            </div>
        </div>
        <div class="description-wide">
            <div class="description-block">
                <h3 class="icon-link"><?php p($l->t('Quickstart')); ?></h3>
                <p><?php p($l->t('Activate a set of demo report to show how Analytics works.')); ?></p>
                <button id="wizardDemo">
                    <?php p($l->t('Create Demo')); ?>
                </button>
            </div>
            <br><br>
            <div class="description-block">
                <button id="wizardEnd" class="analyticsPrimary modal-default-button">
                    <?php p($l->t('Start using Analytics')); ?>
                </button>
            </div>
        </div>
    </div>
</template>

<template id="wizardNewGeneral">
    <div class="page" style="display: none;">
        <div class="content content-values">
            <h2><?php p($l->t('General information')); ?></h2><br>
            <div class="table" style="display: table; width: 100%;">
                <div style="display: table-row;">
                    <div style="display: table-cell; width: 50%;">
                        <?php p($l->t('Report title')); ?>
                        <br>
                        <input style="display: table-cell; width: 400px;" id="wizardNewName"
                               value="<?php p($l->t('New report')); ?>">
                        <br><br>
                        <?php p($l->t('Subheader')); ?>
                        <br>
                        <input style="display: table-cell; width: 400px;" id="wizardNewSubheader">
                        <br><br>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('Text variables can be used in the name or subheader. They are replaced when the report is executed.')); ?>
                        <br><br>
                        <?php p($l->t('The following variables are available:')); ?><br>
                        %lastUpdateDate%, %lastUpdateTime%, %currentDate%, %currentTime%, %owner%
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; width: 50%;">
                        <?php p($l->t('Report group')); ?>
                        <br>
                        <select style="display: table-cell;" id="wizardNewGrouping" class="sidebarInput">
                            <option value="0"></option>
                        </select>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('Reports can be grouped into a folder structure.')); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<template id="wizardNewType">
    <div class="page" style="display: none;">
        <div class="content content-values">
            <h2><?php p($l->t('Type of report')); ?></h2><br>
            <div class="table" style="display: table; width: 100%;">
                <div style="display: table-row;">
                    <div style="display: table-cell; width: 50%;">
                        <button id="wizardNewTypeRealtime"><?php p($l->t('Real-time Data')); ?></button>
                        <button id="wizardNewTypeStored"><?php p($l->t('Stored Data')); ?></button>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <?php p($l->t('Reports can read their data either from real-time data sources or from saved datasets within the Nextcloud database.')); ?>
                        <br>
                    </div>
                </div>
                <div id="wizardNewTypeDatasourceRow" style="display: none;">
                    <div style="display: table-cell; width: 50%;">
                        <?php p($l->t('Data source')); ?>
                        <br>
                        <select style="display: table-cell;" id="wizardNewDatasource" class="sidebarInput">
                        </select>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('Different data sources are available. The data is read in real-time when the report is being executed.')); ?>
                        <br>
                    </div>
                </div>
                <div id="wizardNewTypeOptionsRow" style="display: none;">
                    <div style="display: table-cell; width: 50%;">
                        <div id="wizardNewDatasourceSection" style="margin-right: 20px;"></div>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <?php p($l->t('Every data source requires specific parameter. Please enter the information.')); ?>
                        <br>
                    </div>
                </div>
                <div id="wizardNewTypeStoredRow" style="display: none;">
                    <div style="display: table-cell; width: 50%;">
                        <br>
                        <button id="wizardNewTypeStoredNew"><?php p($l->t('New dataset')); ?></button>
                        <button id="wizardNewTypeStoredOld"><?php p($l->t('Existing dataset')); ?></button>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('The report can be based on a new dataset or read existing data that is available already.')); ?>
                        <br>
                    </div>
                </div>
                <div id="wizardNewTypeDatasetRow" style="display: none;">
                    <div style="display: table-cell; width: 50%;">
                        <br>
                        <select id="wizardNewDataset">
                        </select>
                        <br>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('Many reports can be based on the same data, but every report has its own visualization, threshold and sharing settings.')); ?>
                        <br>
                        <br>
                    </div>
                </div>
                <div id="wizardNewTypeDimensionRow" style="display: none;">
                    <div style="display: table-cell; width: 50%;">
                        <?php p($l->t('Column')); ?>&nbsp;1
                        <br>
                        <input id="wizardNewDimension1" class="sidebarInput" value="<?php p($l->t('Object')); ?>">
                        <br>
                        <?php p($l->t('Column')); ?>&nbsp;2
                        <br>
                        <input id="wizardNewDimension2" class="sidebarInput" value="<?php p($l->t('Date')); ?>">
                        <br>
                        <?php p($l->t('Value')); ?>
                        <br>
                        <input id="wizardNewValue" class="sidebarInput" value="<?php p($l->t('Value')); ?>">
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('Datasets can store data from scheduled data loads, the REST API or manual data entry. This can be configured via the Dataset maintenance')); ?>
                        <br><br>
                        <?php p($l->t('Choose the names of the columns for the new dataset')); ?>
                        <br>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
<template id="wizardNewVisual">
    <div class="page" style="display: none;">
        <div class="content content-values">
            <h2><?php p($l->t('Visualization of the report')); ?></h2>
            <h3><?php p($l->t('All settings can be changed afterwards')); ?></h3>
            <div class="table" style="display: table; width: 100%;">
                <div style="display: table-row;">
                    <div style="display: table-cell; width: 50%;">
                        <div class="icon-analytics-chartNone icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartNone" class="radio" checked="checked" name="chart">
                            <label for="chartNone"><?php p($l->t('No Chart')); ?></label>
                        </div>
                    </div>
                    <div style="display: table-cell;">
                        <div class="icon-analytics-chartNone icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartTableNone" class="radio" checked="checked" name="table">
                            <label for="chartTableNone"><?php p($l->t('No Table')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; ">
                        <div class="icon-analytics-chartTime icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartTime" class="radio" name="chart" value="datetime">
                            <label for="chartTime"><?php p($l->t('Timeline (date in column 2)')); ?></label>
                        </div>
                    </div>
                    <div style="display: table-cell;">
                        <div class="icon-analytics-chartTable icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartTable" class="radio" name="table">
                            <label for="chartTable"><?php p($l->t('Table')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; ">
                        <div class="icon-analytics-chartLine icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartLine" class="radio" name="chart" value="line">
                            <label for="chartLine"><?php p($l->t('Line')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; ">
                        <div class="icon-analytics-chartArea icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartArea" class="radio" name="chart" value="area">
                            <label for="chartArea"><?php p($l->t('Area')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; ">
                        <div class="icon-analytics-chartFunnel icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartFunnel" class="radio" name="chart" value="funnel">
                            <label for="chartFunnel"><?php p($l->t('Funnel')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell;">
                        <div class="icon-analytics-chartBar icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartBar" class="radio" name="chart" value="column">
                            <label for="chartBar"><?php p($l->t('Bar')); ?></label>
                        </div>
                    </div>
                    <div style="display: table-cell;">
                        <?php p($l->t('The report can now be created')); ?>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell;">
                        <div class="icon-analytics-chartDonut icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 10px;">
                            <input type="radio" id="chartDonut" class="radio" name="chart" value="doughnut">
                            <label for="chartDonut"><?php p($l->t('Doughnut')); ?></label>
                        </div>
                    </div>
                    <div style="display: table-cell;">
                        <button id="wizardNewCreate" type="button" class="analyticsPrimary">
                            <?php p($l->t('Create')); ?>
                        </button>
                    </div>
                    <div style="display: table-cell;">
                        <button id="wizardNewCancel" type="button">
                            <?php p($l->t('Cancel')); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="wizardDatasetGeneral">
    <div class="page" style="display: none;">
        <div class="content content-values">
            <h2><?php p($l->t('General information')); ?></h2><br>
            <div class="table" style="display: table; width: 100%;">
                <div style="display: table-row;">
                    <div style="display: table-cell; width: 50%;">
                        <?php p($l->t('Dataset')); ?> <?php p($l->t('Title')); ?>
                        <br>
                        <input style="display: table-cell; width: 400px;" id="wizardDatasetName">
                        <br><br>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <button id="wizardNewCreate" type="button" class="analyticsPrimary">
                            <?php p($l->t('Create')); ?>
                        </button>
                        <button id="wizardNewCancel" type="button">
                            <?php p($l->t('Cancel')); ?>
                        </button>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; width: 50%;">
                        <?php p($l->t('Column')); ?>&nbsp;1
                        <br>
                        <input id="wizardDatasetDimension1" class="sidebarInput" value="<?php p($l->t('Object')); ?>">
                        <br>
                        <?php p($l->t('Column')); ?>&nbsp;2
                        <br>
                        <input id="wizardDatasetDimension2" class="sidebarInput" value="<?php p($l->t('Date')); ?>">
                        <br>
                        <?php p($l->t('Value')); ?>
                        <br>
                        <input id="wizardDatasetValue" class="sidebarInput" value="<?php p($l->t('Value')); ?>">
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('Datasets can store data from scheduled data loads, the REST API or manual data entry. This can be configured via the Dataset maintenance')); ?>
                        <br><br>
                        <?php p($l->t('Choose the names of the columns for the new dataset')); ?>
                        <br>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
