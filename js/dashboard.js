/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

    OCA.Dashboard.register('analytics', (el) => {
        el.innerHTML = '<ul id="ulAnalytics"><li id="ilAnalyticsFavorites">Favorites</li><li id="ilAnalyticsRecent">Recent</li></ul>';
    });
    OCA.Analytics.Dashboard.getData(20);
    OCA.Analytics.Dashboard.getData(155);
    OCA.Analytics.Dashboard.getData(103);

})

if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {};
}
/**
 * @namespace OCA.Analytics.Dashboard
 */
OCA.Analytics.Dashboard = {
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
        let subheader = jsondata['options']['subheader'];
        let data = jsondata['data'][jsondata['data'].length - 1];
        let kpi = data[0];
        let value = data[data.length - 1];

        let li = OCA.Analytics.Dashboard.buildWidgetKpiRow(report, kpi, value, jsondata.thresholds);
        document.getElementById('ilAnalyticsFavorites').insertAdjacentHTML('afterend', li);
    },

    buildWidgetKpiRow: function (report, kpi, value, thresholds) {
        let thresholdColor = OCA.Analytics.Dashboard.validateThreshold(kpi, value, thresholds)
        return `<li class="analyticsWidgetItem">
            <div style="float: left;">
                <div class="details">
                    <div class="analyticsWidgetReport">${report}</div>
                </div>
            </div>
            <div style="float: right;">
                <div class="details">
                    <div ${thresholdColor} class="analyticsWidgetValue">${parseFloat(value)}</div>
                    <div class="analyticsWidgetSmall">${kpi}</div>
                </div>
            </div>
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

}