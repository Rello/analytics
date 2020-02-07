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
        let columns = [];
        let data;

        document.getElementById('drilldown').style.removeProperty('display');
        let columnKeys = Object.keys(jsondata.data[0]);
        let header = jsondata.header;
        for (let i = 0; i < columnKeys.length; i++) {
            columns[i] = {'data': columnKeys[i], 'title': header[columnKeys[i]]};
            if (header[columnKeys[i]].length === 1) {
                columns[i]['render'] = $.fn.dataTable.render.number('.', ',', 2, header[columnKeys[i]] + ' ');
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

    buildHighchart: function (jsondata) {

        document.getElementById('chartContainer').style.removeProperty('display');
        let chartType = jsondata.options.chart;

        let seriesOptions = [], xAxisOptions = [], xAxisCategories = [], xplotOptions = [];

        document.getElementById('drilldown').style.removeProperty('display');
        let lastObject = 0;
        let index = 0;
        let timestamp;

        for (let values of jsondata.data) {

            if (lastObject !== values['dimension1']) {
                seriesOptions.push({data: [], name: values['dimension1']});
                if (lastObject !== 0) {
                    index++;
                }
                lastObject = values['dimension1'];
            }
            // create array per dimension 1 with subarrays per dimension 2 & dimension 3 (value)
            // [0] => name: 'dimension 1', data : [ [0] => dimension 2, dimension 3],
            // [0] => name: 'dimension 1', data : [ [1] => dimension 2, dimension 3]
            if (chartType === 'datetime') {
                // tweak for Safari; other browsers are OK
                timestamp = values['dimension2'];
                if (isNaN(new Date(timestamp))) {
                    timestamp = timestamp.replace(/ /g, "T");
                }
                seriesOptions[index]['data'].push([new Date(timestamp).getTime(), parseFloat(values['dimension3'])]);
            } else {
                seriesOptions[index]['data'].push([values['dimension2'], parseFloat(values['dimension3'])]);
                // create array all dimension 2 for the x-axis
                xAxisCategories.push(values['dimension2']);
            }
        }


        if (chartType === 'datetime') {
            xplotOptions = {area: {stacking: 'undefined'}, series: {marker: {enabled: false},},};
            xAxisOptions = {
                type: 'datetime',
                dateTimeLabelFormats: {
                    day: '%Y'
                },
            };
            chartType = 'line';
        } else if (chartType === 'area') {
            xplotOptions = {area: {stacking: 'normal'}, series: {marker: {enabled: false},},};
            xAxisOptions = {
                categories: xAxisCategories,
            };
        } else {
            xplotOptions = {area: {stacking: 'undefined'}, series: {marker: {enabled: false},},};
            xAxisOptions = {
                categories: xAxisCategories,
            };
        }
        if (parseInt(jsondata.options.type) === OCA.Analytics.TYPE_GIT) {
            seriesOptions[0]['showInLegend'] = false;
        }

        //$('#chartContainer').highcharts()
        Highcharts.chart('chartContainer', {
            series: seriesOptions,
            chart: {
                zoomType: 'x',
                type: chartType,
                events: {
                    load: function () {
                        // if there are more than 4 series, just select only one for visibility reasons
                        let theSeries = this.series;
                        if (theSeries.length > 4) {
                            $.each(theSeries, function () {
                                if (this.index > 0) {
                                    this.setVisible(false);
                                }
                            });
                        }
                    }
                },
            },
            yAxis: {
                allowDecimals: false,
                title: {
                    text: null
                },
            },
            legend: {
                layout: 'vertical',
                align: 'right',
                verticalAlign: 'middle'
            },
            title: {
                text: ''
            },
            xAxis: xAxisOptions,
            plotOptions: xplotOptions,
            //data: dataOptions

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
            document.getElementById('chartContainer').style.display = 'none';
            document.getElementById('chartContainer').innerHTML = '';
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

    getData: function (exportData = null) {
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
                        OCA.Analytics.UI.buildHighchart(data);
                    } else if (visualization === 'table') {
                        OCA.Analytics.UI.buildDataTable(data);
                    } else {
                        OCA.Analytics.UI.buildHighchart(data);
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
