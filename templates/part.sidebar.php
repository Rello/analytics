<?php
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */
?>

<div id="app-sidebar" class="details-view scroll-container disappear" data-id="" data-type="">
    <div class="detailFileInfoContainer">
        <div class="mainFileInfoView">
            <div class="thumbnailContainer">
                <a id="sidebarThumbnail" href="#" class="thumbnail">
                    <div class="stretcher"></div>
                </a>
            </div>
            <div class="file-details-container">
                <br>
                <div class="fileName"><h2 id="sidebarTitle"></h2>
                    <br>
                </div>
                <div class="file-details ellipsis">
                    <a class="action action-favorite favorite permanent">
                        <span id="sidebarFavorite" class="icon icon-star" title=""></span>
                    </a>
                    <span id="sidebarMime"></span>
                </div>
            </div>
        </div>
    </div>
    <ul class="tabHeaders">
    </ul>
    <div class="tabsContainer">
    </div>
    <a id="sidebarClose" class="close icon-close" href="#"></a>
</div>

<div hidden>
    <div id="templateDataset">
        <div class="table" style="display: table;">
            <div style="display: table-row;">
                <div id="tableKey1" style="display: table-cell; width: 120px;"><?php p($l->t('Report title')); ?></div>
                <div style="display: table-cell;"><input id="tableName"></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;"><?php p($l->t('Report group')); ?></div>
                <div style="display: table-cell;">
                    <select id="tableParent">
                        <option value="0"></option>
                    </select>
                </div>
            </div>
            <div style="display: table-row;">
                <div id="tableKey2" style="display: table-cell;"><?php p($l->t('Datasource')); ?></div>
                <div style="display: table-cell;">
                    <select id="tableType">
                        <option value="" selected></option>
                        <option value="0"><?php p($l->t('No Data / Group')); ?></option>
                        <option value="1"><?php p($l->t('Local File')); ?></option>
                        <option value="2"><?php p($l->t('Internal Database')); ?></option>
                        <option value="3"><?php p($l->t('GitHub')); ?></option>
                    </select>
                </div>
            </div>
            <div id="datasetLinkRow" style="display: table-row;">
                <div style="display: table-cell;"><?php p($l->t('Options')); ?></div>
                <div style="display: table-cell;"><input id="datasetLink" disabled>
                    <button id="datasetLinkButton" type="button" class="icon-rename">
                        <?php //p($l->t('Edit')); ?>
                    </button>
                </div>
            </div>
        </div>
        <br>
        <div id="datasetDimensionSectionHeader"><h3><?php p($l->t('Column headers')); ?></h3></div>
        <div id="datasetDimensionSection" class="table" style="display: table;">
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;"><?php p($l->t('Column')); ?>&nbsp;1</div>
                <div style="display: table-cell;"><input id="dimension1"></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;"><?php p($l->t('Column')); ?>&nbsp;2</div>
                <div style="display: table-cell;"><input id="dimension2"></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;"><?php p($l->t('Column')); ?>&nbsp;3</div>
                <div style="display: table-cell;"><input id="dimension3"></div>
            </div>
        </div>
        <br>
        <div id="datasetVisualizationSectionHeader"><h3><?php p($l->t('Visualization')); ?></h3></div>
        <div id="datasetVisualizationSection" class="table" style="display: table;">
            <div style="display: table-row;">
                <div id="tableKey4" style="display: table-cell; width: 120px;"><?php p($l->t('Display')); ?></div>
                <div style="display: table-cell;">
                    <select id="tableVisualization">
                        <option value="ct" selected><?php p($l->t('Chart') . ' & ' . $l->t('Table')); ?></option>
                        <option value="table"><?php p($l->t('Table')); ?></option>
                        <option value="chart"><?php p($l->t('Chart')); ?></option>
                    </select>
                </div>
            </div>
            <div style="display: table-row;">
                <div id="tableKey5" style="display: table-cell;"><?php p($l->t('Chart Type')); ?></div>
                <div style="display: table-cell;">
                    <select id="tableChart">
                        <option value="" selected></option>
                        <option value="line"><?php p($l->t('Line')); ?></option>
                        <option value="datetime"><?php p($l->t('Timeline (Date in column 2)')); ?></option>
                        <option value="column"><?php p($l->t('Columns')); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <br>
        <button id="updateDatasetButton" type="button">
            <?php p($l->t('Update Report')); ?>
        </button>
        <button id="deleteDatasetButton" type="button">
            <?php p($l->t('Delete Report')); ?>
        </button>
    </div>
    <div id="templateData">
        <div class="table" style="display: table;">
            <div style="display: table-row;">
                <div id="DataTextDimension1"
                     style="display: table-cell; width: 120px;"><?php p($l->t('Object')); ?></div>
                <div style="display: table-cell;"><input id="DataDimension1"></div>
            </div>
            <div style="display: table-row;">
                <div id="DataTextDimension2" style="display: table-cell; width: 120px;"><?php p($l->t('Date')); ?></div>
                <div style="display: table-cell;"><input id="DataDimension2"></div>
            </div>
            <div style="display: table-row;">
                <div id="DataTextDimension3"
                     style="display: table-cell; width: 120px;"><?php p($l->t('Value')); ?></div>
                <div style="display: table-cell;"><input id="DataDimension3"></div>
            </div>
        </div>
        <br>
        <button id="updateDataButton" type="button">
            <?php p($l->t('Save Data')); ?>
        </button>
        <button id="deleteDataButton" type="button" disabled>
            <?php p($l->t('Delete Data')); ?>
        </button>
        <br>
        <br>
        <br>
        <div><h3><?php p($l->t('Data import')); ?></h3></div>
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
    </div>
</div>