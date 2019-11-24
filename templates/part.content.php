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
<div id="analytics-content" style="width:100%; padding: 10px" hidden>
    <input type="hidden" name="sharingToken" value="<?php p($_['token']); ?>" id="sharingToken">
    <input type="hidden" name="dataset" value="" id="datasetId">
    <h2 id="dataHeader" style="text-align: center;"></h2>
    <h3 id="dataSubHeader" style="text-align: center;"></h3>
    <div style="width:100%; padding: 20px">
        <div id="drilldown" style="display: none" hidden>
            <?php p($l->t('Drilldown')); ?>
            <input type="checkbox" id="checkBoxObject" class="checkbox" checked>
            <label for="checkBoxObject"><?php p($l->t('Object')); ?></label>
            <input type="hidden" id="checkBoxDate" class="checkbox" checked>
        </div>
        <div id="chartContainer" style="min-width: 310px; height: 50%; margin: 0 auto"></div>
        <br>
        <table id="tableContainer" style="width:100%; height: 50%"></table>
    </div>
</div>
<div id="analytics-intro" style="width:50%; padding: 50px">
    <h2><?php p($l->t('Data Analytics')); ?></h2>
    <br>
    <h3><?php p($l->t('Quickstart')); ?></h3>
    <span>-&nbsp;<?php p($l->t('Template placeholder')); ?></span>
    <br>
    <h3><?php p($l->t('Recent')); ?></h3>
    <span>-&nbsp;<?php p($l->t('Recent placeholder')); ?></span>
    <br>
    <h3><?php p($l->t('Updates')); ?></h3>
    <span>-&nbsp;<?php p($l->t('Updates placeholder')); ?></span>
</div>
