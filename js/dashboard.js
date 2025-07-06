/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
    if (typeof OCA.Dashboard === 'object') {
        OCA.Dashboard.register('analytics', (el) => {
            el.innerHTML = '<ul id="ulAnalytics"></ul>';
            OCA.Analytics.Dashboard.getFavorites();
        });
    }
})

OCA.Analytics = Object.assign({}, OCA.Analytics, {
    TYPE_GROUP: 0,
    TYPE_INTERNAL_FILE: 1,
    TYPE_INTERNAL_DB: 2,
    TYPE_GIT: 3,
    TYPE_EXTERNAL_FILE: 4,
    TYPE_EXTERNAL_REGEX: 5,
    TYPE_SPREADSHEET: 7,
    TYPE_SHARED: 99,
    // flexible mapping depending on type required by the used chart library
    // Add in all js files!
    chartTypeMapping: {
        'datetime': 'line',
        'column': 'bar',
        'columnSt': 'bar', // map stacked type also to base type; needed in filter
        'columnSt100': 'bar', // map stacked type also to base type; needed in filter
        'area': 'line',
        'line': 'line',
        'doughnut': 'doughnut',
        'funnel': 'funnel'
    },
    /**
     * Build common request headers for backend calls
     */
    headers: function () {
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');
        return headers;
    },
    stories: [],
});

/**
 * @namespace OCA.Analytics.Dashboard
 */
OCA.Analytics.Dashboard = {
    init: function () {
        OCA.Analytics.Visualization?.showContentByType('intro');
        document.getElementById('ulAnalytics').innerHTML = '';
        // show favorites when the Analytics app itself is loaded
        if (decodeURI(location.hash).length === 0) {
            OCA.Analytics.Dashboard.getFavorites();
        }
    },

    getFavorites: function () {
        const requests = [];
        requests.push(fetch(OC.generateUrl('apps/analytics/favorites'), {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        }));
        requests.push(fetch(OC.generateUrl('apps/analytics/panoramaFavorites'), {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        }));

        Promise.all(requests)
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(responseData => {
                const reportFavorites = Array.isArray(responseData[0]) ? responseData[0] : [];
                const panoramaFavorites = Array.isArray(responseData[1]) ? responseData[1] : [];

                if (reportFavorites.length > 0 || panoramaFavorites.length > 0) {
                    document.getElementById('ulAnalytics').innerHTML = '';

                    for (let dataset of reportFavorites) {
                        let li = '<li id="analyticsWidgetItem-report-' + dataset + '" class="analyticsWidgetItem"></li>';
                        document.getElementById('ulAnalytics').insertAdjacentHTML('beforeend', li);
                        OCA.Analytics.Dashboard.getData(dataset);
                    }

                    for (let panorama of panoramaFavorites) {
                        let story = OCA.Analytics.stories.find(x => parseInt(x.id) === parseInt(panorama));
                        if (story !== undefined) {
                            let li = '<li id="analyticsWidgetItem-panorama-' + panorama + '" class="analyticsWidgetItem"></li>';
                            document.getElementById('ulAnalytics').insertAdjacentHTML('beforeend', li);
                            let widgetRow = OCA.Analytics.Dashboard.buildPanoramaRow(story.name, panorama);
                            document.getElementById('analyticsWidgetItem-panorama-' + panorama).insertAdjacentHTML('beforeend', widgetRow);
                            document.getElementById('analyticsWidgetItem-panorama-' + panorama).addEventListener('click', OCA.Analytics.Dashboard.handleNavigationClicked);
                        }
                   }
                } else {
                    document.getElementById('ulAnalytics').innerHTML = '<div>'
                        + t('analytics', 'Add a report to the favorites to be shown here.')
                        + '</div>';
                }
            });
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
                try {
                    // Chart.js v4.4.3 changed from xAxes to x. In case the user has old chart options, they need to be corrected
                    let parsedChartOptions = JSON.parse(jsondata.options.chartoptions.replace(/xAxes/g, 'x'));
                    jsondata.options.chartoptions = (parsedChartOptions !== null && typeof parsedChartOptions === 'object') ? parsedChartOptions : {};
                } catch (e) {
                    jsondata.options.chartoptions = {};
                }

                try {
                    let parsedDataOptions = JSON.parse(jsondata.options.dataoptions);
                    jsondata.options.dataoptions = (parsedDataOptions !== null && typeof parsedDataOptions === 'object') ? parsedDataOptions : {};
                } catch (e) {
                    jsondata.options.dataoptions = {};
                }

                try {
                    let parsedFilterOptions = JSON.parse(jsondata.options.filteroptions);
                    jsondata.options.filteroptions = (parsedFilterOptions !== null && typeof parsedFilterOptions === 'object') ? parsedFilterOptions : {};
                } catch (e) {
                    jsondata.options.filteroptions = {};
                }

                try {
                    let parsedTableOptions = JSON.parse(jsondata.options.tableoptions);
                    jsondata.options.tableoptions = (parsedTableOptions !== null && typeof parsedTableOptions === 'object') ? parsedTableOptions : {};
                } catch (e) {
                    jsondata.options.tableoptions = {};
                }

                // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
                jsondata = OCA.Analytics.Visualization.sortDates(jsondata);
                jsondata = OCA.Analytics.Visualization.applyTimeAggregation(jsondata);
                jsondata = OCA.Analytics.Visualization.applyTopN(jsondata);

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
        document.getElementById('analyticsWidgetItem-report-' + reportId).insertAdjacentHTML('beforeend', widgetRow);
        document.getElementById('analyticsWidgetItem-report-' + reportId).addEventListener('click', OCA.Analytics.Dashboard.handleNavigationClicked);

        if (type !== 'table') {
            document.getElementById('kpi' + reportId).remove();
            let ctx = document.getElementById('myChart' + jsondata['options']['id']).getContext('2d');
            OCA.Analytics.Visualization.buildChart(ctx, jsondata, this.getDefaultChartOptions());

            //OCA.Analytics.Dashboard.buildChart(jsondata);
        } else {
            document.getElementById('chartContainer' + reportId).remove();
        }
    },

    buildWidgetRow: function (report, reportId, subheader, value, thresholds) {
        let thresholdColor = OCA.Analytics.Dashboard.validateThreshold(subheader, value, thresholds);
        //value = parseFloat(value).toLocaleString();
        value = OCA.Analytics.Dashboard.nFormatter(value);
        let href = OC.generateUrl('apps/analytics/r/' + reportId);

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

    buildPanoramaRow: function (name, panoramaId) {
        let href = OC.generateUrl('apps/analytics/pa/' + panoramaId);

        return `<a href="${href}">
                <div class="analyticsWidgetContent1">
                    <div class="analyticsWidgetReport">${name}</div>
                </div>
                <div class="analyticsWidgetContent2">
                    <span class="analyticsWidgetIcon icon-analytics-panorama"></span>
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

        // store the full chart type for deriving the stacked attribute later
        // the general chart type is used for the chart from here on
        let chartTypeFull;
        jsondata.options.chart === '' ? chartTypeFull = 'column' : chartTypeFull = jsondata.options.chart;
        let chartType = chartTypeFull.replace(/St100$/, '').replace(/St$/, '');

        // get the default settings for a chart
        let chartOptions = OCA.Analytics.Dashboard.getDefaultChartOptions();
        Chart.defaults.elements.line.borderWidth = 2;
        Chart.defaults.elements.line.tension = 0.1;
        Chart.defaults.elements.point.radius = 0;
        Chart.defaults.plugins.tooltip.enabled = document.getElementById('myChart' + jsondata['options']['id']).clientHeight > 50;
        Chart.defaults.plugins.legend.display = false;

        // convert the data array
        let [xAxisCategories, datasets] = OCA.Analytics.Dashboard.convertDataToChartJsFormat(jsondata.data, chartType, jsondata.options);

        // do the color magic
        let colors = OCA.Analytics.Visualization.defaultColorPalette;
        for (let i = 0; i < datasets.length; ++i) {
            let j = i - (Math.floor(i / colors.length) * colors.length)

            // in only one dataset is being shown, create a fancy gradient fill
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
                // special array handling for doughnuts
                if (jsondata.options.dataoptions !== null) {
                    const arr = JSON.parse(jsondata.options.dataoptions);
                    let index = 0;
                    for (const obj of arr) {
                        if (obj.backgroundColor) {
                            colors[index] = obj.backgroundColor;
                        }
                        index++;
                    }
                }
                datasets[i].backgroundColor = datasets[i].borderColor = colors;
                Chart.defaults.elements.line.fill = false;
            } else {
                datasets[i].backgroundColor = colors[j];
                Chart.defaults.elements.line.fill = false;
                datasets[i].borderColor = colors[j];
            }
        }

        // derive the stacked or the stacked-100 option and adjust the data and options
        let stacked = chartTypeFull.endsWith('St') || chartTypeFull.endsWith('St100');
        let stacked100 = chartTypeFull.endsWith('St100');
        if (stacked === true) {
            chartOptions.scales['primary'].stacked = chartOptions.scales['xAxes'].stacked = true;
        }
        if (stacked100 === true) {
            datasets = OCA.Analytics.Report.calculateStacked100(datasets);
            chartOptions.scales['primary'].max = 100;
        }

        // overwrite some default chart options depending on the chart type
        if (chartType === 'datetime') {
            chartOptions.scales['x'].type = 'time';
            chartOptions.scales['x'].distribution = 'linear';
        } else if (chartType === 'area') {
            chartOptions.scales['x'].type = 'time';
            chartOptions.scales['x'].distribution = 'linear';
            chartOptions.scales['primary'].stacked = true;
            chartOptions.scales['x'].stacked = false; // area does not work otherwise
            Chart.defaults.elements.line.fill = true;
        } else if (chartType === 'doughnut') {
            chartOptions.scales['x'].display = false;
            chartOptions.scales['primary'].display = chartOptions.scales['primary'].grid.display = false;
            chartOptions.scales['secondary'].display = chartOptions.scales['secondary'].grid.display = false;
            chartOptions.circumference = 180;
            chartOptions.rotation = -90;
            datasets[0]['borderWidth'] = 0;
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
        if (userDatasetOptions !== '' && userDatasetOptions !== null && chartType !== 'doughnut') {
            let numberOfDatasets = datasets.length;
            let userDatasetOptionsCleaned = JSON.parse(userDatasetOptions);
            datasets = cloner.deep.merge({}, datasets);
            datasets = cloner.deep.merge(datasets, userDatasetOptionsCleaned);
            datasets = Object.values(datasets);
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

    getDefaultChartOptions: function () {
        return {
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
                'x': {
                    type: 'category',
                    distribution: 'linear',
                    grid: {
                        display: false
                    },
                    display: false,
                },
            },
            animation: {
                duration: 1500 // general animation time
            },
            plugins: {
                legend: {
                    display: false,
                },
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
    },

    convertDataToChartJsFormat: function (data, chartType, options) {
        const isTopGrouping = options?.filteroptions?.group?.type === 'top';
        let datasets = [], xAxisCategories = [];
        let datasetCounter = 0;
        const dataModel = options?.chartoptions?.analyticsModel ?? '';

        if (dataModel === 'accountModel') {
            const header = data.header.slice(1);
            const rows = data.data;
            xAxisCategories = header;
            rows.forEach(row => {
                const label = row[0];
                const dataPoints = row.slice(1).map((value, index) => ({x: xAxisCategories[index], y: value}));
                datasets.push({label: label, data: dataPoints});
            });
        } else if (dataModel === 'timeSeriesModel') {
            const header = data.header.slice(1);
            const rows = data.data;
            xAxisCategories = rows.map(r => r[0]);
            header.forEach((seriesName, index) => {
                const dataset = {label: seriesName, data: [], hidden: datasetCounter >= 4 && !isTopGrouping};
                rows.forEach(row => {
                    dataset.data.push({x: row[0], y: parseFloat(row[index + 1])});
                });
                datasets.push(dataset);
                datasetCounter++;
            });
        } else {
            const labelMap = new Map();
            data.forEach((row) => {
                let dataSeriesColumn, characteristicColumn, value;
                if (row.length >= 3) {
                    [dataSeriesColumn, characteristicColumn, value] = row.slice(-3);
                } else if (row.length === 2) {
                    [characteristicColumn, value] = row;
                    dataSeriesColumn = '';
                }

                if (!xAxisCategories.includes(characteristicColumn)) {
                    xAxisCategories.push(characteristicColumn);
                }

                if (!labelMap.has(dataSeriesColumn)) {
                    labelMap.set(dataSeriesColumn, {
                        ...(chartType !== 'doughnut' && {label: dataSeriesColumn || undefined}),
                        data: [],
                        hidden: datasetCounter >= 4 && !isTopGrouping
                    });
                    datasetCounter++;
                }

                const dataset = labelMap.get(dataSeriesColumn);
                if (chartType === 'doughnut') {
                    dataset.data.push(parseFloat(value));
                } else {
                    dataset.data.push({x: characteristicColumn, y: parseFloat(value)});
                }
            });
            datasets = Array.from(labelMap.values());
        }

        if (chartType === 'doughnut') {
            datasets = [{data: datasets.flatMap(d => d.data)}];
        }

        return [xAxisCategories, datasets];
    },

    handleNavigationClicked: function (evt) {
        if (typeof OCA.Dashboard !== 'object') {
            evt.preventDefault();
            history.pushState(null, '', evt.target.href);
        }

        let liId = evt.target.closest('a').parentElement.id;
        let itemType = liId.includes('panorama') ? 'panorama' : 'report';
        let id = liId.replace('analyticsWidgetItem-' + itemType + '-', '');
        let selector = '#navigationDatasets [data-id="' + id + '"][data-item_type="' + itemType + '"]';
        if (document.querySelector(selector) !== null) {
            document.querySelector(selector).click();
        }
    },
}