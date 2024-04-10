/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */
/** global: OCA */
/** global: OCP */
/** global: OC */
/** global: table */
/** global: Chart */
/** global: cloner */
/** global: _ */

'use strict';
let OCA;
OCA = {};

if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {
        initialDocumentTitle: null,
        isAdvanced: false,
        currentReportData: {},
        chartObject: null,
        // flexible mapping depending on type required by the used chart library
        chartTypeMapping: {
            'datetime': 'line',
            'column': 'bar',
            'columnSt': 'bar', // map stacked type also to base type; needed in filter
            'columnSt100': 'bar', // map stacked type also to base type; needed in filter
            'area': 'line',
            'line': 'line',
            'doughnut': 'doughnut'
        },
        datasources: [],
        datasourceOptions: [],
        datasets: [],
        reports: [],
        unsavedFilters: null,
        refreshTimer: null,
        currentXhrRequest: null,
    };
}

OCA.Analytics.UI = {

    initApplication: function () {
        OCA.Analytics.Visualization.hideElement('analytics-intro');
        OCA.Analytics.Visualization.hideElement('analytics-content');
        OCA.Analytics.Visualization.hideElement('analytics-loading');
        OCA.Analytics.Visualization.showElement('analytics-content');
        document.getElementById('chartContainer').innerHTML = '';
        document.getElementById('chartContainer').innerHTML = '<button id="chartZoomReset" hidden>Reset Zoom</button><canvas id="myChart" ></canvas>';
        document.getElementById('chartZoomReset').addEventListener('click', OCA.Analytics.UI.handleZoomResetButton);

        OCA.Analytics.currentReportData = JSON.parse(document.getElementById('data').value);
        // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
        OCA.Analytics.currentReportData = OCA.Analytics.Visualization.sortDates(OCA.Analytics.currentReportData);
        OCA.Analytics.currentReportData.data = OCA.Analytics.Visualization.formatDates(OCA.Analytics.currentReportData.data);

        let ctx = document.getElementById('myChart').getContext('2d');
        OCA.Analytics.Visualization.buildChart(ctx, OCA.Analytics.currentReportData, OCA.Analytics.UI.getDefaultChartOptions());
    },

    getDefaultChartOptions: function () {
        return {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                'primary': {
                    type: 'linear',
                    stacked: false,
                    position: 'left',
                    display: true,
                    grid: {
                        display: true,
                    },
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString();
                        },
                    },
                },
                'secondary': {
                    type: 'linear',
                    stacked: false,
                    position: 'right',
                    display: false,
                    grid: {
                        display: false,
                    },
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString();
                        },
                    },
                },
                'xAxes': {
                    type: 'category',
                    time: {
                        parser: 'YYYY-MM-DD HH:mm',
                        tooltipFormat: 'LL',
                    },
                    distribution: 'linear',
                    grid: {
                        display: false
                    },
                    display: true,
                },
            },
            animation: {
                duration: 0 // general animation time
            },

            tooltips: {
                callbacks: {
                    label: function (tooltipItem, data) {
//                        let datasetLabel = data.datasets[tooltipItem.datasetIndex].label || '';
                        let datasetLabel = data.datasets[tooltipItem.datasetIndex].label || data.labels[tooltipItem.index];
                        if (tooltipItem.yLabel !== '') {
                            return datasetLabel + ': ' + parseFloat(tooltipItem.yLabel).toLocaleString();
                        } else {
                            return datasetLabel;
                        }
                    }
                }
            },

            plugins: {
                datalabels: {
                    display: false,
                    formatter: (value, ctx) => {
                        let sum = 0;
                        let dataArr = ctx.chart.data.datasets[0].data;
                        dataArr.map(data => {
                            sum += data;
                        });
                        value = (value * 100 / sum).toFixed(0);
                        if (value > 5) {
                            return value + "%";
                        } else {
                            return '';
                        }
                    },
                },
            },
        };
    },
}

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.UI.initApplication();
    OCA.Analytics.Visualization.hideElement('analytics-warning');
});