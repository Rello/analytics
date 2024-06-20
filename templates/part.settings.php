<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>

<div id="app-settings">
    <div id="app-settings-header">
        <button name="app settings" id="appSettingsButton"
                class="settings-button"
                data-apps-slide-toggle="#app-settings-content">
            <?php p($l->t('Settings')); ?>
        </button>
    </div>

    <div id="app-settings-content" style="display: none;">
        <ul id="analytics-settings">
            <li class="analytics-settings-item icon-external">
                <button id="importDatasetButton" style="padding: 0 25px;">
                    <?php p($l->t('Import report')); ?>
                </button>
                <input type="file" id="importFile" accept="text/plain" hidden>
            </li>
            <li class="analytics-settings-item icon-external">
                <a href="https://github.com/rello/analytics/wiki/donate" target="_blank" style="padding: 0 25px;">
                    <?php p($l->t('Do you like this app?')); ?>
                </a>
            </li>
            <li class="analytics-settings-item icon-info">
                <a id="wizardStart" style="padding: 0 25px;">
                    <?php p($l->t('Introduction')); ?>
                </a>
            </li>
            <li class="analytics-settings-item icon-info">
                <a href="https://github.com/rello/analytics/wiki" target="_blank" style="padding: 0 25px;">
                    <?php p($l->t('More information …')); ?>
                </a>
            </li>
        </ul>
    </div>
</div>
