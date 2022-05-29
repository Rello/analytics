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
        TYPE_EMPTY_GROUP: 0,
        TYPE_INTERNAL_FILE: 1,
        TYPE_INTERNAL_DB: 2,
        TYPE_GIT: 3,
        TYPE_EXTERNAL_FILE: 4,
        TYPE_EXTERNAL_REGEX: 5,
        TYPE_EXCEL: 7,
        TYPE_SHARED: 99,
        SHARE_TYPE_USER: 0,
        SHARE_TYPE_GROUP: 1,
        SHARE_TYPE_LINK: 3,
        SHARE_TYPE_ROOM: 10,
        initialDocumentTitle: null,
        isAdvanced: false,
        currentReportData: {},
        chartObject: null,
        // flexible mapping depending on type requiered by the used chart library
        chartTypeMapping: {
            'datetime': 'line',
            'column': 'bar',
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
        OCA.Analytics.UI.hideElement('analytics-intro');
        OCA.Analytics.UI.hideElement('analytics-content');
        OCA.Analytics.UI.hideElement('analytics-loading');
        OCA.Analytics.UI.showElement('analytics-content');
        document.getElementById('chartContainer').innerHTML = '';
        document.getElementById('chartContainer').innerHTML = '<button id="chartZoomReset" hidden>Reset Zoom</button><canvas id="myChart" ></canvas>';
        document.getElementById('chartZoomReset').addEventListener('click', OCA.Analytics.UI.handleZoomResetButton);

        OCA.Analytics.currentReportData = JSON.parse(document.getElementById('data').value);
        // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
        OCA.Analytics.currentReportData.data = OCA.Analytics.UI.sortDates(OCA.Analytics.currentReportData.data);
        OCA.Analytics.currentReportData.data = OCA.Analytics.UI.formatDates(OCA.Analytics.currentReportData.data);

        OCA.Analytics.UI.buildChart(OCA.Analytics.currentReportData);
    },

    buildChart: function (jsondata) {

        OCA.Analytics.UI.showElement('tableSeparatorContainer');
        OCA.Analytics.UI.showElement('chartContainer');
        let ctx = document.getElementById('myChart').getContext('2d');

        let chartType;
        jsondata.options.chart === '' ? chartType = 'column' : chartType = jsondata.options.chart;
        let datasets = [], xAxisCategories = [];
        let lastObject = false;
        let dataSeries = -1;
        let targetDataseries = 0;
        let hidden = false;

        let header = jsondata.header;
        let headerKeys = Object.keys(header).length;
        let dataSeriesColumn = headerKeys - 3; //characteristic is taken from the second last column
        let characteristicColumn = headerKeys - 2; //characteristic is taken from the second last column
        let keyFigureColumn = headerKeys - 1; //key figures is taken from the last column

        Chart.defaults.elements.line.borderWidth = 2;
        Chart.defaults.elements.line.tension = 0.1;
        Chart.defaults.elements.line.fill = false;
        Chart.defaults.elements.point.radius = 1;
        Chart.defaults.plugins.legend.display = false;
        Chart.defaults.plugins.legend.position = 'bottom';

        var chartOptions = {
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

        for (let values of jsondata.data) {
            // indexOf will search, if an exiting dataset exists already for that label (first column)
            // internal data comes sorted from the database and the dataSeries can increment
            // external data like csv can be unsorted
            if (dataSeriesColumn >= 0 && datasets.indexOf(datasets.find(o => o.label === values[dataSeriesColumn])) === -1) {
                // create new data series for every new label in dataSeriesColumn
                datasets.push({label: values[dataSeriesColumn], data: [], hidden: hidden});
                dataSeries++;
                // default hide > 4th series for better visibility
                if (dataSeries === 3) {
                    hidden = true;
                }
                lastObject = values[dataSeriesColumn];
                targetDataseries = dataSeries;
            } else if (lastObject === false) {
                // when only 2 columns are provided, no label will be set
                datasets.push({label: '', data: [], hidden: hidden});
                dataSeries++;
                targetDataseries = dataSeries;
                lastObject = true;
            } else if (lastObject !== values[dataSeriesColumn] && lastObject !== true) {
                // find the correct dataset, where the data needs to be added to
                targetDataseries = datasets.indexOf(datasets.find(o => o.label === values[dataSeriesColumn]));
                if (targetDataseries === -1) {
                    targetDataseries = 0;
                }
                lastObject = values[dataSeriesColumn];
            }

            if (chartType === 'datetime' || chartType === 'area') {
                datasets[targetDataseries]['data'].push({
                    y: parseFloat(values[keyFigureColumn]),
                    x: values[characteristicColumn]

                });
            } else {
                datasets[targetDataseries]['data'].push(parseFloat(values[keyFigureColumn]));
                if (targetDataseries === 0) {
                    // Add category labels only once and not for every data series.
                    // They have to be unique anyway
                    xAxisCategories.push(values[characteristicColumn]);
                }
            }
        }

        if (datasets.length > 1) {
            // show legend button only when usefull with >1 dataset
            OCA.Analytics.UI.showElement('chartLegendContainer');
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
            } else {
                datasets[i].backgroundColor = colors[j];
                Chart.defaults.elements.line.fill = false;
                datasets[i].borderColor = colors[j];
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
            Chart.defaults.plugins.legend.display = true;
            chartOptions.plugins.datalabels.display = true;
        }

        // the user can add/overwrite chart options
        // the user can put the options in array-format into the report definition
        // these are merged with the standard report settings
        // e.g. the display unit for the x-axis can be overwritten '{"scales": {"xAxes": {"time": {"unit" : "month"}}}}'
        // e.g. add a secondary y-axis '{"scales":{"secondary":{"display":true}}}'

        // replace old settings from Chart.js 2
        // {"scales":{"yAxes":[{},{"display":true}]}} => {"scales":{"secondary":{"display":true}}}
        if (jsondata.options.chartoptions !== null) {
            jsondata.options.chartoptions = jsondata.options.chartoptions.replace('{"yAxes":[{},{"display":true}]}', '{"secondary":{"display":true}}');
        }
        OCA.Analytics.currentReportData.options.chartoptions = jsondata.options.chartoptions;
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
            datasets = cloner.deep.merge({}, datasets);
            datasets = cloner.deep.merge(datasets, JSON.parse(userDatasetOptions));
            datasets = Object.values(datasets);
        }

        OCA.Analytics.chartObject = new Chart(ctx, {
            plugins: [ChartDataLabels],
            type: OCA.Analytics.chartTypeMapping[chartType],
            data: {
                labels: xAxisCategories,
                datasets: datasets
            },
            options: chartOptions,
        });
    },

    showElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = false;
            //document.getElementById(element).style.removeProperty('display');
        }
    },

    hideElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = true;
            //document.getElementById(element).style.display = 'none';
        }
    },

    sortDates: function (data) {
        if (OCA.Analytics.currentReportData.options.chartoptions !== '') {
            if (JSON.parse(OCA.Analytics.currentReportData.options.chartoptions)?.scales?.xAxes?.time?.parser !== undefined) {
                let parser = JSON.parse(OCA.Analytics.currentReportData.options.chartoptions)["scales"]["xAxes"]["time"]["parser"];
                data.sort(function (a, b) {
                    let sortColumn = a.length - 2;
                    if (sortColumn === 0) {
                        return moment(a[sortColumn], parser).toDate() - moment(b[sortColumn], parser).toDate();
                    } else {
                        return a[0] - b[0] || moment(a[sortColumn], parser).toDate() - moment(b[sortColumn], parser).toDate();
                    }
                });
            }
        }
        return data;
    },

    formatDates: function (data) {
        let firstrow = data[0];
        let now;
        for (let i = 0; i < firstrow.length; i++) {
            // loop columns and check for a valid date
            if (!isNaN(new Date(firstrow[i]).valueOf()) && firstrow[i] !== null && firstrow[i].length >= 19) {
                // column contains a valid date
                // then loop all rows for this column and convert to local time
                for (let j = 0; j < data.length; j++) {
                    if (data[j][i].length === 19) {
                        // values are assumed to have a timezone or are used as UTC
                        data[j][i] = data[j][i] + 'Z';
                    }
                    now = new Date(data[j][i]);
                    data[j][i] = now.getFullYear()
                        + "-" + (now.getMonth() < 9 ? '0' : '') + (now.getMonth() + 1) //getMonth will start with Jan = 0
                        + "-" + (now.getDate() < 10 ? '0' : '') + now.getDate()
                        + " " + (now.getHours() < 10 ? '0' : '') + now.getHours()
                        + ":" + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes()
                        + ":" + (now.getSeconds() < 10 ? '0' : '') + now.getSeconds()
                }
            }
        }
        return data;
    },

}

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.UI.initApplication();
    OCA.Analytics.UI.hideElement('analytics-warning');
});