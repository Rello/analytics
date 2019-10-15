<?php
/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */
?>

<div id="app-sidebar" class="details-view scroll-container disappear" data-id="">
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
    <div id="templateTable">
        <div class="table" style="display: table;">
            <div style="display: table-row;">
                <div id="tableKey1" style="display: table-cell; width: 120px;">Name</div>
                <div style="display: table-cell;"><input id="tableName"></div>
            </div>
            <div style="display: table-row;">
                <div id="tableKey2" style="display: table-cell;">Typ</div>
                <div style="display: table-cell;">
                    <select id="tableType">
                        <option value="" selected></option>
                        <option value="1">Local File</option>
                        <option value="2">Database</option>
                        <option value="3">GitHub</option>
                    </select>
                </div>
            </div>
            <div style="display: table-row;">
                <div id="tableKey3" style="display: table-cell;">Datasource</div>
                <div style="display: table-cell;"><input id="tableLink"></div>
            </div>
        </div>
        <br>
        <div>Visualization</div>
        <div id="templateTable" class="table" style="display: table;">
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
        <div>CRON</div>
        <div id="templateTable" class="table" style="display: table;">
            <div style="display: table-row;">
                <div id="tableKey4" style="display: table-cell; width: 120px;">Schedule</div>
                <div style="display: table-cell;">
                    <select id="tableValue6">
                        <option value="ct" selected>inactive</option>
                        <option value="hourly">hourly</option>
                        <option value="daily">daily</option>
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
</div>