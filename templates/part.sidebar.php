<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
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
                <div class="fileName"><h3 id="sidebarTitle"></h3>
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
<template id="templateSidebarShare">
    <input type="text" id="shareInput" placeholder="<?php p($l->t('Name')); ?>"
           style="width: 100%; margin-bottom: 0px;" ;>
    <ul id="shareSearchResult" class="shareWithList" style="display: none;">
    </ul>
    <div class="linkShareView subView">
        <div id="linkShareList" class="shareWithList">
        </div>
    </div>
    <div class="shareeListView subView">
        <div id="shareeList" class="shareWithList"></div>
    </div>
</template>

<template id="templateSidebarShareShareeRow">
    <li id="row">
        <div id="avatar" class="avatar imageplaceholderseed"
             style="width: 32px; height: 32px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 32px; font-size: 17.6px;"></div>
        <span id="username" class="username" data-share-type="" data-user=""></span>
        <span class="sharingOptionsGroup">
        <span id="icon" class="" style="opacity: 0.5;"></span>
            <div id="shareMenu" class="share-menu">
                <a id="icon-more" class="icon icon-more"></a>
                <div class="popovermenu menu" style="display: none;">
                    <ul>
                        <li>
                            <span class="menuitem">
                                <input disabled type="checkbox" name="shareEditing" id="shareEditing"
                                       class="checkbox showPasswordCheckbox">
                                <label for="shareEditing"><?php p($l->t('Can change filters')); ?></label>
                            </span>
                        </li>
                        <li>
                            <a href="#" class="unshare" id="deleteShare">
                                <span class="icon icon-close" id="deleteShareIcon"></span>
                                <span><?php p($l->t('Unshare')); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </span>
    </li>

</template>