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
                <div id="tableKey1" style="display: table-cell; width: 120px;">Name</div>
                <div style="display: table-cell;"><input id="tableName"></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;">Parent Dataset</div>
                <div style="display: table-cell;">
                    <select id="tableParent">
                        <option value="0"></option>
                    </select>
                </div>
            </div>
            <div style="display: table-row;">
                <div id="tableKey2" style="display: table-cell;">Datasource</div>
                <div style="display: table-cell;">
                    <select id="tableType">
                        <option value="" selected></option>
                        <option value="0">No Data / Group</option>
                        <option value="1">Local File</option>
                        <option value="2">Database</option>
                        <option value="3">GitHub</option>
                    </select>
                </div>
            </div>
            <div id="datasetLinkRow" style="display: table-row;">
                <div style="display: table-cell;">Parameter</div>
                <div style="display: table-cell;"><input id="datasetLink" disabled>
                    <button id="datasetLinkButton" type="button">
                        Edit
                    </button>
                </div>
            </div>
        </div>
        <br>
        <div id="datasetDimensionSectionHeader"><h3>Column headers</h3></div>
        <div id="datasetDimensionSection" class="table" style="display: table;">
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;">Column 1</div>
                <div style="display: table-cell;"><input id="dimension1"></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;">Column 2</div>
                <div style="display: table-cell;"><input id="dimension2"></div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; width: 120px;">Column 3</div>
                <div style="display: table-cell;"><input id="dimension3"></div>
            </div>
        </div>
        <br>
        <div id="datasetVisualizationSectionHeader"><h3>Visualization</h3></div>
        <div id="datasetVisualizationSection" class="table" style="display: table;">
            <div style="display: table-row;">
                <div id="tableKey4" style="display: table-cell; width: 120px;">Display</div>
                <div style="display: table-cell;">
                    <select id="tableVisualization">
                        <option value="ct" selected>Chart & Table</option>
                        <option value="table">Table</option>
                        <option value="chart">Chart</option>
                    </select>
                </div>
            </div>
            <div style="display: table-row;">
                <div id="tableKey5" style="display: table-cell;">Chart Typ</div>
                <div style="display: table-cell;">
                    <select id="tableChart">
                        <option value="" selected></option>
                        <option value="line">(Time)Line</option>
                        <option value="column">Columns</option>
                    </select>
                </div>
            </div>
        </div>
        <br>
        <button id="updateDatasetButton" type="button">
            Update Dataset
        </button>
        <button id="deleteDatasetButton" type="button">
            Delete Dataset
        </button>
    </div>
    <div id="templateData">
        <div class="table" style="display: table;">
            <div style="display: table-row;">
                <div id="DataTextDimension1" style="display: table-cell; width: 120px;">Object</div>
                <div style="display: table-cell;"><input id="DataDimension1"></div>
            </div>
            <div style="display: table-row;">
                <div id="DataTextDimension2" style="display: table-cell; width: 120px;">Value</div>
                <div style="display: table-cell;"><input id="DataDimension2"></div>
            </div>
            <div style="display: table-row;">
                <div id="DataTextDimension3" style="display: table-cell; width: 120px;">Date</div>
                <div style="display: table-cell;"><input id="DataDimension3"></div>
            </div>
        </div>
        <br>
        <button id="updateDataButton" type="button">
            Save Record
        </button>
        <button id="deleteDataButton" type="button" disabled>
            Delete Record
        </button>
        <br>
        <br>
        <br>
        <div>Import Data</div>
        <button id="importDataFileButton" type="button">
            From File
        </button>
        <button id="importDataClipboardButton" type="button">
            From Clipboard
        </button>
        <br>
        <textarea id="importDataClipboardText" rows="5" cols="50" hidden></textarea>
        <br>
        <button id="importDataClipboardButtonGo" type="button" hidden>
            Import
        </button>
    </div>
</div>