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
<div id="storyHeader" style="text-align: left; background-color: var(--color-primary-light);padding: 12px;"><?php p($l->t('Success Story - march 2023 14')); ?></div>
<style>
    .container {
        width: 100%;
        height: 100%;
        overflow: hidden;
        margin-top: -48px;
        padding-top: 44px;
    }
    .pages {
        display: flex;
        width: 200%;
        height: 100%;
        transition: margin-left 0.5s ease;
        margin-left: 0;
    }
    .flex-container {
        width: 100%;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .flex-row {
        flex: 1;
        display: flex;
        height: 50%;
    }
    .flex-item {
        flex: 1;
        padding: 30px;
    }
    .flex-item:empty {
        background-color: azure;
        border: 1px solid lightgrey;
    }
    .bg-azure {
        background-color: azure;
    }
    .navigation {
        z-index: 9999;
        position: fixed;
        bottom: 20px;
        right: 20px;
        font-size: 24px;
    }
    .nav-item {
        margin: 0 10px;
        opacity: 0.3;
        cursor: pointer;
        transition: opacity 0.3s ease;
    }
    .nav-item:hover {
        opacity: 1;
    }
    .nav-item.disabled {
        opacity: 0.1;
        cursor: not-allowed;
    }
    .edit {
        z-index: 9999;
        position: fixed;
        top: 60px;
        right: 20px;
        font-size: 24px;
    }
    .addPage {
        z-index: 9999;
        position: fixed;
        bottom: 40px;
        right: 20px;
        font-size: 24px;
    }

</style>
<div id="analytics-content" class="container" style="width:100%;">
    <div class="edit">
        <span class="nav-item" id="editBtn">...</span>
    </div>
    <div class="navigation">
        <span class="nav-item" id="prevBtn"><</span>
        <span class="nav-item" id="nextBtn">></span>
    </div>
    <div class="addPage">
        <span class="nav-item" id="plusBtn">+</span>
    </div>

    <div class="pages" id="storyPages">
    </div>
</div>
<div id="analytics-intro" style="padding: 0px" hidden>
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