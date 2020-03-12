/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */
/** global: OCA */
/** global: OCP */
/** global: OC */
/** global: table */
/** global: Chart */
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
        TYPE_SHARED: 99,
        SHARE_TYPE_USER: 0,
        SHARE_TYPE_LINK: 3,
        initialDocumentTitle: null,
    };
}
/**
 * @namespace OCA.Analytics.Core
 */
OCA.Analytics.Core = {
    initApplication: function () {
        const urlHash = decodeURI(location.hash);
        if (urlHash.length > 1) {
            if (urlHash[2] === 'f') {
                window.location.href = '#';
                OCA.Analytics.Backend.createDataset(urlHash.substring(3));
            } else if (urlHash[2] === 'r') {
                OCA.Analytics.Navigation.init(urlHash.substring(4));
            }
        } else {
            OCA.Analytics.Navigation.init();
        }
    },

    handleDrilldownChange: function () {
        OCA.Analytics.UI.resetContent();
        OCA.Analytics.Backend.getData();
    },
};

OCA.Analytics.UI = {

    buildDataTable: function (jsondata) {
        document.getElementById('tableContainer').style.removeProperty('display');
        document.getElementById('drilldown').style.removeProperty('display');

        let columns = [];
        let data;

        let header = jsondata.header;
        let headerKeys = Object.keys(header);
        for (let i = 0; i < headerKeys.length; i++) {
            columns[i] = {'title': header[headerKeys[i]]};
            if (header[headerKeys[i]].length === 1) {
                columns[i]['render'] = $.fn.dataTable.render.number('.', ',', 2, header[headerKeys[i]] + ' ');
            }
        }
        data = jsondata.data;

        const language = {
            search: t('analytics', 'Search'),
            lengthMenu: t('analytics', 'Show _MENU_ entries'),
            info: t('analytics', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            infoEmpty: t('analytics', 'Showing 0 to 0 of 0 entries'),
            paginate: {
                first: t('analytics', 'first'),
                previous: t('analytics', 'previous'),
                next: t('analytics', 'next'),
                last: t('analytics', 'last')
            },
        };

        $('#tableContainer').DataTable({
            data: data,
            columns: columns,
            language: language,
            rowCallback: function (row, data, index) {
                OCA.Analytics.UI.dataTableRowCallback(row, data, index, jsondata.thresholds)
            },
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

        thresholds = thresholds.filter(p => p.dimension1 === data['dimension1'] || p.dimension1 === '*');

        for (let threshold of thresholds) {
            const comparison = operators[threshold['option']](parseFloat(data['dimension3']), parseFloat(threshold['dimension3']));
            threshold['severity'] = parseInt(threshold['severity']);
            if (comparison === true) {
                if (threshold['severity'] === 2) {
                    $(row).find('td:eq(2)').css('color', 'red');
                } else if (threshold['severity'] === 3) {
                    $(row).find('td:eq(2)').css('color', 'orange');
                } else if (threshold['severity'] === 4) {
                    $(row).find('td:eq(2)').css('color', 'green');
                }
            }
        }
    },

    buildChart: function (jsondata) {

        document.getElementById('chartContainer').style.removeProperty('display');
        document.getElementById('chartMenuContainer').style.removeProperty('display');
        let ctx = document.getElementById('myChart').getContext('2d');

        // flexible mapping depending on type requiered by the used chart library
        let chartTypeMapping = {'datetime': 'line', 'column': 'bar', 'area': 'line', 'line': 'line'};

        let chartType = jsondata.options.chart;
        let datasets = [], xAxisCategories = [], xAxes = [];
        let lastObject = false;
        let dataSeries = -1;
        let hidden = false, fill = false, stacked = false;

        let header = jsondata.header;
        let headerKeys = Object.keys(header).length;
        let dataSeriesColumn = headerKeys - 3; //characteristic is taken from the second last column
        let characteristicColumn = headerKeys - 2; //characteristic is taken from the second last column
        let keyFigureColumn = headerKeys - 1; //key figures is taken from the last column

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
                xAxes = [{
                    type: 'time',
                    distribution: 'linear',
                    gridLines: {
                        display: false
                    }
                }];
            } else {
                datasets[dataSeries]['data'].push(parseFloat(values[keyFigureColumn]));
                if (dataSeries === 0) {
                    // Add category lables only once and not for every data series.
                    // They have to be unique anyway
                    xAxisCategories.push(values[characteristicColumn]);
                }
                xAxes = [{
                    gridLines: {
                        display: false
                    }
                }];
            }
        }

        if (chartType === 'area') {
            fill = true;
            stacked = true;
        }

        Chart.defaults.global.elements.line.borderWidth = 2;
        Chart.defaults.global.elements.line.tension = 0.1;
        Chart.defaults.global.elements.line.fill = fill;
        Chart.defaults.global.elements.point.radius = 1;
        Chart.defaults.global.legend.display = false;
        Chart.defaults.global.legend.position = "bottom";

        let myChart = new Chart(ctx, {
            type: chartTypeMapping[chartType],
            data: {
                labels: xAxisCategories,
                datasets: datasets
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    yAxes: [{
                        ticks: {
                            callback: function (value, index, values) {
                                return value.toLocaleString();
                            },
                        },
                        stacked: stacked,
                    }],
                    xAxes: xAxes
                },
                plugins: {
                    colorschemes: {
                        scheme: 'tableau.ClassicLight10'
                    }
                }
            }
        });

        document.getElementById('chart-legend').addEventListener('click', function () {
            myChart.options.legend.display = !myChart.options.legend.display;
            myChart.update();
        });
    },

    resetContent: function () {
        if (document.getElementById('advanced').value === 'true') {
            document.getElementById('analytics-intro').classList.remove('hidden');
            document.getElementById('app-sidebar').classList.add('disappear');
        } else {
            if ($.fn.dataTable.isDataTable('#tableContainer')) {
                $('#tableContainer').DataTable().destroy();
            }
            document.getElementById('chartMenuContainer').style.display = 'none';
            document.getElementById('chartContainer').style.display = 'none';
            document.getElementById('chartContainer').innerHTML = '';
            document.getElementById('chartContainer').innerHTML = '<canvas id="myChart" ></canvas>';
            document.getElementById('tableContainer').style.display = 'none';
            document.getElementById('tableContainer').innerHTML = '';
            document.getElementById('dataHeader').innerHTML = '';
            document.getElementById('dataSubHeader').innerHTML = '';
            document.getElementById('drilldown').style.display = 'none';
        }
    },

    notification: function (type, message) {
        if (parseInt(OC.config.versionstring.substr(0, 2)) >= 17) {
            if (type === 'success') {
                OCP.Toast.success(message)
            } else if (type === 'error') {
                OCP.Toast.error(message)
            } else {
                OCP.Toast.info(message)
            }
        } else {
            OC.Notification.showTemporary(message);
        }
    }
};

OCA.Analytics.Backend = {

    getData: function () {
        document.getElementById('analytics-intro').classList.add('hidden');
        document.getElementById('analytics-content').removeAttribute('hidden');
        const objectDrilldown = document.getElementById('checkBoxObject').checked;
        const dateDrilldown = document.getElementById('checkBoxDate').checked;
        let url;

        if (document.getElementById('sharingToken').value === '') {
            const datasetId = document.querySelector('#navigationDatasets .active').dataset.id;
            url = OC.generateUrl('apps/analytics/data/') + datasetId;
        } else {
            const token = document.getElementById('sharingToken').value;
            url = OC.generateUrl('apps/analytics/data/public/') + token;
        }

        $.ajax({
            type: 'GET',
            url: url,
            data: {
                'objectDrilldown': objectDrilldown,
                'dateDrilldown': dateDrilldown
            },
            success: function (data) {
                if (data.status !== 'nodata') {
                    let visualization = data.options.visualization;
                    document.getElementById('dataHeader').innerText = data.options.name;
                    document.getElementById('dataSubHeader').innerText = data.options.subheader;
                    document.title = data.options.name + ' @ ' + OCA.Analytics.initialDocumentTitle;
                    if (visualization === 'chart') {
                        OCA.Analytics.UI.buildChart(data);
                    } else if (visualization === 'table') {
                        OCA.Analytics.UI.buildDataTable(data);
                    } else {
                        OCA.Analytics.UI.buildChart(data);
                        OCA.Analytics.UI.buildDataTable(data);
                    }
                }
            }
        });
    },

    getDatasets: function (datasetId) {
        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataset'),
            success: function (data) {
                OCA.Analytics.Navigation.buildNavigation(data);
                OCA.Analytics.Sidebar.Dataset.fillSidebarParentDropdown(data);
                if (datasetId) {
                    document.querySelector('#navigationDatasets [data-id="' + datasetId + '"]').click();
                }
            }
        });
    },

    createDataset: function (file = '') {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataset'),
            data: {
                'file': file,
            },
            success: function (data) {
                OCA.Analytics.Navigation.init(data);
            }
        });
    },
};

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.initialDocumentTitle = document.title;
    document.getElementById('analytics-warning').classList.add('hidden');
    if (document.getElementById('sharingToken').value === '') {
        document.getElementById('analytics-intro').attributes.removeNamedItem('hidden');
        OCA.Analytics.Core.initApplication();
        document.getElementById('newDatasetButton').addEventListener('click', OCA.Analytics.Navigation.handleNewDatasetButton);
        if (document.getElementById('advanced').value === 'false') {
            document.getElementById('createDemoReport').addEventListener('click', OCA.Analytics.Navigation.createDemoReport);
            document.getElementById('checkBoxObject').addEventListener('click', OCA.Analytics.Core.handleDrilldownChange);
            document.getElementById('checkBoxDate').addEventListener('click', OCA.Analytics.Core.handleDrilldownChange);
        }
    } else {
        OCA.Analytics.Backend.getData();
    }

    $('#myInput').on('keyup', function () {
        table.search(this.value).draw();
    });
});
