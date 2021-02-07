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

<template id="wizzardDialog">
    <div class="modal-mask" id="firstrunwizard"
         style="touch-action: pan-y; user-select: none; -webkit-user-drag: none; -webkit-tap-highlight-color: rgba(0, 0, 0, 0);"
         hidden>
        <div class="modal-wrapper modal-wrapper--normal" style="">
            <a class="prev" style="">
                <div id="wizzardPrevious" class="icon icon-view-previous icon-white">
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
            </div>
            <a class="next" style="">
                <div id="wizzardNext" class="icon icon-view-next icon-white">
                    <span class="hidden-visually">Next</span>
                </div>
            </a>
        </div>
    </div>
</template>

<template id="wizzard-start">
    <div class="page">
        <div class="content content-values">
            <h3>Nextcloud Analytics makes your data visible and helps you to understand it. From financial data to IoT
                logs.<br>Give your numbers a meaning</h3>
            <ul id="wizard-values">
                <li>
                    <span class="icon-timezone"></span>
                    <h3>Your data is processed inside Nextcloud</h3>
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
<template id="wizzard-charts">
    <div>
        <div class="page">
            <div class="image"><img src="/nextcloud/own_apps20/analytics/img/wizzard_charts.png"></div>
            <div class="description">
                <h3>Visualize almost any data with easy to use reports</h3>
                <ul>
                    <li>Different chart types like lines, bars or doughnuts</li>
                    <li>Fully customize the chart layout by using advanced scripting</li>
                    <li>Interactive tables for any data</li>
                    <li>Use thresholds to mark exceptions and receive Nextcloud notifications</li>
                    <li>Show your most important insights in the Nextcloud Dashboard</li>
                </ul>
            </div>
        </div>
    </div>
</template>
<template id="wizzard-filter">
    <div>
        <div class="page">
            <div class="image"><img src="/nextcloud/own_apps20/analytics/img/wizzard_filter.gif"></div>
            <div class="description">
                <h3>Slice and dice your data</h3>
                <ul>
                    <li>Different filter types like "equal", "list of values" or "contains"</li>
                    <li>Change drilldowns by removing columns</li>
                    <li>Customize chats by assigning primary or secondary axis</li>
                    <li>Use different chart types per data series</li>
                    <li>Save filters and reuse them next time</li>
                </ul>
            </div>
        </div>
    </div>
</template>
<template id="wizzard-datasource">
    <div>
        <div class="page">
            <div class="image"><img src="/nextcloud/own_apps20/analytics/img/wizzard_datasources.gif"></div>
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

<template id="wizzard-dataload">
    <div>
        <div class="page">
            <div class="image"><img src="/nextcloud/own_apps20/analytics/img/wizzard_dataload.gif"></div>
            <div class="description">
                <h3>Advanced configuration: Dataloads</h3>
                <ul>
                    <li>Any datasource can be persisted in nextcloud</li>
                    <li>Use the timestamp feature to keep a history</li>
                    <li>Full (deletion of old data) or delta loads are possible</li>
                    <li>Schedule loads in the background daily or hourly</li>
                    <li>Use the REST API to push data into reports</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<template id="wizzard-final">
    <div>
        <div class="page content-final">
            <div class="description-wide">
                <div class="description-block">
                    <h3 class="icon-info">Get more information</h3>
                    <p>More information is available here:</p>
                    <ul>
                        <li><a href="https://github.com/Rello/analytics/wiki" target="_blank" rel="noreferrer noopener">Wiki</a>
                        </li>
                        <li><a href="https://help.nextcloud.com/c/apps/analytics/159" target="_blank"
                               rel="noreferrer noopener">Nextcloud forum</a></li>
                    </ul>
                </div>
                <br>
                <div class="description-block">
                    <p>This wizzard can be opened again by selecting "About" in the Analytics settings section.</p>
                </div>
            </div>
            <div class="description-wide">
                <div class="description-block">
                    <h3 class="icon-link">Quickstart</h3>
                    <p>Activate a set of demo report to show how Analytics works</p>
                    <button id="wizzardDemo">
                        Create Demo
                    </button>
                </div>
                <br><br>
                <div class="description-block">
                    <button id="wizzardEnd" class="primary modal-default-button">
                        Start using Analytics
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
