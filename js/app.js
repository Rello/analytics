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
/** global: t */
/** global: table */
/** global: Chart */
/** global: cloner */
/** global: _ */

'use strict';

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
        tableObject: null,
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

        headers: function () {
            let headers = new Headers();
            headers.append('requesttoken', OC.requestToken);
            headers.append('OCS-APIREQUEST', 'true');
            headers.append('Content-Type', 'application/json');
            return headers;
        }
    };
}
/**
 * @namespace OCA.Analytics.Core
 */
OCA.Analytics.Core = {
    initApplication: function () {
        OCA.Analytics.Backend.getDatasourceDefinitions();

        const urlHash = decodeURI(location.hash);
        if (urlHash.length > 1) {
            if (urlHash[2] === 'f') {
                window.location.href = '#';
                OCA.Analytics.Sidebar.Report.createFromDataFile(urlHash.substring(3));
            } else if (urlHash[2] === 'r') {
                OCA.Analytics.Navigation.init((parseInt(urlHash.substring(4))));
            }
        } else {
            OCA.Analytics.Navigation.init();
        }
    },

    getDistinctValues: function (array) {
        let unique = [];
        let distinct = [];
        if (array === undefined) {
            return distinct;
        }
        for (let i = 0; i < array.length; i++) {
            if (!unique[array[i][0]]) {
                distinct.push(array[i][0]);
                unique[array[i][0]] = 1;
            }
        }
        return distinct;
    },

    getInitialState: function (key) {
        const app = 'analytics';
        const elem = document.querySelector('#initial-state-' + app + '-' + key);
        if (elem === null) {
            return false;
        }
        return JSON.parse(atob(elem.value))
    },

    openWiki: function () {
        window.open('https://github.com/rello/analytics/wiki', '_blank');
    }
};

OCA.Analytics.UI = {

    buildDataTable: function (jsondata) {
        OCA.Analytics.UI.showElement('tableContainer');
        //OCA.Analytics.UI.showElement('tableMenuBar');

        let columns = [];
        let data, unit = '';

        let header = jsondata.header;
        let allDimensions = jsondata.dimensions;
        (jsondata.dimensions) ? allDimensions = jsondata.dimensions : allDimensions = jsondata.header;
        let headerKeys = Object.keys(header);
        for (let i = 0; i < headerKeys.length; i++) {
            columns[i] = {'title': (header[headerKeys[i]] !== null) ? header[headerKeys[i]] : ""};
            let columnType = Object.keys(allDimensions).find(key => allDimensions[key] === header[headerKeys[i]]);

            if (i === headerKeys.length - 1) {
                // this is the last column

                // prepare for later unit cloumn
                //columns[i]['render'] = function(data, type, row, meta) {
                //    return data + ' ' + row[row.length-2];
                //};
                if (header[headerKeys[i]] !== null && header[headerKeys[i]].length === 1) {
                    unit = header[headerKeys[i]] + ' ';
                }
                //columns[i]['render'] = DataTable.render.number(null, null, 2, unit + ' ');
                columns[i]['render'] = function (data, type, row, meta) {
                    // If display or filter data is requested, format the number
                    if (type === 'display' || type === 'filter') {
                        return unit + parseFloat(data).toLocaleString();
                    }
                    // Otherwise the data type requested (`type`) is type detection or
                    // sorting data, for which we want to use the integer, so just return
                    // that, unaltered
                    return data;
                }
                columns[i]['className'] = 'dt-right';
            } else if (columnType === 'timestamp') {
                columns[i]['render'] = function (data, type) {
                    // If display or filter data is requested, format the date
                    if (type === 'display' || type === 'filter') {
                        return new Date(data * 1000).toLocaleString();
                    }
                    // Otherwise the data type requested (`type`) is type detection or
                    // sorting data, for which we want to use the integer, so just return
                    // that, unaltered
                    return data;
                }
            } else if (columnType === 'unit') {
                columns[i]['visible'] = false;
                columns[i]['searchable'] = false;
            }
        }
        data = jsondata.data;

        const language = {
            // TRANSLATORS Noun
            search: t('analytics', 'Search'),
            lengthMenu: t('analytics', 'Show _MENU_ entries'),
            info: t('analytics', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            infoEmpty: t('analytics', 'Showing 0 to 0 of 0 entries'),
            paginate: {
                // TRANSLATORS pagination description non-capital
                first: t('analytics', 'first'),
                // TRANSLATORS pagination description non-capital
                previous: t('analytics', 'previous'),
                // TRANSLATORS pagination description non-capital
                next: t('analytics', 'next'),
                // TRANSLATORS pagination description non-capital
                last: t('analytics', 'last')
            },
        };

        OCA.Analytics.tableObject = new DataTable(document.getElementById("tableContainer"), {
            //dom: 'tipl',
            data: data,
            columns: columns,
            language: language,
            rowCallback: function (row, data, index) {
                OCA.Analytics.UI.dataTableRowCallback(row, data, index, jsondata.thresholds)
            },
            initComplete: function () {
                let info = this.closest('.dataTables_wrapper').find('.dataTables_info');
                info.toggle(this.api().page.info().pages > 1);
                let length = this.closest('.dataTables_wrapper').find('.dataTables_length');
                length.toggle(this.api().page.info().pages > 1);
                let filter = this.closest('.dataTables_wrapper').find('.dataTables_filter');
                filter.toggle(this.api().page.info().pages > 1);
            },
            drawCallback: function () {
                let pagination = this.closest('.dataTables_wrapper').find('.dataTables_paginate');
                pagination.toggle(this.api().page.info().pages > 1);

            }
        });
    },

    dataTableRowCallback: function (row, data, index, thresholds) {
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

        thresholds = thresholds.filter(p => (p.dimension1 === data[0] || p.dimension1 === '*') && p.option !== 'new');

        let color;
        let severity;
        for (let threshold of thresholds) {
            // use the last column for comparison of the value
            const comparison = operators[threshold['option']](parseFloat(data[data.length - 1]), parseFloat(threshold['value']));
            severity = parseInt(threshold['severity']);
            if (comparison === true) {
                if (severity === 2) {
                    color = 'red';
                } else if (severity === 3) {
                    color = 'orange';
                } else if (severity === 4) {
                    color = 'green';
                }

                if (data.length > 3) {
                    // external data source
                    row.style.color = color;
                } else {
                    row.childNodes.item(data.length - 1).style.color = color;
                }
            }
        }
    },

    buildChart: function (jsondata) {

        const defaultLegendClickHandler = Chart.defaults.plugins.legend.onClick;
        const pieDoughnutLegendClickHandler = Chart.controllers.doughnut.overrides.plugins.legend.onClick;
        const newLegendClickHandler = function (e, legendItem, legend) {
            const index = legendItem.datasetIndex;
            const type = legend.chart.config.type;

            // Do the original logic
            if (type === 'pie' || type === 'doughnut') {
                pieDoughnutLegendClickHandler(e, legendItem, legend)
            } else {
                defaultLegendClickHandler(e, legendItem, legend);
            }
            document.getElementById('saveIcon').style.removeProperty('display');

        };

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
        Chart.defaults.plugins.legend.onClick = newLegendClickHandler;

        let chartOptions = {
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
                        if (tooltipItem['yLabel'] !== '') {
                            return datasetLabel + ': ' + parseFloat(tooltipItem['yLabel']).toLocaleString();
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
                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                        modifierKey: 'ctrl',
                    },
                    zoom: {
                        drag: {
                            enabled: true
                        },
                        mode: 'x',
                        onZoom: this.toggleZoomResetButton,
                    },
                }
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
            let numberOfDatasets = datasets.length;
            let userDatasetOptionsCleaned = JSON.parse(userDatasetOptions);
            userDatasetOptionsCleaned.length = numberOfDatasets; // cut saved definitions if report now has less data sets
            datasets = cloner.deep.merge({}, datasets);
            datasets = cloner.deep.merge(datasets, userDatasetOptionsCleaned);
            datasets = Object.values(datasets);
        }

        OCA.Analytics.chartObject = new Chart(ctx, {
            plugins: [ChartDataLabels, ChartZoom],
            type: OCA.Analytics.chartTypeMapping[chartType],
            data: {
                labels: xAxisCategories,
                datasets: datasets
            },
            options: chartOptions,
        });
    },

    toggleZoomResetButton: function () {
        OCA.Analytics.UI.showElement('chartZoomReset');
    },

    handleZoomResetButton: function () {
        OCA.Analytics.chartObject.resetZoom();
        OCA.Analytics.UI.hideElement('chartZoomReset');
    },

    toggleChartLegend: function () {
        OCA.Analytics.chartObject.legend.options.display = !OCA.Analytics.chartObject.legend.options.display
        OCA.Analytics.chartObject.update();
    },

    downloadChart: function () {
        OCA.Analytics.UI.hideReportMenu();
        document.getElementById('downloadChartLink').href = OCA.Analytics.chartObject.toBase64Image();
        document.getElementById('downloadChartLink').setAttribute('download', OCA.Analytics.currentReportData.options.name + '.png');
        document.getElementById('downloadChartLink').click();
    },

    resetContentArea: function () {
        if (OCA.Analytics.isAdvanced) {
            OCA.Analytics.UI.showElement('analytics-intro');
            document.getElementById('app-sidebar').classList.add('disappear');
        } else {
            if (OCA.Analytics.tableObject !== null) {
                OCA.Analytics.tableObject.destroy();
                OCA.Analytics.tableObject = null;
            }
            OCA.Analytics.UI.hideElement('chartContainer');
            OCA.Analytics.UI.hideElement('chartLegendContainer');
            document.getElementById('chartContainer').innerHTML = '';
            document.getElementById('chartContainer').innerHTML = '<button id="chartZoomReset" hidden>' + t('analytics', 'Reset zoom') + '</button><canvas id="myChart" ></canvas>';
            document.getElementById('chartZoomReset').addEventListener('click', OCA.Analytics.UI.handleZoomResetButton);
            OCA.Analytics.UI.hideElement('tableContainer');
            OCA.Analytics.UI.hideElement('tableSeparatorContainer');
            //OCA.Analytics.UI.hideElement('tableMenuBar');
            document.getElementById('tableContainer').innerHTML = '';

            OCA.Analytics.UI.hideElement('reportSubHeader');
            OCA.Analytics.UI.hideElement('noDataContainer');
            document.getElementById('reportHeader').innerHTML = '';
            document.getElementById('reportSubHeader').innerHTML = '';

            OCA.Analytics.UI.showElement('reportMenuBar');
            OCA.Analytics.UI.hideReportMenu();
            document.getElementById('chartOptionsIcon').disabled = false;
            document.getElementById('analysisIcon').disabled = false;
            document.getElementById('drilldownIcon').disabled = false;
            document.getElementById('downloadChartIcon').disabled = false;
            document.getElementById('analysisIcon').disabled = false;
        }
    },

    buildReportOptions: function () {
        let currentReport = OCA.Analytics.currentReportData;
        let canUpdate = parseInt(currentReport.options['permissions']) === OC.PERMISSION_UPDATE;
        let isInternalShare = currentReport.options['isShare'] !== undefined;
        let isExternalShare = document.getElementById('sharingToken').value !== '';

        if (isExternalShare) {
            if (canUpdate) {
                OCA.Analytics.UI.hideElement('reportMenuIcon');
                OCA.Analytics.Filter.refreshFilterVisualisation();
            } else {
                //document.getElementById('reportMenuBar').remove();
                OCA.Analytics.UI.hideElement('reportMenuBar');
                //document.getElementById('reportMenuBar').id = 'reportMenuBarHidden';
            }
            return;
        }

        if (!canUpdate) {
            OCA.Analytics.UI.hideElement('reportMenuBar');
        }

        if (isInternalShare) {
            OCA.Analytics.UI.showElement('reportMenuIcon');
        }

        if (parseInt(currentReport.options.type) !== OCA.Analytics.TYPE_INTERNAL_DB) {
            document.getElementById('drilldownIcon').disabled = true;
        }

        if (currentReport.options.chart === 'doughnut') {
            document.getElementById('analysisIcon').disabled = true;
        }

        let visualization = currentReport.options.visualization;
        if (visualization === 'table') {
            document.getElementById('chartOptionsIcon').disabled = true;
            document.getElementById('analysisIcon').disabled = true;
            document.getElementById('downloadChartIcon').disabled = true;
        }

        let refresh = parseInt(currentReport.options.refresh);
        isNaN(refresh) ? refresh = 0 : refresh;
        document.getElementById('refresh' + refresh).checked = true;

        OCA.Analytics.Filter.refreshFilterVisualisation();
    },

    reportOptionsEventlisteners: function () {
        document.getElementById('addFilterIcon').addEventListener('click', OCA.Analytics.Filter.openFilterDialog);
        document.getElementById('reportMenuIcon').addEventListener('click', OCA.Analytics.UI.toggleReportMenu);
        //document.getElementById('tableMenuIcon').addEventListener('click', OCA.Analytics.UI.toggleTableMenu);
        document.getElementById('saveIcon').addEventListener('click', OCA.Analytics.Filter.Backend.saveReport);
        document.getElementById('saveIconNew').addEventListener('click', OCA.Analytics.Filter.Backend.newReport);
        document.getElementById('drilldownIcon').addEventListener('click', OCA.Analytics.Filter.openDrilldownDialog);
        document.getElementById('chartOptionsIcon').addEventListener('click', OCA.Analytics.Filter.openChartOptionsDialog);

        document.getElementById('analysisIcon').addEventListener('click', OCA.Analytics.UI.showReportMenuAnalysis);
        document.getElementById('refreshIcon').addEventListener('click', OCA.Analytics.UI.showReportMenuRefresh);
        document.getElementById('trendIcon').addEventListener('click', OCA.Analytics.Functions.trend);
        document.getElementById('disAggregateIcon').addEventListener('click', OCA.Analytics.Functions.disAggregate);
        document.getElementById('aggregateIcon').addEventListener('click', OCA.Analytics.Functions.aggregate);
        //document.getElementById('linearRegressionIcon').addEventListener('click', OCA.Analytics.Functions.linearRegression);
        document.getElementById('backIcon').addEventListener('click', OCA.Analytics.UI.showReportMenuMain);
        document.getElementById('backIcon2').addEventListener('click', OCA.Analytics.UI.showReportMenuMain);
        document.getElementById('downloadChartIcon').addEventListener('click', OCA.Analytics.UI.downloadChart);
        document.getElementById('chartLegend').addEventListener('click', OCA.Analytics.UI.toggleChartLegend);

        //document.getElementById('menuSearchBox').addEventListener('keypress', OCA.Analytics.UI.tableSearch);

        let refresh = document.getElementsByName('refresh');
        for (let i = 0; i < refresh.length; i++) {
            refresh[i].addEventListener('change', OCA.Analytics.Filter.Backend.saveRefresh);
        }
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

    hideReportMenu: function () {
        if (document.getElementById('reportMenu') !== null) {
            document.getElementById('reportMenu').classList.remove('open');
        }
    },

    toggleReportMenu: function () {
        document.getElementById('reportMenu').classList.toggle('open');
        document.getElementById('reportMenuMain').style.removeProperty('display');
        document.getElementById('reportMenuAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuRefresh').style.setProperty('display', 'none', 'important');
    },

    toggleTableMenu: function () {
        document.getElementById('tableMenu').classList.toggle('open');
        document.getElementById('tableMenuMain').style.removeProperty('display');
    },

    showReportMenuAnalysis: function () {
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuAnalysis').style.removeProperty('display');
    },

    showReportMenuRefresh: function () {
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuRefresh').style.removeProperty('display');
    },

    showReportMenuMain: function () {
        document.getElementById('reportMenuAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuRefresh').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuMain').style.removeProperty('display');
    },

    tableSearch: function () {
        OCA.Analytics.tableObject.search( this.value ).draw();
    }
};

OCA.Analytics.Functions = {

    trend: function () {
        OCA.Analytics.UI.showElement('chartLegendContainer');
        OCA.Analytics.UI.hideReportMenu();

        let numberDatasets = OCA.Analytics.chartObject.data.datasets.length;
        let datasetType = 'time';
        for (let y = 0; y < numberDatasets; y++) {
            let dataset = OCA.Analytics.chartObject.data.datasets[y];
            let newLabel = dataset.label + " " + t('analytics', 'Trend');

            // generate trend only for visible data series
            if (OCA.Analytics.chartObject.isDatasetVisible(y) === false) continue;
            // dont add trend twice
            if (OCA.Analytics.chartObject.data.datasets.find(o => o.label === newLabel) !== undefined) continue;
            // dont add trend for a trend
            if (dataset.label.substr(dataset.label.length - 5) === t('analytics', 'Trend')) continue;

            let yValues = [];
            for (let i = 0; i < dataset.data.length; i++) {
                if (typeof (dataset.data[i]) === 'number') {
                    datasetType = 'bar';
                    yValues.push(parseInt(dataset.data[i]));
                } else {
                    yValues.push(parseInt(dataset.data[i]["y"]));
                }
            }
            let xValues = [];
            for (let i = 1; i <= dataset.data.length; i++) {
                xValues.push(i);
            }

            let regression = OCA.Analytics.Functions.regressionFunction(xValues, yValues);
            let ylast = ((dataset.data.length) * regression["slope"]) + regression["intercept"];

            let data = [];
            if (datasetType === 'time') {
                data = [
                    {x: dataset.data[0]["x"], y: regression["intercept"]},
                    {x: dataset.data[dataset.data.length - 1]["x"], y: ylast},
                ]
            } else {
                for (let i = 1; i < dataset.data.length + 1; i++) {
                    data.push(((i) * regression["slope"]) + regression["intercept"]);
                }
            }
            let newDataset = {
                label: newLabel,
                backgroundColor: dataset.backgroundColor,
                borderColor: dataset.borderColor,
                borderDash: [5, 5],
                type: 'line',
                yAxisID: dataset.yAxisID,
                data: data
            };
            OCA.Analytics.chartObject.data.datasets.push(newDataset);

        }
        OCA.Analytics.chartObject.update();
    },

    regressionFunction: function (x, y) {
        const n = y.length;
        let sx = 0;
        let sy = 0;
        let sxy = 0;
        let sxx = 0;
        let syy = 0;
        for (let i = 0; i < n; i++) {
            sx += x[i];
            sy += y[i];
            sxy += x[i] * y[i];
            sxx += x[i] * x[i];
            syy += y[i] * y[i];
        }
        const mx = sx / n;
        const my = sy / n;
        const yy = n * syy - sy * sy;
        const xx = n * sxx - sx * sx;
        const xy = n * sxy - sx * sy;
        const slope = xy / xx;
        const intercept = my - slope * mx;

        return {slope, intercept};
    },

    aggregate: function () {
        OCA.Analytics.Functions.aggregationFunction('aggregate');
        // const cumulativeSum = (sum => value => sum += value)(0);
        // datasets[0]['data'] = datasets[0]['data'].map(cumulativeSum)
    },

    disAggregate: function () {
        OCA.Analytics.Functions.aggregationFunction('disaggregate');
    },

    aggregationFunction: function (mode) {
        OCA.Analytics.UI.showElement('chartLegendContainer');
        OCA.Analytics.UI.hideReportMenu();

        let numberDatasets = OCA.Analytics.chartObject.data.datasets.length;
        let newLabel;
        for (let y = 0; y < numberDatasets; y++) {
            let dataset = OCA.Analytics.chartObject.data.datasets[y];
            if (mode === 'aggregate') {
                newLabel = dataset.label + " " + t('analytics', 'Aggregation');
            } else {
                newLabel = dataset.label + " " + t('analytics', 'Disaggregation');
            }

            // generate trend only for visible data series
            if (OCA.Analytics.chartObject.isDatasetVisible(y) === false) continue;
            // dont add trend twice
            if (OCA.Analytics.chartObject.data.datasets.find(o => o.label === newLabel) !== undefined) continue;
            // dont add trend for a trend
            if (dataset.label.substr(dataset.label.length - 5) === "Trend") continue;
            if (dataset.label.substr(dataset.label.length - 11) === t('analytics', 'Aggregation')) continue;
            if (dataset.label.substr(dataset.label.length - 14) === t('analytics', 'Disaggregation')) continue;

            let lastValue = 0;
            let newValue;
            let newData = OCA.Analytics.chartObject.data.datasets[y]['data'].map(function (currentValue, index, arr) {
                if (mode === 'aggregate') {
                    if (typeof (currentValue) === 'number') {
                        newValue = currentValue + lastValue;
                        lastValue = newValue;
                    } else {
                        newValue = {x: currentValue["x"], y: parseInt(currentValue["y"]) + lastValue};
                        lastValue = parseInt(currentValue["y"]) + lastValue;
                    }
                } else {
                    if (typeof (currentValue) === 'number') {
                        newValue = currentValue - lastValue;
                        lastValue = currentValue;
                    } else {
                        newValue = {x: currentValue["x"], y: parseInt(currentValue["y"]) - lastValue};
                        lastValue = parseInt(currentValue["y"]);
                    }
                    return newValue;
                }
                return newValue;
            })

            let newDataset = {
                label: newLabel,
                backgroundColor: dataset.backgroundColor,
                borderColor: dataset.borderColor,
                borderDash: [5, 5],
                type: 'line',
                yAxisID: 'secondary',
                data: newData
            };
            OCA.Analytics.chartObject.data.datasets.push(newDataset);

        }
        OCA.Analytics.chartObject.update();
    },

};

OCA.Analytics.Datasource = {
    buildDropdown: function () {
        let options = document.createDocumentFragment();
        let option = document.createElement('option');
        option.value = '';
        option.innerText = t('analytics', 'Please select');
        options.appendChild(option);

        let sortedOptions = OCA.Analytics.Datasource.sortOptions(OCA.Analytics.datasources);
        sortedOptions.forEach((entry) => {
            let value = entry[1];
            option = document.createElement('option');
            option.value = entry[0];
            option.innerText = value;
            options.appendChild(option);
        });
        return options;
    },

    sortOptions: function (obj) {
        let sortable = [];
        for (let key in obj)
            if (obj.hasOwnProperty(key))
                sortable.push([key, obj[key]]);
        sortable.sort(function (a, b) {
            let x = a[1].toLowerCase(),
                y = b[1].toLowerCase();
            return x < y ? -1 : x > y ? 1 : 0;
        });
        return sortable;
    },

    buildOptionsForm: function (datasource) {
        let template = OCA.Analytics.datasourceOptions[datasource];
        let form = document.createElement('div');
        form.id = 'dataSourceOptions';

        if (typeof(template) === 'undefined') {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Data source not available anymore'));
            return form;
        }

        // create a hidden dummy for the data source type
        form.appendChild(OCA.Analytics.Datasource.buildOptionHidden('dataSourceType',datasource));

        for (let templateOption of template) {
            // loop all options of the datasource template and create the input form

            // create the label column
            let tableRow = document.createElement('div');
            tableRow.style.display = 'table-row';
            let label = document.createElement('div');
            label.style.display = 'table-cell';
            label.style.width = '100%';
            label.innerText = templateOption.name;
            // create the info icon column
            let infoColumn = document.createElement('div');
            infoColumn.style.display = 'table-cell';
            infoColumn.style.minWidth = '20px';

            //create the input fields
            let input;
            input = OCA.Analytics.Datasource.buildOptionsInput(templateOption);
            input.style.display = 'table-cell';
            if (templateOption.type) {
                if (templateOption.type === 'tf') {
                    input = OCA.Analytics.Datasource.buildOptionsSelect(templateOption);
                } else if (templateOption.type === 'filePicker') {
                    input = OCA.Analytics.Datasource.buildOptionsInput(templateOption);
                    input.addEventListener('click', OCA.Analytics.Datasource.handleFilepicker);
                } else if (templateOption.type === 'columnPicker') {
                    input = OCA.Analytics.Datasource.buildOptionsInput(templateOption);
                    //input.disabled = true;
                    input.addEventListener('click', OCA.Analytics.Datasource.handleColumnPicker);
                }
            }
            form.appendChild(tableRow);
            tableRow.appendChild(label);
            tableRow.appendChild(input);
            tableRow.appendChild(infoColumn);
        }
        return form;
    },

    buildOptionHidden: function (id, value) {
        let dataSourceType = document.createElement('input');
        dataSourceType.hidden = true;
        dataSourceType.id = id;
        dataSourceType.innerText = value;
        return dataSourceType;
    },

    buildOptionsInput: function (templateOption) {
        let input = document.createElement('input');
        input.style.display = 'inline-flex';
        input.classList.add('sidebarInput');
        input.placeholder = templateOption.placeholder;
        input.id = templateOption.id;
        input.dataset.type = templateOption.type;
        if (templateOption.type && templateOption.type === 'number') {
            input.type = 'number';
            input.min = '1';
        }
        return input;
    },

    buildOptionsCheckboxIndicator: function (templateOption) {
        let input = document.createElement('input');
        input.type = 'checkbox'
        input.disabled = true;
        input.style.display = 'inline-flex';
        //input.classList.add('sidebarInput');
        input.id = templateOption.id;
        input.dataset.type = templateOption.type;

        let edit = document.createElement('span');
        edit.style.display = 'inline-flex';
        edit.classList.add('icon','icon-rename');
        edit.style.minHeight = '36px';

        let div = document.createElement('div');
        div.style.display = 'table-cell';
        div.appendChild(input);
        div.appendChild(edit);
        return div;
    },

    buildOptionsSelect: function (templateOption) {
        let input = document.createElement('select');
        let text, value;
        input.style.display = 'inline-flex';
        input.classList.add('sidebarInput');
        input.id = templateOption.id;
        input.dataset.type = templateOption.type;

        // if options are split with "-", they are considered as value/key pairs
        let selectOptions = templateOption.placeholder.split("/")
        for (let selectOption of selectOptions) {
            let keyValue = selectOption.split('-');
            value = selectOption;
            text = selectOption;
            if (keyValue.length >> 1) {
                value = keyValue[0];
                text = keyValue[1];
            }
            let option = document.createElement('option');
            option.value = value;
            option.innerText = text;
            input.appendChild(option);
        }
        return input;
    },

    handleColumnPicker: function () {
        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Column Picker'),
            OCA.Analytics.Datasource.processColumnPickerDialog
        );

        // Get the values from all input fields but not the cloumn picker
        // they are used to get the data from the data source
        let option = {};
        let inputFields = document.querySelectorAll('#dataSourceOptions input, #dataSourceOptions select');
        for (let inputField of inputFields) {
            if (inputField.dataset.type !== 'columnPicker') option[inputField.id] = inputField.value;
        }

        let requestUrl = OC.generateUrl('apps/analytics/data');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                type: parseInt(document.getElementById('dataSourceType').innerText),
                options: JSON.stringify(option),
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                OCA.Analytics.Datasource.createColumnPickerContent(data);
            })
            .catch(error => {
                // stop if the file link is missing
                OCA.Analytics.Notification.notification('error', t('analytics', 'Parameter missing'));
                OCA.Analytics.Notification.dialogClose();
            });
    },

    createColumnPickerContent: function (data) {
        // Array of items
        const items = data.data[0].map((value, index) => {
            return {
                id: index + 1,
                name: data.header[index],
                text: value,
                checked: true
            };
        });

        let selectionArray = document.querySelector('[data-type="columnPicker"]').value.split(',').map(str => parseInt(str));

        // sort the items and put selected ones in front
        items.sort((a, b) => {
            const indexA = selectionArray.indexOf(a.id);
            const indexB = selectionArray.indexOf(b.id);
            if (indexA < 0) return indexB >= 0 ? 1 : 0;
            if (indexB < 0) return -1;
            return indexA - indexB;
        });

        // selected ones should get the checkbox true
        items.forEach((item) => {
            item.checked = selectionArray.includes(item.id);
        });

        const list = document.createElement("ul");
        list.id = 'sortable-list';
        list.style.display = 'inline-block';
        list.style.listStyle = 'none';
        list.style.margin = '0';
        list.style.padding = '0';
        list.style.width = "400px"
        items.forEach((item) => {
            const li = document.createElement("li");
            li.style.display = 'flex';
            li.style.alignItems = 'center';
            li.style.margin = '5px';
            li.style.backgroundColor = 'var(--color-background-hover)';
            li.draggable = true;
            li.addEventListener("dragstart", OCA.Analytics.Notification.handleDragStart);
            li.addEventListener("dragover", OCA.Analytics.Notification.handleDragOver);
            li.addEventListener("drop", OCA.Analytics.Notification.handleDrop);

            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.id = item.id;
            checkbox.checked = item.checked;

            const span = document.createElement("span");
            span.textContent = item.name;
            span.style.marginLeft = '10px';
            const spanContent = document.createElement("span");
            spanContent.textContent = item.text;
            spanContent.style.marginLeft = '10px';
            spanContent.style.color = 'var(--color-text-maxcontrast)';
            li.appendChild(checkbox);
            li.appendChild(span);
            li.appendChild(spanContent);
            list.appendChild(li);
        });

        OCA.Analytics.Notification.htmlDialogUpdate(
            list,
            'Select the required columns.<br>Rearange the sequence with drag & drop.<br>Remove all selections to reset.',
        );
    },

    processColumnPickerDialog: function () {
        //get the list and sequence of the selected items
        const checkboxList = document.querySelectorAll('#sortable-list input[type="checkbox"]');
        const checkboxIds = [];

        checkboxList.forEach(function (checkbox) {
            if (checkbox.checked) {
                checkboxIds.push(checkbox.id);
            }
        });
        document.querySelector('[data-type="columnPicker"]').value = checkboxIds;
        OCA.Analytics.Notification.dialogClose();
    },

    handleFilepicker: function () {
        let type = parseInt(document.getElementById('dataSourceType').innerText);

        let mime;
        if (type === OCA.Analytics.TYPE_INTERNAL_FILE) {
            mime = ['text/csv', 'text/plain'];
        } else if (type === OCA.Analytics.TYPE_EXCEL) {
            mime = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.ms-excel'];
        }
        OC.dialogs.filepicker(
            t('analytics', 'Select file'),
            function (path) {
                document.querySelector('[data-type="filePicker"]').value = path;
            },
            false,
            mime,
            true,
            1);
    },
};

OCA.Analytics.Backend = {

    formatParams: function (params) {
        return "?" + Object
            .keys(params)
            .map(function (key) {
                return key + "=" + encodeURIComponent(params[key])
            })
            .join("&")
    },

    getData: function () {
        if (OCA.Analytics.currentXhrRequest) OCA.Analytics.currentXhrRequest.abort();
        OCA.Analytics.UI.resetContentArea();
        OCA.Analytics.UI.hideElement('analytics-intro');
        OCA.Analytics.UI.hideElement('analytics-content');
        OCA.Analytics.UI.showElement('analytics-loading');

        let url;
        if (document.getElementById('sharingToken').value === '') {
            const reportId = document.querySelector('#navigationDatasets .active').firstElementChild.dataset.id;
            url = OC.generateUrl('apps/analytics/data/') + reportId;
        } else {
            const token = document.getElementById('sharingToken').value;
            url = OC.generateUrl('apps/analytics/data/public/') + token;
        }

        // send user current filter options to the data request;
        // if nothing is changed by the user, the filter which is stored for the report, will be used
        let ajaxData = {};
        if (typeof (OCA.Analytics.currentReportData.options) !== 'undefined') {
            if (typeof (OCA.Analytics.currentReportData.options.filteroptions) !== 'undefined') {
                ajaxData.filteroptions = JSON.stringify(OCA.Analytics.currentReportData.options.filteroptions);
            }
            if (typeof (OCA.Analytics.currentReportData.options.dataoptions) !== 'undefined') {
                ajaxData.dataoptions = OCA.Analytics.currentReportData.options.dataoptions;
            }
            if (typeof (OCA.Analytics.currentReportData.options.chartoptions) !== 'undefined') {
                ajaxData.chartoptions = OCA.Analytics.currentReportData.options.chartoptions;
            }
        }

        // using xmlhttprequest in this place as long running requests need to be aborted
        // abort() is not possible for fetch()
        let xhr = new XMLHttpRequest();
        let params = new URLSearchParams(ajaxData);
        let requestUrl = `${url}?${params}`;

        xhr.open('GET', requestUrl, true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                let data = JSON.parse(xhr.responseText);
                // Do something with the data here

                OCA.Analytics.UI.hideElement('analytics-loading');
                OCA.Analytics.UI.showElement('analytics-content');
                OCA.Analytics.currentReportData = data;
                try {
                    OCA.Analytics.currentReportData.options.filteroptions = JSON.parse(OCA.Analytics.currentReportData.options.filteroptions);
                } catch (e) {
                    OCA.Analytics.currentReportData.options.filteroptions = {};
                }
                if (OCA.Analytics.currentReportData.options.filteroptions === null) {
                    OCA.Analytics.currentReportData.options.filteroptions = {};
                }

                document.getElementById('reportHeader').innerText = data.options.name;
                if (data.options.subheader !== '') {
                    document.getElementById('reportSubHeader').innerText = data.options.subheader;
                    OCA.Analytics.UI.showElement('reportSubHeader');
                }

                document.title = data.options.name + ' - ' + OCA.Analytics.initialDocumentTitle;
                if (data.status !== 'nodata' && parseInt(data.error) === 0) {

                    // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
                    data.data = OCA.Analytics.Backend.sortDates(data.data);

                    data.data = OCA.Analytics.Backend.formatDates(data.data);
                    let visualization = data.options.visualization;
                    if (visualization === 'chart') {
                        OCA.Analytics.UI.buildChart(data);
                    } else if (visualization === 'table') {
                        OCA.Analytics.UI.buildDataTable(data);
                    } else {
                        OCA.Analytics.UI.buildChart(data);
                        OCA.Analytics.UI.buildDataTable(data);
                    }
                } else {
                    OCA.Analytics.UI.showElement('noDataContainer');
                    if (parseInt(data.error) !== 0) {
                        OCA.Analytics.Notification.notification('error', data.error);
                    }
                }
                OCA.Analytics.UI.buildReportOptions();

                let refresh = parseInt(OCA.Analytics.currentReportData.options.refresh);
                OCA.Analytics.Backend.startRefreshTimer(refresh);
            }
        };
        xhr.send();
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
        let firstRow = data[0];
        let now;
        for (let i = 0; i < firstRow.length; i++) {
            // loop columns and check for a valid date
            if (!isNaN(new Date(firstRow[i]).valueOf()) && firstRow[i] !== null && firstRow[i].length >= 19) {
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

    getDatasourceDefinitions: function () {
        let requestUrl = OC.generateUrl('apps/analytics/datasource');
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.datasourceOptions = data['options'];
                OCA.Analytics.datasources = data['datasources'];
            });
    },

    getDatasetDefinitions: function () {
        let requestUrl = OC.generateUrl('apps/analytics/dataset');
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.datasets = data;
            });
    },

    startRefreshTimer(minutes) {
        if (minutes !== 0 && !isNaN(minutes)) {
            if (OCA.Analytics.refreshTimer === null) {
                OCA.Analytics.refreshTimer = setTimeout(OCA.Analytics.Backend.getData, minutes * 60 * 1000)
            } else {
                clearTimeout(OCA.Analytics.refreshTimer)
                OCA.Analytics.refreshTimer = null
                OCA.Analytics.Backend.startRefreshTimer(minutes);
            }
        } else {
            if (OCA.Analytics.refreshTimer !== null) {
                clearTimeout(OCA.Analytics.refreshTimer)
                OCA.Analytics.refreshTimer = null
            }
        }
    },
};

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('advanced').value === 'true') {
        OCA.Analytics.isAdvanced = true;
    }
    OCA.Analytics.initialDocumentTitle = document.title;
    OCA.Analytics.UI.hideElement('analytics-warning');

    if (document.getElementById('sharingToken').value === '') {
        OCA.Analytics.UI.showElement('analytics-intro');
        OCA.Analytics.Core.initApplication();
    } else {
        OCA.Analytics.Backend.getData();
    }
    if (!OCA.Analytics.isAdvanced) {
        OCA.Analytics.UI.reportOptionsEventlisteners();
        document.getElementById("infoBoxReport").addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
        document.getElementById("infoBoxIntro").addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
        document.getElementById("infoBoxWiki").addEventListener('click', OCA.Analytics.Core.openWiki);
    }

    window.addEventListener("beforeprint", function () {
        //document.getElementById('chartContainer').style.height = document.getElementById('myChart').style.height;
    });
});