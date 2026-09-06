<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>

<div id="analytics-content-panorama" hidden>

    <div id="panoramaHeaderRow" class="panoramaHeaderRow">
        <div id="panoramaHeader" class="reportHeader editable"></div>
        <div class="panoramaFilterActions">
            <button type="button" id="panoramaConfigureFilters" hidden><?php p($l->t('Configure filters')); ?></button>
        </div>
    </div>

    <div id="editMenuContainer" class="editMenuContainer" style="display:none;">
        <div class="editMenu" id="editMenu">
            <div class="menu-item" data-modal="modalReport">Report</div>
            <div class="menu-item" data-modal="modalText">Text</div>
            <!--<div class="menu-item" data-modal="modal3">Empty</div>-->
            <div class="menu-item" data-modal="modalPicture">Picture</div>
            <div class="menu-item close-menu-item" data-modal="close">X</div>
        </div>
    </div>

    <!-- Modals for the edit menu -->
    <div>
        <div id="modalText" class="modal">
            <div class="modal-content" style="width: 700px; height: 500px; top: 30%; left: 40%;">
                <span class="close">&times;</span>
                <h2>Enter a free text</h2><br>
                <textarea id="textInputContent" hidden></textarea>
                <div id="textInput" style="width: 649px;height: 330px;overflow: hidden;"></div>
                <br>
                <button type="button" id="textInputButton">save</button>
            </div>
        </div>
        <div id="modal3" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Platzhalter für Später</h2>
            </div>
        </div>
        <div id="modalPicture" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Choose a picture</h2><br>
                <span class="userGuidance">Select a picture from Nextcloud</span>
                <br><br>
                <button type="button" id="pictureInputButton">Choose</button>
            </div>
        </div>
    </div>

    <div id="pageScrollContainer" class="pageScrollContainer">
        <span class="pageScroll" id="prevBtn"><</span>
        <span class="pageScroll" id="nextBtn">></span>
    </div>

    <div class="pages" id="panoramaPages">
    </div>
    <div id="byAnalytics" class="byAnalytics" style="display: none;">
        <img id="byAnalyticsImg" style="width: 33px; margin-right: 7px;" src="<?php echo image_path('analytics', 'app-color.svg') ?>">
        <span style="font-size: 12px; line-height: 14px;">created with<br>Analytics</span>
    </div>
</div>
