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
<template id="wizardDialog">
    <div class="modal-mask" id="analyticsWizard"
         style="touch-action: pan-y; user-select: none; -webkit-user-drag: none; -webkit-tap-highlight-color: rgba(0, 0, 0, 0);"
         hidden>

        <div class="modal-header-top">
            <div class="icons-menu">
                <button id="wizardClose" class="action-item action-item--single icon-close">
                </button>
            </div>
        </div>
        <div class="modal-wrapper modal-wrapper--normal" style="">
            <a class="prev" style="">
                <div id="wizardPrevious" class="icon icon-view-previous icon-white">
                    <span class="hidden-visually">Previous</span>
                </div>
            </a>
            <div class="modal-container">
                <div class="modal-header">
                    <div class="firstrunwizard-header">
                        <div class="logo"><p class="hidden-visually">NC20</p></div>
                        <h2></h2>
                        <p></p>
                    </div>
                </div>
                <div id="pageBody" class="modal-body"></div>
                <div class="modal-footer" id="wizardFooter">
                </div>
            </div>
            <a class="next" style="">
                <div id="wizardNext" class="icon icon-view-next icon-white">
                    <span class="hidden-visually">Next</span>
                </div>
            </a>
        </div>
</template>

<template id="wizard-start">
    <div class="page" style="display: none;">
        <div class="content content-values">
            <h3>Nextcloud Analytics makes your data visible and helps you to evaluate them - from financial data to IoT
                logs<br>Give your numbers a meaning</h3>
            <ul id="wizard-values">
                <li>
                    <span class="icon-timezone"></span>
                    <h3>Data is processed inside Nextcloud</h3>
                </li>
                <li>
                    <span class="icon-shared"></span>
                    <h3>Share your reports and insights</h3>
                </li>
                <li>
                    <span class="icon-projects"></span>
                    <h3>Fully integrated into Nextcloud</h3>
                </li>
                <li>
                    <span class="icon-user"></span>
                    <h3>100% Open Source</h3>
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
                <h3 id="wizardNewHeader1">Visualize any data with easy to use reports</h3>
                <ul>
                    <li>Different chart types like lines, columns or doughnuts</li>
                    <li>Customize further chart options by using advanced scripting</li>
                    <li>Show your most important insights in the Nextcloud Dashboard</li>
                    <li>Interactive tables</li>
                    <li>Use thresholds to mark exceptions or receive Nextcloud notifications</li>
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
                <h3 id="wizardNewHeader2">Slice and dice your data</h3>
                <ul>
                    <li>Different filter types like "equal", "list of values" or "contains"</li>
                    <li>Change drilldowns by removing columns</li>
                    <li>Customize chats by assigning primary or secondary axis</li>
                    <li>Use different chart types per data series</li>
                    <li>Save filters as default</li>
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
                <h3 id="wizardNewHeader3">Use data from various datasources</h3>
                <ul>
                    <li>Internal data is persisted in the database and delivers the most flexibility and performance
                    </li>
                    <li>Use data from Github to monitor download statistics in realtime</li>
                    <li>The JSON datasoruce can read data from external services</li>
                    <li>With the HTML-Grabber, almost any website data can be extracted</li>
                    <li>Read data from internal Nextcloud files to visualize them in realtime</li>
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
                <h3 id="wizardNewHeader4">Advanced configuration: Dataloads</h3>
                <ul>
                    <li>Any datasource can be persisted in Nextcloud</li>
                    <li>Use timestamps to historize data</li>
                    <li>Full (deletion of old data) or delta loads</li>
                    <li>Schedule dataloads in the background daily or hourly</li>
                    <li>Trigger dataloads via scripts using the occ command</li>
                    <li>Use the REST API to push data into Analytics</li>
                </ul>
            </div>
        </div>
</template>
<template id="wizardFinal">
        <div class="page content-final" style="display: none;">
            <div class="description-wide">
                <div class="description-block">
                    <h3 class="icon-info">Get more information</h3>
                    <ul>
                        <li><a href="https://github.com/Rello/analytics/wiki" target="_blank" rel="noreferrer noopener">Wiki</a>
                        </li>
                        <li><a href="https://help.nextcloud.com/c/apps/analytics/159" target="_blank"
                               rel="noreferrer noopener">Nextcloud forum</a></li>
                    </ul>
                </div>
                <br>
                <div class="description-block">
                    <p>You can open this introduction again by selecting "About" in the Analytics settings section</p>
                </div>
            </div>
            <div class="description-wide">
                <div class="description-block">
                    <h3 class="icon-link">Quickstart</h3>
                    <p>Activate a set of demo report to show how Analytics works</p>
                    <button id="wizardDemo">
                        Create Demo
                    </button>
                </div>
                <br><br>
                <div class="description-block">
                    <button id="wizardEnd" class="primary modal-default-button">
                        Start using Analytics
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
                        <input style="display: table-cell; width: 400px;" id="wizardNewName">
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
                        <?php p($l->t('Reports can be grouped into a folder structure')); ?>
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
                        <button id="wizardNewTypeRealtime"><?php p($l->t('Realtime Data')); ?></button>
                        <button id="wizardNewTypeStored"><?php p($l->t('Stored Data')); ?></button>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <?php p($l->t('Reports can read their data either from realtime data sources or from saved datasets within the Nextcloud database.')); ?>
                        <br>
                    </div>
                </div>
                <div id="wizardNewTypeDatasourceRow" style="display: none;">
                    <div style="display: table-cell; width: 50%;">
                        <?php p($l->t('Datasource')); ?>
                        <br>
                        <select style="display: table-cell;" id="wizardNewDatasource" class="sidebarInput">
                        </select>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <?php p($l->t('Different data sources are available. The data is read in realtime when the report is being executed.')); ?>
                        <br>
                    </div>
                </div>
                <div id="wizardNewTypeOptionsRow" style="display: none;">
                    <div style="display: table-cell; width: 50%;">
                        <div id="wizardNewDatasourceSection" style="margin-right: 20px;"></div>
                        <br>
                    </div>
                    <div style="display: table-cell;">
                        <?php p($l->t('Every datasource requires specific parameter. Please enter the information.')); ?>
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
                        <div style="padding-top: 17px;">
                            <input type="radio" id="chartNone" class="radio" checked="checked" name="chart">
                            <label for="chartNone"><?php p($l->t('No Chart')); ?></label>
                        </div>
                    </div>
                    <div style="display: table-cell;">
                        <div class="icon-analytics-chartNone icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 17px;">
                            <input type="radio" id="chartTableNone" class="radio" checked="checked" name="table">
                            <label for="chartTableNone"><?php p($l->t('No Table')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; ">
                        <div class="icon-analytics-chartLine icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 17px;">
                        <input type="radio" id="chartTime" class="radio" name="chart" value="datetime">
                        <label for="chartTime"><?php p($l->t('Timeline (date in column 2)')); ?></label>
                        </div>
                    </div>
                    <div style="display: table-cell;">
                        <div class="icon-analytics-chartTable icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 17px;">
                            <input type="radio" id="chartTable" class="radio" name="table">
                            <label for="chartTable"><?php p($l->t('Table')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; ">
                        <div class="icon-analytics-chartTime icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 17px;">
                        <input type="radio" id="chartLine" class="radio" name="chart" value="line">
                        <label for="chartLine"><?php p($l->t('Line')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell; ">
                        <div class="icon-analytics-chartArea icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 17px;">
                            <input type="radio" id="chartArea" class="radio" name="chart" value="area">
                            <label for="chartArea"><?php p($l->t('Area')); ?></label>
                        </div>
                    </div>
                </div>
                <div style="display: table-row;">
                    <div style="display: table-cell;">
                        <div class="icon-analytics-chartBar icon-analytics-charts-wizard"></div>
                        <div style="padding-top: 17px;">
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
                        <div style="padding-top: 17px;">
                        <input type="radio" id="chartDonut" class="radio" name="chart" value="doughnut">
                        <label for="chartBar"><?php p($l->t('Doughnut')); ?></label>
                        </div>
                    </div>
                    <div style="display: table-cell;">
                        <button id="wizardNewCreate" type="button" class="primary">
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
                        <?php p($l->t('Dataset'));?> <?php p($l->t('Title'));?>
                        <br>
                        <input style="display: table-cell; width: 400px;" id="wizardDatasetName">
                        <br><br>
                    </div>
                    <div style="display: table-cell;">
                        <br>
                        <button id="wizardNewCreate" type="button" class="primary">
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
