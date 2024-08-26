<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>
<h3 style="text-align: center; background-color: var(--color-primary-light);"><?php p($l->t('Dataset maintenance')); ?></h3>
<div id="analytics-content" hidden>
    <input type="hidden" name="sharingToken" value="" id="sharingToken">
    <input type="hidden" name="dataset" value="" id="datasetId">
    <input type="hidden" name="advanced" value="true" id="advanced">
    <h3 id="sidebarTitle" style="text-align: center;"></h3>
    <div id="app-sidebar" class="details-view scroll-container disappear" data-id="" data-type="">
        <ul class="tabHeaders">
        </ul>
        <div class="tabsContainer">
        </div>
    </div>
</div>
<div id="analytics-intro" style="padding: 50px" hidden>
    <span><?php p($l->t('This section is used for dataset maintenance and data load configurations.')); ?></span>
    <br>
    <br>
    <span><?php p($l->t('Please select a dataset')); ?></span>
</div>