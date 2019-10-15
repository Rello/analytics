/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

'use strict';

if (!OCA.Data) {
    /**
     * @namespace
     */
    OCA.Data = {};
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
        document.querySelector('#navigationDatasets .active').click();
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

    buildNavigationRow: function (data) {
        var li = document.createElement('li');
        var typeIcon;

        var a = document.createElement('a');
        a.setAttribute('href', '#');
        if (data.type === 1) typeIcon = 'icon-file';
        else if (data.type === 2) typeIcon = 'icon-projects';
        else if (data.type === 3) typeIcon = 'icon-external';
        a.classList.add(typeIcon);
        a.innerText = data.name;
        a.dataset.id = data.id;
        a.dataset.type = data.type;
        a.dataset.link = data.link;
        a.dataset.visualization = data.visualization;
        a.dataset.name = data.name;
        a.dataset.chart = data.chart;
        a.addEventListener('click', OCA.Data.UI.handleNavigationClicked);

        var div = document.createElement('div');
        div.classList.add('app-navigation-entry-utils');
        var ul = document.createElement('ul');
        var li2 = document.createElement('li');
        li2.classList.add('app-navigation-entry-utils-menu-button');
        var button = document.createElement('button');
        button.addEventListener('click', OCA.Data.UI.handleOptionsClicked);
        button.dataset.id = data.id;
        button.dataset.name = data.name;

        //button.classList.add(typeIcon)

        li2.appendChild(button);
        ul.appendChild(li2);
        div.appendChild(ul);
        li.appendChild(a);
        li.appendChild(div);

        var categoryList = document.getElementById('navigationDatasets');
        categoryList.appendChild(li);
    },

    handleNavigationClicked: function (evt) {

        OCA.Data.UI.resetContent();
        var activeCategory = document.querySelector('#navigationDatasets .active');
        if (evt) {
            if (activeCategory) {
                activeCategory.classList.remove('active');
            }
            evt.target.classList.add('active');
            if (evt.target.dataset.type === "1") OCA.Data.Backend.getDataCsv();
            else if (evt.target.dataset.type === "2") OCA.Data.Backend.getData();
            else if (evt.target.dataset.type === "3") OCA.Data.Backend.gettest();
        }
    },

    handleOptionsClicked: function (evt) {
        OCA.Data.Sidebar.showSidebar(evt);
        evt.stopPropagation();
    },

    buildDataTable: function (jsondata) {
        document.getElementById('tableContainer').style.removeProperty('display');
        document.getElementById('drilldown').style.removeProperty('display');

        var data = Object.keys(jsondata.data[0]);
        var header = jsondata.header;
        var i = 0;
        var columns = [];
        while (i < data.length) {
            columns[i] = {'data': data[i], 'title': header[i]};
            i += 1;
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
            data: jsondata.data,
            columns: columns,
            language: language
        });
    },

    buildDataTableCsv: function (jsondata) {
        document.getElementById('tableContainer').style.removeProperty('display');

        var data = $.csv.toObjects(jsondata);
        var csvHeader = jsondata.split('\n')[0];
        var singleHeader = csvHeader.split(',');
        var i = 0;
        var columns = [];
        while (i < singleHeader.length) {
            var text = singleHeader[i].replace(/[^a-zA-Z0-9 &%.]/g, '').trim();
            columns[i] = {'title': text, 'data': text};
            i += 1;
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

    buildHighchartCsv: function (jsondata) {

        document.getElementById('chartContainer').style.removeProperty('display');
        var chartType = document.querySelector('#navigationDatasets .active').dataset.chart;
        Highcharts.chart('chartContainer', {
            chart: {
                type: chartType,
                events: {
                    load: function () {
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
                text: document.querySelector('#navigationDatasets .active').dataset.name
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
            data: {
                csv: jsondata,
                enablePolling: true
            }
        });
    },

    buildHighchart: function (jsondata) {

        document.getElementById('drilldown').style.removeProperty('display');
        document.getElementById('chartContainer').style.removeProperty('display');
        var chartType = document.querySelector('#navigationDatasets .active').dataset.chart;

        var objectArray = [];
        var categories = [];
        var lastObject = 0;
        var index = 0;

        for (var values of jsondata.data) {

            if (lastObject !== values['object']) {
                objectArray.push({data: [], name: values['object']});
                if (lastObject !== 0) index++;
                lastObject = values['object'];
            }
            //objectArray[index]['data'].push([Date.UTC(values['date']), parseFloat(values['value'])]);
            objectArray[index]['data'].push([values['date'], parseFloat(values['value'])]);
            categories.push(values['date']);

        }

        Highcharts.chart('chartContainer', {
            series: objectArray,
            chart: {
                type: chartType,
                events: {
                    load: function () {
                        var theSeries = this.series;
                        if (theSeries.length > 2) {
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
                text: document.querySelector('#navigationDatasets .active').dataset.name
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
            xAxis: {
                categories: categories,
                //type: 'datetime',
                //dateTimeLabelFormats: { // don't display the dummy year
                //    month: '%Y'
                //},
            }
        });
    },

    resetContent: function () {

        if ($.fn.dataTable.isDataTable('#tableContainer')) {
            $('#tableContainer').DataTable().destroy();
        }

        document.getElementById('chartContainer').style.display = 'none';
        document.getElementById('chartContainer').innerHTML = ''
        document.getElementById('tableContainer').style.display = 'none';
        document.getElementById('tableContainer').innerHTML = ''
        document.getElementById('drilldown').style.display = 'none';
    },
};

OCA.Data.Backend = {

    getData: function () {
        var objectDrilldown = document.getElementById('checkBoxObject').checked;
        var dateDrilldown = document.getElementById('checkBoxDate').checked;
        var datasetId = document.querySelector('#navigationDatasets .active').dataset.id;

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/data/data/') + datasetId,
            data: {
                'objectDrilldown': objectDrilldown,
                'dateDrilldown': dateDrilldown
            },
            success: function (data) {
                var visualization = document.querySelector('#navigationDatasets .active').dataset.visualization;
                if (visualization === 'chart') OCA.Data.UI.buildHighchart(data);
                else if (visualization === 'table') OCA.Data.UI.buildDataTable(data);
                else {
                    OCA.Data.UI.buildHighchart(data);
                    OCA.Data.UI.buildDataTable(data);
                }
            }.bind()
        });
    },

    getDataCsv: function () {
        var link = document.querySelector('#navigationDatasets .active').dataset.link;
        $.ajax({
            type: "GET",
            url: link,
            dataType: "text",
            success: function (data) {
                var visualization = document.querySelector('#navigationDatasets .active').dataset.visualization;
                if (visualization === 'chart') OCA.Data.UI.buildHighchartCsv(data);
                else if (visualization === 'table') OCA.Data.UI.buildDataTableCsv(data);
                else {
                    OCA.Data.UI.buildHighchartCsv(data);
                    OCA.Data.UI.buildDataTableCsv(data);
                }
            }
        });
    },

    getDatasets: function () {
        $.ajax({
            type: "GET",
            url: OC.generateUrl('apps/data/dataset'),
            success: function (data) {
                OCA.Data.UI.buildNavigation(data[0]);
            }
        });
    },

    gettest: function () {
        $.ajax({
            type: "GET",
            url: OC.generateUrl('apps/data/data'),
            success: function (data) {
                OCA.Data.UI.buildHighchart(data);
                OCA.Data.UI.buildDataTable(data);
            }
        });
    },

    createDataset: function () {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/data/dataset'),
            success: function (data) {
                OCA.Data.Core.initNavigation();
            }.bind()
        });
    },

    deleteDataset: function (datasetId) {
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/data/dataset/') + datasetId,
            success: function (data) {
                OCA.Data.Core.initNavigation();
            }.bind()
        });
    },

    updateDataset: function (datasetId) {
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/data/dataset/') + datasetId,
            data: {
                'name': document.getElementById('tableName').value,
                'type': document.getElementById('tableType').value,
                'link': document.getElementById('tableLink').value,
                'visualization': document.getElementById('tableVisualization').value,
                'chart': document.getElementById('tableChart').value
            },
            success: function (data) {
                OCA.Data.Core.initNavigation();
            }.bind()
        });
    },

};

document.addEventListener('DOMContentLoaded', function () {
    OCA.Data.Core.initNavigation();

    document.getElementById('checkBoxObject').addEventListener('click', OCA.Data.Core.handleDrilldownChange);
    document.getElementById('checkBoxDate').addEventListener('click', OCA.Data.Core.handleDrilldownChange);
    document.getElementById('newDatasetButton').addEventListener('click', OCA.Data.Core.handleNewDatasetButton);

    var categoryList = document.getElementById('navigationDatasets');
    //categoryList.addEventListener('click', OCA.Data.UI.handleNavigationClicked);

    $('#myInput').on('keyup', function () {
        table.search(this.value).draw();
    });
});
