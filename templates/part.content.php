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
<div id="data-content" style="width:100%; padding: 30px">
    <input type="hidden" name="sharingToken" value="<?php p($_['token']); ?>" id="sharingToken">
    <input type="hidden" name="dataset" value="" id="datasetId">
    <div>
        <div id="drilldown" style="display: none">
            Drilldown by:
            <input type="checkbox" id="checkBoxObject" class="checkbox" checked>
            <label for="checkBoxObject">Object</label>
            <input type="hidden" id="checkBoxDate" class="checkbox" checked>
        </div>
        <div id="chartContainer" style="min-width: 310px; height: 50%; margin: 0 auto"></div>
        <br>
        <table id="tableContainer" style="width:100%; height: 50%"></table>
    </div>
</div>