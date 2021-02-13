/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */
/** global: OC */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.Dashboard.init();
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
        chartTypeMapping: {
            'datetime': 'line',
            'column': 'bar',
            'area': 'line',
            'line': 'line',
            'doughnut': 'doughnut'
        },
    };
}
/**
 * @namespace OCA.Analytics.Dashboard
 */
OCA.Analytics.Dashboard = {
    init: function () {
        if (typeof OCA.Dashboard === 'object') {
            OCA.Dashboard.register('analytics', (el) => {
                el.innerHTML = '<ul id="ulAnalytics"></ul>';
                OCA.Analytics.Dashboard.getFavorites();
            });
        } else if (typeof OCA.Analytics.Navigation === 'object') {
            // show favorites when the Analytics app itself is loaded
            if (decodeURI(location.hash).length === 0) {
                OCA.Analytics.Dashboard.getFavorites();
            }
        }
    },

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
                        let li = `<li id="analyticsWidgetItem${dataset}" class="analyticsWidgetItem"></li>`
                        document.getElementById('ulAnalytics').insertAdjacentHTML('beforeend', li);
                        OCA.Analytics.Dashboard.getData(dataset);
                    }
                } else {
                    document.getElementById('ulAnalytics').innerHTML = '<div>' + t('analytics', 'Add a report to the favorites to be shown here') + '</div>'
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
                if (jsondata['status'] !== 'nodata' && jsondata['data'].length > 20) {
                    jsondata['data'].slice(jsondata['data'].length - 20); //just the last 20 records for the micro charts
                }
                OCA.Analytics.Dashboard.createWidgetContent(jsondata);
            }
        };
        xhr.send();
    },

    createWidgetContent: function (jsondata) {
        let report = jsondata['options']['name'];
        let reportId = jsondata['options']['id'];
        let data = jsondata['data'][jsondata['data'].length - 1];
        let kpi = data[0];
        let value = parseFloat(data[data.length - 1]).toLocaleString();

        let widgetRow = OCA.Analytics.Dashboard.buildWidgetRow(report, reportId, kpi, value, jsondata.thresholds);
        document.getElementById('analyticsWidgetItem' + reportId).insertAdjacentHTML('beforeend', widgetRow);
        document.getElementById('analyticsWidgetItem' + reportId).addEventListener('click', OCA.Analytics.Dashboard.handleNavigationClicked);

        if (jsondata['options']['visualization'] !== 'table') {
            document.getElementById('kpi' + reportId).remove();
            OCA.Analytics.Dashboard.buildChart(jsondata);
        } else {
            document.getElementById('chartContainer' + reportId).remove();
        }
    },

    buildWidgetRow: function (report, reportId, kpi, value, thresholds) {
        let thresholdColor = OCA.Analytics.Dashboard.validateThreshold(kpi, value, thresholds);
        let href = OC.generateUrl('apps/analytics/#/r/' + reportId);

        return `<a href="${href}">
                <div class="analyticsWidgetContent1">
                    <div class="analyticsWidgetReport">${report}</div>
                    <div class="analyticsWidgetSmall">${kpi}</div>
                </div>
                <div class="analyticsWidgetContent2">
                     <div id="kpi${reportId}">
                        <div ${thresholdColor} class="analyticsWidgetValue">${value}</div>
                    </div>
                   <div id="chartContainer${reportId}">
                        <canvas id="myChart${reportId}" class="chartContainer"></canvas>
                    </div>
                </div>
            </a>`;
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

    buildChart: function (jsondata) {

        let ctx = document.getElementById('myChart' + jsondata['options']['id']).getContext('2d');
        let chartType = jsondata.options.chart;
        let datasets = [], xAxisCategories = [];
        let lastObject = false;
        let dataSeries = -1;
        let hidden = false;

        let header = jsondata.header;
        let headerKeys = Object.keys(header).length;
        let dataSeriesColumn = headerKeys - 3; //characteristic is taken from the second last column
        let characteristicColumn = headerKeys - 2; //characteristic is taken from the second last column
        let keyFigureColumn = headerKeys - 1; //key figures is taken from the last column

        Chart.defaults.global.elements.line.borderWidth = 2;
        Chart.defaults.global.elements.line.tension = 0.1;
        Chart.defaults.global.elements.line.fill = true;
        Chart.defaults.global.elements.point.radius = 0;
        Chart.defaults.global.tooltips.enabled = document.getElementById('myChart' + jsondata['options']['id']).clientHeight > 50;

        let chartOptions = {
            bezierCurve: false, //remove curves from your plot
            scaleShowLabels: false, //remove labels
            tooltipEvents: [], //remove trigger from tooltips so they will'nt be show
            pointDot: false, //remove the points markers
            scaleShowGridLines: false, //set to false to remove the grids background
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                yAxes: [{
                    id: 'primary',
                    stacked: false,
                    position: 'left',
                    display: false,
                    gridLines: {
                        display: false,
                    },
                }, {
                    id: 'secondary',
                    stacked: false,
                    position: 'right',
                    display: false,
                    gridLines: {
                        display: false,
                    },
                }],
                xAxes: [{
                    type: 'category',
                    distribution: 'linear',
                    gridLines: {
                        display: false
                    },
                    display: false,
                }],
            },
            plugins: {
                colorschemes: {
                    scheme: 'tableau.ClassicMedium10'
                }
            },
            legend: {
                display: false,
            },
            animation: {
                duration: 1500 // general animation time
            },
        };

        for (let values of jsondata.data) {
            if (dataSeriesColumn >= 0 && lastObject !== values[dataSeriesColumn]) {
                // create new dataseries for every new lable in dataSeriesColumn
                datasets.push({label: values[dataSeriesColumn], data: [], hidden: hidden});
                dataSeries++;
                // default hide > 4th series for better visibility
                if (dataSeries === 3) {
                    hidden = true;
                }
                lastObject = values[dataSeriesColumn];
            } else if (lastObject === false) {
                // when only 2 columns are provided, no label will be set
                datasets.push({label: '', data: [], hidden: hidden});
                dataSeries++;
                lastObject = true;
            }

            if (chartType === 'datetime' || chartType === 'area') {
                datasets[dataSeries]['data'].push({
                    t: values[characteristicColumn],
                    y: parseFloat(values[keyFigureColumn])
                });
            } else {
                datasets[dataSeries]['data'].push(parseFloat(values[keyFigureColumn]));
                if (dataSeries === 0) {
                    // Add category lables only once and not for every data series.
                    // They have to be unique anyway
                    xAxisCategories.push(values[characteristicColumn]);
                }
            }
        }

        if (chartType === 'datetime') {
            chartOptions.scales.xAxes[0].type = 'time';
            chartOptions.scales.xAxes[0].distribution = 'linear';
        } else if (chartType === 'area') {
            chartOptions.scales.xAxes[0].type = 'time';
            chartOptions.scales.xAxes[0].distribution = 'linear';
            chartOptions.scales.yAxes[0].stacked = true;
            Chart.defaults.global.elements.line.fill = true;
        } else if (chartType === 'doughnut') {
            chartOptions.scales.xAxes[0].display = false;
            chartOptions.scales.yAxes[0].display = false;
            chartOptions.scales.yAxes[0].gridLines.display = false;
            chartOptions.scales.yAxes[1].display = false;
            chartOptions.scales.yAxes[1].gridLines.display = false;
            chartOptions.circumference = Math.PI;
            chartOptions.rotation = -Math.PI;
            chartOptions.legend.display = false;
            datasets[0]['borderWidth'] = 0;
        }

        if (chartType !== 'column' && chartType !== 'doughnut') {
            let height = document.getElementById('myChart' + jsondata['options']['id']).clientHeight;
            let gradient = ctx.createLinearGradient(0, 0, 0, height);
            gradient.addColorStop(0, 'rgb(174,199,232)'); // '#aec7e8'
            gradient.addColorStop(1, 'rgb(174,199,232,0)');
            datasets[0]['backgroundColor'] = gradient;
        }

        // the user can add/overwrite chart options
        // the user can put the options in array-format into the report definition
        // these are merged with the standard report settings
        // e.g. the display unit for the x-axis can be overwritten '{"scales": {"xAxes": [{"time": {"unit" : "month"}}]}}'
        // e.g. add a secondary y-axis '{"scales": {"yAxes": [{},{"id":"B","position":"right"}]}}'
        let userChartOptions = jsondata.options.chartoptions;
        if (userChartOptions !== '' && userChartOptions !== null) {
            chartOptions = cloner.deep.merge(chartOptions, JSON.parse(userChartOptions));
        }

        // the user can modify dataset/series settings
        // these are merged with the data array coming from the backend
        // e.g. assign one series to the secondary y-axis: '[{"yAxisID":"B"},{},{"yAxisID":"B"},{}]'
        //let userDatasetOptions = document.getElementById('userDatasetOptions').value;
        let userDatasetOptions = jsondata.options.dataoptions;
        if (userDatasetOptions !== '' && userDatasetOptions !== null) {
            datasets = cloner.deep.merge(JSON.parse(userDatasetOptions), datasets);
        }

        let myChart = new Chart(ctx, {
            type: OCA.Analytics.chartTypeMapping[chartType],
            data: {
                labels: xAxisCategories,
                datasets: datasets
            },
            options: chartOptions,
        });
    },

    handleNavigationClicked: function (evt) {
        let reportId = evt.target.closest('a').parentElement.id.replace('analyticsWidgetItem', '');
        if (document.querySelector('#navigationDatasets [data-id="' + reportId + '"]') !== null) {
            document.querySelector('#navigationDatasets [data-id="' + reportId + '"]').click();
        }
    },
}