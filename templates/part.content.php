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
<div id="analytics-content" style="width:100%; padding: 20px 5%;" hidden>
    <input type="hidden" name="sharingToken" value="<?php p($_['token']); ?>" id="sharingToken">
    <input type="hidden" name="dataset" value="" id="datasetId">
    <input type="hidden" name="advanced" value="false" id="advanced">
    <input type="hidden" name="panorama" value="false" id="panorama">
    <h2 id="reportHeader"></h2>
    <h3 id="reportSubHeader" hidden></h3>
    <div id="reportPlaceholder"></div>
    <?php print_unescaped($this->inc('part.menu')); ?>
    <div id="chartContainer">
    </div>
    <div id="chartLegendContainer">
        <div id="chartLegend" class="icon icon-menu"><?php p($l->t('Legend')); ?></div>
    </div>
    <div id="tableSeparatorContainer"></div>
    <table id="tableContainer"></table>
    <div id="noDataContainer" hidden>
        <?php p($l->t('No data found')); ?>
    </div>
</div>
<div id="analytics-intro" style="padding: 30px" hidden>
    <h2><?php p($l->t('Analytics')); ?></h2>
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
<div id="analytics-warning" style="width:50%; padding: 50px">
    <h2><?php p($l->t('Analytics')); ?></h2>
    <br>
    <h3><?php p($l->t('Javascript issue')); ?></h3>
    <span><?php p($l->t('If you see this message, please disable AdBlock/uBlock for this domain (only).')); ?></span>
    <br>
    <span><?php p($l->t('The EasyPrivacy list is blocking some scripts because of a wildcard \'*analytics*\' string filter.')); ?></span>
    <br>
    <br>
    <a href="https://github.com/Rello/analytics/wiki/EasyPrivacy-Blocklist"
       target="_blank"><?php p($l->t('More Information …')); ?></a>
</div>
<div id="analytics-loading" style="width:100%; padding: 100px 5%;" hidden>
    <div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>
</div>
