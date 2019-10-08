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
    loadDatasets: function () {

        var navigationData = {id: "1", name: "Test 1"};
        OCA.Data.UI.buildNavigationRow(navigationData);

        navigationData = {id: "2", name: "Erich-Weinert-Straße"};
        OCA.Data.UI.buildNavigationRow(navigationData);

        navigationData = {id: "3", name: "Pflanzen"};
        OCA.Data.UI.buildNavigationRow(navigationData);

        var categoryList = document.getElementById('navigationDatasets');
        categoryList.addEventListener('click', OCA.Data.UI.handleNavigationClicked);
    },

};

OCA.Data.UI = {
    buildNavigationRow: function (navigationData) {
        var li = document.createElement('li');
        li.dataset.id = navigationData.id;

        var a = document.createElement('a');
        a.setAttribute('href', '#');
        a.classList.add('icon-projects');
        a.innerText = navigationData.name;
        li.appendChild(a);

        var categoryList = document.getElementById('navigationDatasets');
        categoryList.appendChild(li);
    },

    handleNavigationClicked: function (evt) {
        var activeCategory = document.querySelector('#navigationDatasets .active');
        if (evt) {
            if (activeCategory) {
                activeCategory.classList.remove('active');
            }
            var parentLi = evt.target.closest('li');
            parentLi.classList.add('active');
            OCA.Data.Backend.getData(parentLi.dataset.id);
        }
    },

    buildDataTable: function (jsondata) {
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
                first:      "erster",
                    previous:   "zurück",
                    next:       "weiter",
                    last:       "letzter"
            },
        };

        if ($.fn.dataTable.isDataTable('#example'))
        {
            $('#example').DataTable().destroy();
            $('#example').empty();
        }

        $('#example').DataTable({
            data: jsondata.data,
            columns: columns,
            language: language
    });
    },

    buildHighchart: function (jsondata) {
        var objectArray = [];
        var lastObject = 0;
        var index = 0;

        for (var values of jsondata.data) {

            if (lastObject !== values['object']) {
                objectArray.push({data:[],name:values['object']});
                if (lastObject !== 0) index++;
                lastObject = values['object'];
            }
            objectArray[index]['data'].push([Date.UTC(values['date']),parseFloat(values['value'])]);

        }

        Highcharts.chart('container', {
            series: objectArray,
            chart: {
                type: 'line'
            },
            title: {
                text: 'Nextcloud Data'
            },
            yAxis: {
                allowDecimals: false,
                title: {
                    text: 'Units'
                }
            },
            xAxis: {
                type: 'datetime',
                dateTimeLabelFormats: { // don't display the dummy year
                    month: '%Y'
                },
                title: {
                    text: 'Date'
                }
            },
            tooltip: {
                formatter: function () {
                    return '<b>' + this.series.name + '</b><br/>' +
                        this.point.y + ' ' + this.point.name.toLowerCase();
                }
            }
        });
    },

    buildDataChart: function (jsondata) {
        var data = Object.keys(jsondata.data[0]);
        var ctx = document.getElementById('myChart');

        var player = [];
        var score = [];

         score.push({t:"1491170400000",y:"500.00"});
        score.push({t:"1491180400000",y:"500.00"});
        score.push({t:"1491190400000",y:"500.00"});

        //1491170400000

        var myLineChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Kosten',
                    data: score,
                    type: 'line',
                    pointRadius: 0,
                    fill: false,
                    lineTension: 0,
                    borderWidth: 2
                }]
            },
            options: {
                scales: {
                    xAxes: [{
                        type: 'time',
                        distribution: 'series',
                        ticks: {
                            source: 'data',
                            autoSkip: true
                        }
                    }],
                    yAxes: [{
                        scaleLabel: {
                            display: false,
                            labelString: 'Closing price ($)'
                        }
                    }]
                },
                elements: {
                    line: {
                        tension: 0 // disables bezier curves
                    }
                }
            }
        });
    },
};

OCA.Data.Backend = {

    getData: function () {
        var objectDrilldown = document.getElementById('checkBoxObject').checked;
        var dateDrilldown = document.getElementById('checkBoxDate').checked;
        var id = document.querySelector('#navigationDatasets .active').dataset.id;

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/data/getdata'),
            data: {
                'id': id,
                'objectDrilldown': objectDrilldown,
                'dateDrilldown': dateDrilldown
            },
            success: function (jsondata) {
                OCA.Data.UI.buildDataTable(jsondata);
                OCA.Data.UI.buildHighchart(jsondata);
            }.bind()
        });
    }
};

document.addEventListener('DOMContentLoaded', function () {
    OCA.Data.Core.loadDatasets();

    document.getElementById('checkBoxObject').addEventListener('click', OCA.Data.Backend.getData);
    document.getElementById('checkBoxDate').addEventListener('click', OCA.Data.Backend.getData);

    $('#myInput').on('keyup', function () {
        table.search(this.value).draw();
    });


});