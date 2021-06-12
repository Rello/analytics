<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */
?>

<template id="wizardDialog">
    <div class="modal-mask" id="firstrunwizard"
         style="touch-action: pan-y; user-select: none; -webkit-user-drag: none; -webkit-tap-highlight-color: rgba(0, 0, 0, 0);"
         hidden>

        <div class="modal-header-top">
            <div class="icons-menu">
                <button id="wizardClose" class="action-item action-item--single icon-close">
                </button>
            </div>
        </div>
        <div class="modal-wrapper modal-wrapper--normal" style="">
            <a class="prev" style="">
                <div id="wizardPrevious" class="icon icon-view-previous icon-white">
                    <span class="hidden-visually">Previous</span>
                </div>
            </a>
            <div class="modal-container">
                <div class="modal-header">
                    <div class="firstrunwizard-header">
                        <div class="logo"><p class="hidden-visually">NC20</p></div>
                        <h2></h2>
                        <p></p></div>
                </div>
                <div id="pageBody" class="modal-body">
                </div>
                <div class="modal-footer"
                ">
                <div id="wizardDot1" class="dot active"></div>
                <div id="wizardDot2" class="dot"></div>
                <div id="wizardDot3" class="dot"></div>
                <div id="wizardDot4" class="dot"></div>
                <div id="wizardDot5" class="dot"></div>
                <div id="wizardDot6" class="dot"></div>
            </div>
        </div>
        <a class="next" style="">
            <div id="wizardNext" class="icon icon-view-next icon-white">
                <span class="hidden-visually">Next</span>
            </div>
        </a>
    </div>
</template>

<template id="wizard-start">
    <div class="page">
        <div class="content content-values">
            <h3>Nextcloud Analytics makes your data visible and helps you to evaluate them - from financial data to IoT
                logs<br>Give your numbers a meaning</h3>
            <ul id="wizard-values">
                <li>
                    <span class="icon-timezone"></span>
                    <h3>Data is processed inside Nextcloud</h3>
                </li>
                <li>
                    <span class="icon-shared"></span>
                    <h3>Share your reports and insights</h3>
                </li>
                <li>
                    <span class="icon-projects"></span>
                    <h3>Fully integrated into Nextcloud</h3>
                </li>
                <li>
                    <span class="icon-user"></span>
                    <h3>100% Open Source</h3>
                </li>
            </ul>
        </div>
    </div>
</template>
<template id="wizard-charts">
    <div>
        <div class="page">
            <div class="image"><img
                        src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_charts.png') ?>">
            </div>
            <div class="description">
                <h3>Visualize any data with easy to use reports</h3>
                <ul>
                    <li>Different chart types like lines, columns or doughnuts</li>
                    <li>Customize further chart options by using advanced scripting</li>
                    <li>Show your most important insights in the Nextcloud Dashboard</li>
                    <li>Interactive tables</li>
                    <li>Use thresholds to mark exceptions or receive Nextcloud notifications</li>
                </ul>
            </div>
        </div>
    </div>
</template>
<template id="wizard-filter">
    <div>
        <div class="page">
            <div class="image"><img
                        src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_filter.gif') ?>">
            </div>
            <div class="description">
                <h3>Slice and dice your data</h3>
                <ul>
                    <li>Different filter types like "equal", "list of values" or "contains"</li>
                    <li>Change drilldowns by removing columns</li>
                    <li>Customize chats by assigning primary or secondary axis</li>
                    <li>Use different chart types per data series</li>
                    <li>Save filters as default</li>
                </ul>
            </div>
        </div>
    </div>
</template>
<template id="wizard-datasource">
    <div>
        <div class="page">
            <div class="image"><img
                        src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_datasources.gif') ?>">
            </div>
            <div class="description">
                <h3>Use data from various datasources</h3>
                <ul>
                    <li>Internal data is persisted in the database and delivers the most flexibility and performance
                    </li>
                    <li>Use data from Github to monitor download statistics in realtime</li>
                    <li>The JSON datasoruce can read data from external services</li>
                    <li>With the HTML-Grabber, almost any website data can be extracted</li>
                    <li>Read data from internal Nextcloud files to visualize them in realtime</li>
                </ul>
            </div>
        </div>
    </div>
</template>
<template id="wizard-dataload">
    <div>
        <div class="page">
            <div class="image"><img
                        src="<?php echo \OC::$server->getURLGenerator()->imagePath('analytics', 'wizard_dataload.gif') ?>">
            </div>
            <div class="description">
                <h3>Advanced configuration: Dataloads</h3>
                <ul>
                    <li>Any datasource can be persisted in Nextcloud</li>
                    <li>Use timestamps to historize data</li>
                    <li>Full (deletion of old data) or delta loads</li>
                    <li>Schedule dataloads in the background daily or hourly</li>
                    <li>Trigger dataloads via scripts using the occ command</li>
                    <li>Use the REST API to push data into Analytics</li>
                </ul>
            </div>
        </div>
    </div>
</template>
<template id="wizard-final">
    <div>
        <div class="page content-final">
            <div class="description-wide">
                <div class="description-block">
                    <h3 class="icon-info">Get more information</h3>
                    <ul>
                        <li><a href="https://github.com/Rello/analytics/wiki" target="_blank" rel="noreferrer noopener">Wiki</a>
                        </li>
                        <li><a href="https://help.nextcloud.com/c/apps/analytics/159" target="_blank"
                               rel="noreferrer noopener">Nextcloud forum</a></li>
                    </ul>
                </div>
                <br>
                <div class="description-block">
                    <p>You can open this introduction again by selecting "About" in the Analytics settings section</p>
                </div>
            </div>
            <div class="description-wide">
                <div class="description-block">
                    <h3 class="icon-link">Quickstart</h3>
                    <p>Activate a set of demo report to show how Analytics works</p>
                    <button id="wizardDemo">
                        Create Demo
                    </button>
                </div>
                <br><br>
                <div class="description-block">
                    <button id="wizardEnd" class="primary modal-default-button">
                        Start using Analytics
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
