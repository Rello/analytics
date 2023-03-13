<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */
?>
<h3 style="text-align: center; background-color: var(--color-primary-light);"><?php p($l->t('Dataset maintenance')); ?></h3>
<div id="analytics-content" style="width:100%; padding: 10px" hidden>
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
<div id="analytics-warning" style="width:50%; padding: 50px">
    <h2><?php p($l->t('Analytics')); ?></h2>
    <br>
    <h3><?php p($l->t('Javascript issue')); ?></h3>
    <span><?php p($l->t('If you see this message, please disable AdBlock/uBlock for this domain (only).')); ?></span>
    <br>
    <span><?php p($l->t('The EasyPrivacy list is blocking some scripts because of a wildcard filter for *analytics*.')); ?></span>
    <br>
    <br>
    <a href="https://github.com/Rello/analytics/wiki/EasyPrivacy-Blocklist"
       target="_blank"><?php p($l->t('More Information …')); ?></a>
</div>