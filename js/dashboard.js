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
        TYPE_EXCEL: 7,
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
                    document.getElementById('ulAnalytics').innerHTML = '<div>'
                        + t('analytics', 'Add a report to the favorites to be shown here.')
                        + '</div>';
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
        let type = jsondata['options']['visualization'];
        let value, data, subheader;

        if (jsondata['data'] && type === 'table') {
            data = jsondata['data'][jsondata['data'].length - 1];
            subheader = data[0];
            value = data[data.length - 1];
        } else if (jsondata['data']) {
            data = jsondata['data'][jsondata['data'].length - 1];
            subheader = jsondata['options']['subheader'];
            value = data[data.length - 1];
        } else {
            subheader = 'no data';
            value = 0;
            type = 'table';
        }

        let widgetRow = OCA.Analytics.Dashboard.buildWidgetRow(report, reportId, subheader, value, jsondata.thresholds);
        document.getElementById('analyticsWidgetItem' + reportId).insertAdjacentHTML('beforeend', widgetRow);
        document.getElementById('analyticsWidgetItem' + reportId).addEventListener('click', OCA.Analytics.Dashboard.handleNavigationClicked);

        if (type !== 'table') {
            document.getElementById('kpi' + reportId).remove();
            OCA.Analytics.Dashboard.buildChart(jsondata);
        } else {
            document.getElementById('chartContainer' + reportId).remove();
        }
    },

    buildWidgetRow: function (report, reportId, subheader, value, thresholds) {
        let thresholdColor = OCA.Analytics.Dashboard.validateThreshold(subheader, value, thresholds);
        //value = parseFloat(value).toLocaleString();
        value = OCA.Analytics.Dashboard.nFormatter(value);
        let href = OC.generateUrl('apps/analytics/#/r/' + reportId);

        return `<a href="${href}">
                <div class="analyticsWidgetContent1">
                    <div class="analyticsWidgetReport">${report}</div>
                    <div class="analyticsWidgetSmall">${subheader}</div>
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

    nFormatter: function (num) {
        if (num >= 1000000000) {
            return (num / 1000000000).toFixed(1).replace(/\.0$/, '') + 'G';
        }
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        }
        return num;
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

        Chart.defaults.elements.line.borderWidth = 2;
        Chart.defaults.elements.line.tension = 0.1;
        Chart.defaults.elements.point.radius = 0;
        Chart.defaults.plugins.tooltip.enabled = document.getElementById('myChart' + jsondata['options']['id']).clientHeight > 50;
        Chart.defaults.plugins.legend.display = false;

        let chartOptions = {
            bezierCurve: false, //remove curves from your plot
            scaleShowLabels: false, //remove labels
            tooltipEvents: [], //remove trigger from tooltips so they will'nt be show
            pointDot: false, //remove the points markers
            scaleShowGridLines: false, //set to false to remove the grids background
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                'primary': {
                    stacked: false,
                    position: 'left',
                    display: false,
                    grid: {
                        display: false,
                    },
                },
                'secondary': {
                    stacked: false,
                    position: 'right',
                    display: false,
                    grid: {
                        display: false,
                    },
                },
                'xAxes': {
                    type: 'category',
                    distribution: 'linear',
                    grid: {
                        display: false
                    },
                    display: false,
                },
            },
            legend: {
                display: false,
            },
            animation: {
                duration: 1500 // general animation time
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
                }
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
                    y: parseFloat(values[keyFigureColumn]),
                    x: values[characteristicColumn]
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
            chartOptions.scales['xAxes'].type = 'time';
            chartOptions.scales['xAxes'].distribution = 'linear';
        } else if (chartType === 'area') {
            chartOptions.scales['xAxes'].type = 'time';
            chartOptions.scales['xAxes'].distribution = 'linear';
            chartOptions.scales['primary'].stacked = true;
            Chart.defaults.elements.line.fill = true;
        } else if (chartType === 'doughnut') {
            chartOptions.scales['xAxes'].display = false;
            chartOptions.scales['primary'].display = false;
            chartOptions.scales['primary'].grid.display = false;
            chartOptions.scales['secondary'].display = false;
            chartOptions.scales['secondary'].grid.display = false;
            chartOptions.circumference = 180;
            chartOptions.rotation = -90;
            datasets[0]['borderWidth'] = 0;
        }


        // do the color magic
        // a predefined color array is used
        let colors = ["#aec7e8", "#ffbb78", "#98df8a", "#ff9896", "#c5b0d5", "#c49c94", "#f7b6d2", "#c7c7c7", "#dbdb8d", "#9edae5"];
        for (let i = 0; i < datasets.length; ++i) {
            let j = i - (Math.floor(i / colors.length) * colors.length)

            // in only one dataset is being shown, create a fancy gadient fill
            if (datasets.length === 1 && chartType !== 'column' && chartType !== 'doughnut') {
                const hexToRgb = colors[j].replace(/^#?([a-f\d])([a-f\d])([a-f\d])$/i
                    , (m, r, g, b) => '#' + r + r + g + g + b + b)
                    .substring(1).match(/.{2}/g)
                    .map(x => parseInt(x, 16));

                datasets[0].backgroundColor = function (context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    let gradient = ctx.createLinearGradient(0, 0, 0, chart.height);
                    gradient.addColorStop(0, 'rgb(' + hexToRgb[0] + ',' + hexToRgb[1] + ',' + hexToRgb[2] + ')');
                    gradient.addColorStop(1, 'rgb(' + hexToRgb[0] + ',' + hexToRgb[1] + ',' + hexToRgb[2] + ',0)');
                    return gradient;
                }
                datasets[i].borderColor = colors[j];
                Chart.defaults.elements.line.fill = true;
            } else if (chartType === 'doughnut') {
                // special array handling for dougnuts
                datasets[i].backgroundColor = colors;
                datasets[i].borderColor = colors;
                Chart.defaults.elements.line.fill = false;
                chartOptions.plugins.datalabels.display = true;
            } else {
                datasets[i].backgroundColor = colors[j];
                Chart.defaults.elements.line.fill = false;
                datasets[i].borderColor = colors[j];
            }
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

        // never show any axis in the dashboard
        chartOptions.scales['secondary'].display = false;
        chartOptions.scales['primary'].display = false;

        // the user can modify dataset/series settings
        // these are merged with the data array coming from the backend
        // e.g. assign one series to the secondary y-axis: '[{"yAxisID":"B"},{},{"yAxisID":"B"},{}]'
        //let userDatasetOptions = document.getElementById('userDatasetOptions').value;
        let userDatasetOptions = jsondata.options.dataoptions;
        if (userDatasetOptions !== '' && userDatasetOptions !== null) {
            datasets = cloner.deep.merge(datasets, JSON.parse(userDatasetOptions));
        }

        let myChart = new Chart(ctx, {
            plugins: [ChartDataLabels],
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