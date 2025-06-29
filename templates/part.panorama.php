<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>

<style>
    .bg-azure {
        background-color: azure;
    }

    #optionBtnContainer {
        z-index: 9999;
        position: relative;
        top: 5px;
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

    .editMenuContainer {
        position: fixed;
        width: 250px;
        height: 250px;
        top: 40%;
        left: 50%;
        z-index: 1000;
    }

    .editMenu {
        position: relative;
        width: 100%;
        height: 100%;
    }

    .menu-item {
        position: absolute;
        width: 60px;
        height: 60px;
        text-align: center;
        line-height: 60px;
        background-color: var(--color-info);
        color: white;
        border-radius: 50%;
        cursor: pointer;
        /* You'll need to adjust the following transform values for each item
        transform: translate(0px, 0px); */
    }

    .menu-item:hover {
        background-color: var(--color-info-hover);
    }

    .editMenu .close-menu-item {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        /* Additional styles for the close button if necessary */
    }

    /* Modal styles */
    .modal {
        display: none; /* Hidden by default */
        position: fixed; /* Stay in place */
        z-index: 1001; /* Sit on top */
        left: 0;
        top: 0;
        width: 100%; /* Full width */
        height: 100%; /* Full height */
        overflow: auto; /* Enable scroll if needed */
        background-color: rgb(0, 0, 0); /* Fallback color */
        background-color: rgba(0, 0, 0, 0.4); /* Black w/ opacity */
    }

    .modal-content {
        width: 300px;
        height: 400px;
        top: 40%;
        left: 50%;
        background-color: #fefefe;
        padding: 20px;
        border: 1px solid #888;
        position: relative;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    .overlay {
        position: absolute;
        top: 20px;
        left: 20px;
        right: 20px;
        bottom: 20px;
        background-color: rgba(0, 0, 0, 0.4); /* Semi-transparent overlay */
        z-index: 5; /* Ensure it's above content but below menu */
        align-items: center;
        display: flex;
        justify-content: center;
        cursor: pointer;
    }

    .overlay.active {
        background-color: rgba(0, 0, 0, 0.1); /* Semi-transparent overlay */
    }

    .overlayText {
        background-color: white;
        padding: 10px;
        cursor: pointer;
        border-radius: var(--border-radius-element, var(--border-radius-pill));
    }

    .overlayOptions {
        border-radius: var(--border-radius-element, var(--border-radius-pill));
        position: absolute;
        bottom: 10px;
        background-color: rgba(255,255,255,0.8);
        font-size: 12px;
        padding: 2px 4px;
        display: flex;
        align-items: center;
        gap: 4px; /* Optional: adds space between checkbox and label */
    }

    #reportSelectorContainer {
        max-height: 280px; /* Adjust the max height as needed */
        overflow-y: auto; /* Enables vertical scrolling when content overflows */
    }

    .reportSelectorItem {
        padding: 1px;
        border-radius: var(--border-radius-element, var(--border-radius-pill));
    }

    .reportSelectorItem:hover {
        cursor: pointer;
        background-color: var(--color-primary-element-hover);
        color: var(--color-primary-text);
        border-radius: var(--border-radius-element, var(--border-radius-pill));
    }

    .reportSelectorItem.selected {
        background-color: var(--color-primary-element-light);
        color: var(--color-main-text);
        border-radius: var(--border-radius-element, var(--border-radius-pill));
    }

    /* Layout Selector */
    .layoutModal {
        position: fixed;
        z-index: 10;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .layoutModalContent {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 60%;
    }

    .layoutModalGrid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }

    .layoutModalGridCell {
        width: calc(25% - 20px); /* Adjust based on gap size */
        border: 1px solid #ddd;
        padding: 5px;
        box-sizing: border-box;
    }

    .layoutModalGridCell:hover {
        cursor: pointer;
        background-color: var(--color-primary-light-hover);
    }

    .layoutModalName {
        text-align: center;
        margin-top: 5px;
        font-size: 14px;
        color: #333;
    }

    .layoutModalHeader {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
    }

    .layoutModalTitle {
        font-size: 20px;
        font-weight: bold;
    }

    .layoutModalGridPreview {
        /* Additional styling for layout preview */
        height: 100px; /* Set a fixed height for previews */
        overflow: hidden; /* Hide overflow */
    }

    .layoutModalGridPreview .flex-item {
        padding: 0;
        margin: 2px;
        border: 1px solid #888;
    }

    .heading-anchor,
    .text-readonly-bar {
        display: none !important;
    }
</style>
<div id="analytics-content-panorama" hidden>

    <div id="panoramaHeaderRow" class="panoramaHeaderRow">
        <div id="panoramaHeader" class="reportHeader editable"></div>
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
        <div id="modalReport" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Choose a report</h2>
                <div id="reportSelectorContainer"></div>
            </div>
        </div>
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

    <!-- LayoutChooser -->
    <div id="layoutModal" class="layoutModal" style="display:none;">
        <div class="layoutModalContent">
            <div class="layoutModalHeader">
                <span class="layoutModalTitle">Select Layout</span>
                <span id="layoutModalClose" class="close">&times;</span>
            </div>
            <div id="layoutModalGrid" class="layoutModalGrid">
                <!-- Layout previews will be dynamically inserted here -->
            </div>
        </div>
    </div>

    <div class="pages" id="panoramaPages">
    </div>
    <div id="byAnalytics" class="byAnalytics" style="display: none;">
        <img id="byAnalyticsImg" style="width: 33px; margin-right: 7px;" src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'app-color.svg') ?>">
        <span style="font-size: 12px; line-height: 14px;">created with<br>Analytics</span>
    </div>
</div>