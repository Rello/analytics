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

if (!OCA.Data) {
    /**
     * @namespace
     */
    OCA.Data = {
        TYPE_EMPTY_GROUP: 0,
        TYPE_INTERNAL_FILE: 1,
        TYPE_INTERNAL_DB: 2,
        TYPE_GIT: 3,
        TYPE_SHARED: 'S',
        SHARE_USER: 0,
        SHARE_TYPE_LINK: 3
    };
}
/**
 * @namespace OCA.Data.Core
 */
OCA.Data.Core = {
    initNavigation: function () {
        document.getElementById('navigationDatasets').innerHTML = '';
        OCA.Data.Backend.getDatasets();
    },

    handleDrilldownChange: function () {
        OCA.Data.UI.resetContent();
        OCA.Data.Backend.getData();
    },

    handleNewDatasetButton: function () {
        OCA.Data.Backend.createDataset();
    }

};

OCA.Data.UI = {
    buildNavigation: function (data) {
        for (var navigation of data) {
            OCA.Data.UI.buildNavigationRow(navigation);
        }
    },

    fillSidebarParentDropdown: function (data) {
        document.getElementById('tableParent').innerHTML = "";
        var option = document.createElement('option');
        option.text = '';
        option.value = 0;
        document.getElementById('tableParent').add(option);

        for (var dataset of data) {
            if (dataset.type === OCA.Data.TYPE_EMPTY_GROUP) {
                option = document.createElement('option');
                option.text = dataset.name;
                option.value = dataset.id;
                document.getElementById('tableParent').add(option);
            }
        }
    },

    buildNavigationRow: function (data) {
        var li = document.createElement('li');
        var typeIcon;

        var a = document.createElement('a');
        a.setAttribute('href', '#');
        if (data.type === OCA.Data.TYPE_INTERNAL_FILE) {
            typeIcon = 'icon-file';
        } else if (data.type === OCA.Data.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (data.type === OCA.Data.TYPE_GIT) {
            typeIcon = 'icon-external';
        } else if (data.type === OCA.Data.TYPE_SHARED) {
            typeIcon = 'icon-shared';
        } else if (data.type === OCA.Data.TYPE_EMPTY_GROUP) {
            typeIcon = 'icon-folder';
            li.classList.add('collapsible');
        } else {
            typeIcon = '';
        }

        if (typeIcon) a.classList.add(typeIcon);
        a.innerText = data.name;
        a.dataset.id = data.id;
        a.dataset.type = data.type;
        a.dataset.name = data.name;

        var div = document.createElement('div');
        div.classList.add('app-navigation-entry-utils');
        var ul = document.createElement('ul');
        var li2 = document.createElement('li');
        li2.classList.add('app-navigation-entry-utils-menu-button');
        var button = document.createElement('button');
        button.addEventListener('click', OCA.Data.UI.handleOptionsClicked);
        button.dataset.id = data.id;
        button.dataset.name = data.name;
        button.dataset.type = data.type;

        var ulSublist = document.createElement('ul');
        ulSublist.id = 'dataset-' + data.id;

        li2.appendChild(button);
        if (data.type !== OCA.Data.TYPE_SHARED) {
            ul.appendChild(li2);
        }
        div.appendChild(ul);
        li.appendChild(a);
        li.appendChild(div);

        if (data.type === OCA.Data.TYPE_EMPTY_GROUP) {
            li.appendChild(ulSublist);
            a.addEventListener('click', OCA.Data.UI.handleGroupClicked);
        } else {
            a.addEventListener('click', OCA.Data.UI.handleNavigationClicked);
        }

        var categoryList;
        if (data.parent !== 0) {
            categoryList = document.getElementById('dataset-' + data.parent);
        } else {
            categoryList = document.getElementById('navigationDatasets');
        }
        categoryList.appendChild(li);
    },

    handleNavigationClicked: function (evt) {

        OCA.Data.UI.resetContent();
        OCA.Data.Sidebar.hideSidebar();
        var activeCategory = document.querySelector('#navigationDatasets .active');
        if (evt) {
            if (activeCategory) {
                activeCategory.classList.remove('active');
            }
            evt.target.classList.add('active');
            OCA.Data.Backend.getData();
        }
    },

    handleOptionsClicked: function (evt) {
        OCA.Data.Sidebar.showSidebar(evt);
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

    buildDataTable: function (jsondata, csv) {
        document.getElementById('tableContainer').style.removeProperty('display');
        var i = 0;
        var columns = [];

        if (csv === true) {
            var data = $.csv.toObjects(jsondata.data);
            var csvHeader = jsondata.data.split('\n')[0];
            var singleHeader = csvHeader.split(',');
            while (i < singleHeader.length) {
                var text = singleHeader[i].replace(/[^a-zA-Z0-9 &%.]/g, '').trim();
                columns[i] = {'title': text, 'data': text};
                i += 1;
            }
        } else {
            document.getElementById('drilldown').style.removeProperty('display');
            var columnKeys = Object.keys(jsondata.data[0]);
            var header = jsondata.header;
            while (i < columnKeys.length) {
                columns[i] = {'data': columnKeys[i], 'title': header[columnKeys[i]]};
                i += 1;
            }
            data = jsondata.data;
        }

        var language = {
            search: "Suche:",
            paginate: {
                first: "erster",
                previous: "zurück",
                next: "weiter",
                last: "letzter"
            },
        };

        $('#tableContainer').DataTable({
            data: data,
            columns: columns,
            language: language
        });
    },

    buildHighchart: function (jsondata, csv) {

        document.getElementById('chartContainer').style.removeProperty('display');
        var chartType = jsondata.options.chart;

        var dataOptions = [];
        var seriesOptions = [];
        var xAxisOptions = [];
        var xAxisCategories = [];

        if (csv === true) {
            dataOptions = {csv: jsondata.data};
        } else {
            document.getElementById('drilldown').style.removeProperty('display');
            var lastObject = 0;
            var index = 0;

            for (var values of jsondata.data) {

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
                seriesOptions[index]['data'].push([values['dimension2'], parseFloat(values['dimension3'])]);

                // create array all dimension 2 for the x-axis
                xAxisCategories.push(values['dimension2']);
            }
            xAxisOptions = {categories: xAxisCategories};
        }

        Highcharts.chart('chartContainer', {
            series: seriesOptions,
            chart: {
                type: chartType,
                events: {
                    load: function () {
                        // if there are more than 4 series, just select only one for visibility reasons
                        var theSeries = this.series;
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
            title: {
                text: jsondata.options.name
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
            //xAxis: xAxisOptions,
            data: dataOptions
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
        document.getElementById('drilldown').style.display = 'none';
    },
};

OCA.Data.Backend = {

    getData: function () {
        var objectDrilldown = document.getElementById('checkBoxObject').checked;
        var dateDrilldown = document.getElementById('checkBoxDate').checked;
        var url;

        if (document.getElementById('sharingToken').value === '') {
            var datasetId = document.querySelector('#navigationDatasets .active').dataset.id;
            url = OC.generateUrl('apps/analytics/data/') + datasetId;
        } else {
            var token = document.getElementById('sharingToken').value;
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
                var visualization = data.options.visualization;
                var csv;
                csv = data.options.type === OCA.Data.TYPE_INTERNAL_FILE;
                if (visualization === 'chart') {
                    OCA.Data.UI.buildHighchart(data, csv);
                } else if (visualization === 'table') {
                    OCA.Data.UI.buildDataTable(data, csv);
                }
                else {
                    OCA.Data.UI.buildHighchart(data, csv);
                    OCA.Data.UI.buildDataTable(data, csv);
                }
            }
        });
    },

    getDatasets: function () {
        $.ajax({
            type: "GET",
            url: OC.generateUrl('apps/analytics/dataset'),
            success: function (data) {
                OCA.Data.UI.buildNavigation(data);
                OCA.Data.UI.fillSidebarParentDropdown(data);
            }
        });
    },

    createDataset: function () {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataset'),
            success: function () {
                OCA.Data.Core.initNavigation();
            }
        });
    },

};

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('sharingToken').value === '') {
        OCA.Data.Core.initNavigation();
        document.getElementById('newDatasetButton').addEventListener('click', OCA.Data.Core.handleNewDatasetButton);
    } else {
        OCA.Data.Backend.getData();
    }

    document.getElementById('checkBoxObject').addEventListener('click', OCA.Data.Core.handleDrilldownChange);
    document.getElementById('checkBoxDate').addEventListener('click', OCA.Data.Core.handleDrilldownChange);

    $('#myInput').on('keyup', function () {
        table.search(this.value).draw();
    });
});
