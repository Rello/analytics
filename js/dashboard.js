/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */
/** global: OC */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

    OCA.Dashboard.register('analytics', (el) => {
        el.innerHTML = '<ul id="ulAnalytics"></ul>';
    });
    OCA.Analytics.Dashboard.getFavorites();
})

if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {
        TYPE_EMPTY_GROUP: 0,
        TYPE_INTERNAL_FILE: 1,
        TYPE_INTERNAL_DB: 2,
        TYPE_GIT: 3,
        TYPE_EXTERNAL_FILE: 4,
        TYPE_EXTERNAL_REGEX: 5,
        TYPE_SHARED: 99,
    };
}
/**
 * @namespace OCA.Analytics.Dashboard
 */
OCA.Analytics.Dashboard = {
    getFavorites: function () {
        const url = OC.generateUrl('apps/analytics/favorites', true);

        let xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.response !== '[]') {
                    for (let dataset of JSON.parse(xhr.response)) {
                        OCA.Analytics.Dashboard.getData(dataset);
                    }
                } else {
                    document.getElementById('ulAnalytics').parentElement.innerHTML = '<div class="empty-content">' + t('analytics', 'Favorites are shown here') + '</div>'
                }
            }
        };
        xhr.send();
    },

    getData: function (datasetId) {
        const url = OC.generateUrl('apps/analytics/data/' + datasetId, true);

        let xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                let jsondata = JSON.parse(xhr.response);
                OCA.Analytics.Dashboard.createWidgetContent(jsondata);
            }
        };
        xhr.send();
    },

    createWidgetContent: function (jsondata) {
        let report = jsondata['options']['name'];
        let reportId = jsondata['options']['id'];
        let data = jsondata['data'][jsondata['data'].length - 1];
        let type = jsondata['options']['type'];
        let kpi = data[0];
        let value = parseFloat(data[data.length - 1]).toLocaleString();

        let li = OCA.Analytics.Dashboard.buildWidgetKpiRow(report, reportId, type, kpi, value, jsondata.thresholds);
        document.getElementById('ulAnalytics').insertAdjacentHTML('beforeend', li);
    },

    buildWidgetKpiRow: function (report, reportId, type, kpi, value, thresholds) {
        let thresholdColor = OCA.Analytics.Dashboard.validateThreshold(kpi, value, thresholds);
        let typeIcon = OCA.Analytics.Dashboard.validateIcon(type);
        let href = OC.generateUrl('apps/analytics/#/r/' + reportId);

        return `<li class="analyticsWidgetItem">
            <a href="${href}">
                <div class="analyticsWidgetIcon ${typeIcon}" class="analyticsWidgetIcon"></div>           
                <div style="float: left;">
                    <div><div class="analyticsWidgetReport">${report}</div></div>
                        <div class="analyticsWidgetSmall">${kpi}</div>
                </div>
                <div style="float: right;">
                    <div>
                        <div ${thresholdColor} class="analyticsWidgetValue">${value}</div>
                    </div>
                </div>
            </a>
        </li>`;
    },

    validateThreshold: function (kpi, value, thresholds) {
        const operators = {
            '=': function (a, b) {
                return a === b
            },
            '<': function (a, b) {
                return a < b
            },
            '>': function (a, b) {
                return a > b
            },
            '<=': function (a, b) {
                return a <= b
            },
            '>=': function (a, b) {
                return a >= b
            },
            '!=': function (a, b) {
                return a !== b
            },
        };
        let thresholdColor;

        thresholds = thresholds.filter(p => p.dimension1 === kpi || p.dimension1 === '*');

        for (let threshold of thresholds) {
            const comparison = operators[threshold['option']](parseFloat(value), parseFloat(threshold['value']));
            threshold['severity'] = parseInt(threshold['severity']);
            if (comparison === true) {
                if (threshold['severity'] === 2) {
                    thresholdColor = 'style="color: red;"';
                } else if (threshold['severity'] === 3) {
                    thresholdColor = 'style="color: orange;"';
                } else if (threshold['severity'] === 4) {
                    thresholdColor = 'style="color: green;"';
                }
            }
        }

        return thresholdColor;
    },

    validateIcon: function (type) {
        let typeINT = parseInt(type);
        let typeIcon;
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (typeINT === OCA.Analytics.TYPE_GIT || typeINT === OCA.Analytics.TYPE_EXTERNAL_FILE) {
            typeIcon = 'icon-external';
        } else if (typeINT === OCA.Analytics.TYPE_SHARED) {
            typeIcon = 'icon-shared';
        }
        return typeIcon;
    },
}