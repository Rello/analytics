/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */
/** global: OCA */
/** global: OC */
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
        let urlHash = decodeURI(location.hash);
        if (urlHash.length > 1) {
            if (urlHash.substring(2, 3) === 'f') {
                window.location.href = '#';
                OCA.Analytics.Backend.createDataset(urlHash.substring(3));
            } else if (urlHash.substring(2, 3) === 'r') {
                OCA.Analytics.Core.initNavigation(urlHash.substring(4));
            }
        } else {
            OCA.Analytics.Core.initNavigation();
        }
    },

    initNavigation: function (datasetId) {
        document.getElementById('navigationDatasets').innerHTML = '';
        OCA.Analytics.Backend.getDatasets(datasetId);
    },

    handleDrilldownChange: function () {
        OCA.Analytics.UI.resetContent();
        OCA.Analytics.Backend.getData();
    },

    handleNewDatasetButton: function () {
        OCA.Analytics.Backend.createDataset();
    },

    createDemoReport: function () {
        OCA.Analytics.Backend.createDataset('DEMO');
    }
};

OCA.Analytics.UI = {

    buildNavigation: function (data) {
        for (let navigation of data) {
            OCA.Analytics.UI.buildNavigationRow(navigation);
        }
    },

    fillSidebarParentDropdown: function (data) {
        let tableParent = document.querySelector('#templateDataset #sidebarDatasetParent');
        tableParent.innerHTML = "";
        let option = document.createElement('option');
        option.text = '';
        option.value = 0;
        tableParent.add(option);

        for (let dataset of data) {
            if (parseInt(dataset.type) === OCA.Analytics.TYPE_EMPTY_GROUP) {
                option = document.createElement('option');
                option.text = dataset.name;
                option.value = dataset.id;
                tableParent.add(option);
            }
        }
    },

    buildNavigationRow: function (data) {
        let li = document.createElement('li');
        let typeIcon;

        let a = document.createElement('a');
        a.setAttribute('href', '#/r/' + data.id);
        data.type = parseInt(data.type);
        if (data.type === OCA.Analytics.TYPE_INTERNAL_FILE) {
            typeIcon = 'icon-file';
        } else if (data.type === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (data.type === OCA.Analytics.TYPE_GIT || data.type === OCA.Analytics.TYPE_EXTERNAL_FILE) {
            typeIcon = 'icon-external';
        } else if (data.type === OCA.Analytics.TYPE_SHARED) {
            typeIcon = 'icon-shared';
        } else if (data.type === OCA.Analytics.TYPE_EMPTY_GROUP) {
            typeIcon = 'icon-folder';
            li.classList.add('collapsible');
        } else {
            typeIcon = '';
        }

        if (typeIcon) {
            a.classList.add(typeIcon);
        }
        a.innerText = data.name;
        a.dataset.id = data.id;
        a.dataset.type = data.type;
        a.dataset.name = data.name;

        let div = document.createElement('div');
        div.classList.add('app-navigation-entry-utils');
        let ul = document.createElement('ul');
        let li2 = document.createElement('li');
        li2.classList.add('app-navigation-entry-utils-menu-button');
        let button = document.createElement('button');
        button.addEventListener('click', OCA.Analytics.UI.handleOptionsClicked);
        button.dataset.id = data.id;
        button.dataset.name = data.name;
        button.dataset.type = data.type;

        let ulSublist = document.createElement('ul');
        ulSublist.id = 'dataset-' + data.id;

        li2.appendChild(button);
        if (data.type !== OCA.Analytics.TYPE_SHARED) {
            ul.appendChild(li2);
        }
        div.appendChild(ul);
        li.appendChild(a);
        li.appendChild(div);

        if (data.type === OCA.Analytics.TYPE_EMPTY_GROUP) {
            li.appendChild(ulSublist);
            a.addEventListener('click', OCA.Analytics.UI.handleGroupClicked);
        } else {
            a.addEventListener('click', OCA.Analytics.UI.handleNavigationClicked);
        }

        let categoryList;
        if (parseInt(data.parent) !== 0) {
            categoryList = document.getElementById('dataset-' + data.parent);
        } else {
            categoryList = document.getElementById('navigationDatasets');
        }
        categoryList.appendChild(li);
    },

    handleNavigationClicked: function (evt) {

        OCA.Analytics.UI.resetContent();
        OCA.Analytics.Sidebar.hideSidebar();
        let activeCategory = document.querySelector('#navigationDatasets .active');
        if (evt) {
            if (activeCategory) {
                activeCategory.classList.remove('active');
            }
            evt.target.classList.add('active');
            OCA.Analytics.Backend.getData();
        }
    },

    handleOptionsClicked: function (evt) {
        OCA.Analytics.Sidebar.showSidebar(evt);
        evt.stopPropagation();
    },

    handleGroupClicked: function (evt) {
        if (evt.target.parentNode.classList.contains('open')) {
            evt.target.parentNode.classList.remove('open');
        } else {
            evt.target.parentNode.classList.add('open');
        }
        evt.stopPropagation();
    },

    buildDataTable: function (jsondata) {
        document.getElementById('tableContainer').style.removeProperty('display');
        let i = 0;
        let columns = [];
        let data;

        if (parseInt(jsondata.options.type) === 888) { // obsolete CSV logic
            data = $.csv.toObjects(jsondata.data);
            let csvHeader = jsondata.data.split('\n')[0];
            let singleHeader = csvHeader.split(',');
            while (i < singleHeader.length) {
                let text = singleHeader[i].replace(/[^a-zA-Z0-9 &%.]/g, '').trim();
                columns[i] = {'title': text, 'data': text};
                i += 1;
            }
        } else {
            document.getElementById('drilldown').style.removeProperty('display');
            let columnKeys = Object.keys(jsondata.data[0]);
            let header = jsondata.header;
            while (i < columnKeys.length) {
                columns[i] = {'data': columnKeys[i], 'title': header[columnKeys[i]]};
                if (header[columnKeys[i]].length === 1) {
                    columns[i]['render'] = $.fn.dataTable.render.number('.', ',', 2, header[columnKeys[i]] + ' ');
                }
                i += 1;
            }
            data = jsondata.data;
        }

        let language = {
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
                OCA.Analytics.UI.rowCallback(row, data, index, jsondata.thresholds)
            },
        });
    },

    rowCallback: function (row, data, index, thresholds) {

        var operators = {
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
                return a != b
            },
        };

        thresholds = thresholds.filter(p => p.dimension1 === data['dimension1'] || p.dimension1 === '*');

        for (let threshold of thresholds) {
            let comparison = operators[threshold['option']](data['dimension3'], threshold['dimension3']);
            if (comparison === true) {
                if (threshold['severity'] === '2') {
                    $(row).find('td:eq(2)').css('color', 'red');
                } else if (threshold['severity'] === '3') {
                    $(row).find('td:eq(2)').css('color', 'orange');
                } else if (threshold['severity'] === '4') {
                    $(row).find('td:eq(2)').css('color', 'green');
                }
            }
        }
    },

    buildHighchart: function (jsondata) {

        document.getElementById('chartContainer').style.removeProperty('display');
        let chartType = jsondata.options.chart;

        let seriesOptions = [];
        let xAxisOptions = [];
        let xAxisCategories = [];
        let xplotOptions = [];

        document.getElementById('drilldown').style.removeProperty('display');
        let lastObject = 0;
        let index = 0;

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
                //seriesOptions[index]['data'].push([Date.UTC(parseInt(values['dimension2']), 1, 1), parseFloat(values['dimension3'])]);
                seriesOptions[index]['data'].push([new Date(values['dimension2']).getTime(), parseFloat(values['dimension3'])]);
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
        let objectDrilldown = document.getElementById('checkBoxObject').checked;
        let dateDrilldown = document.getElementById('checkBoxDate').checked;
        let url;

        if (document.getElementById('sharingToken').value === '') {
            let datasetId = document.querySelector('#navigationDatasets .active').dataset.id;
            url = OC.generateUrl('apps/analytics/data/') + datasetId;
        } else {
            let token = document.getElementById('sharingToken').value;
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
            type: "GET",
            url: OC.generateUrl('apps/analytics/dataset'),
            success: function (data) {
                OCA.Analytics.UI.buildNavigation(data);
                OCA.Analytics.UI.fillSidebarParentDropdown(data);
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
                OCA.Analytics.Core.initNavigation(data);
            }
        });
    },

};

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.initialDocumentTitle = document.title;
    document.getElementById('analytics-warning').classList.add('hidden')
    if (document.getElementById('sharingToken').value === '') {
        document.getElementById('analytics-intro').attributes.removeNamedItem('hidden');
        OCA.Analytics.Core.initApplication();
        document.getElementById('newDatasetButton').addEventListener('click', OCA.Analytics.Core.handleNewDatasetButton);
        document.getElementById('createDemoReport').addEventListener('click', OCA.Analytics.Core.createDemoReport);
    } else {
        OCA.Analytics.Backend.getData();
    }

    document.getElementById('checkBoxObject').addEventListener('click', OCA.Analytics.Core.handleDrilldownChange);
    document.getElementById('checkBoxDate').addEventListener('click', OCA.Analytics.Core.handleDrilldownChange);

    $('#myInput').on('keyup', function () {
        table.search(this.value).draw();
    });
});
