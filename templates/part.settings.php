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

<div id="app-settings">
    <div id="app-settings-header">
        <button name="app settings"
                class="settings-button"
                data-apps-slide-toggle="#app-settings-content">
            <?php p($l->t('Settings')); ?>
        </button>
    </div>

    <div id="app-settings-content">
        <ul id="data-settings">
            <li class="data-settings-item icon-external">
                <a href="https://github.com/rello/analytics/wiki/donate" target="_blank" style="padding: 0 20px;">
                    <?php p($l->t('Do you like this app?')); ?>
                </a>
            </li>
            <li class="data-settings-item icon-info">
                <a href="https://github.com/rello/analytics/wiki" target="_blank" style="padding: 0 20px;">
                    <?php p($l->t('More information…')); ?>
                </a>
            </li>
        </ul>
    </div>
</div>
