<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

?>

<div id="analytics-content-intro" hidden>
    <img src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'logo.svg') ?>" style="width: 300px;margin-left: auto;margin-right: auto;/* padding-left: 100px; */display: block;">
    <br>
    <h3><?php p($l->t('Favorites')); ?></h3>
    <div>
        <ul id="ulAnalytics" style="width: 100%;"></ul>
    </div>
    <br>
    <h3><?php p($l->t('Quickstart')); ?></h3>
    <div>
        <ul id="ulQuickstart" style="width: 100%;">
            <li style="display: inline-block; margin: 10px;">
                <div class="infoBox" id="infoBoxReport"><img height="80px" width="80px"
                                                             src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'infoReport.svg') ?>"
                                                             alt="infoReport">
                    <div class="infoBoxHeader"><?php p($l->t('New Report')); ?></div>
                </div>
            </li>
            <li style="display: inline-block; margin: 10px;">
                <div class="infoBox" id="infoBoxIntro"><img height="80px" width="80px"
                                                            src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'infoIntro.svg') ?>"
                                                            alt="infoIntro">
                    <div class="infoBoxHeader"><?php p($l->t('Intro')); ?></div>
                </div>
            </li>
            <li style="display: inline-block; margin: 10px;">
                <div class="infoBox" id="infoBoxWiki"><img height="80px" width="80px"
                                                           src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'infoWiki.svg') ?>"
                                                           alt="infoWiki">
                    <div class="infoBoxHeader"><?php p($l->t('Wiki')); ?></div>
                </div>
            </li>
        </ul>
    </div>
    <br>
</div>