/**
 * Analytics
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
        TYPE_SHARED: 99,
        SHARE_TYPE_USER: 0,
        SHARE_TYPE_GROUP: 1,
        SHARE_TYPE_LINK: 3,
        SHARE_TYPE_ROOM: 10,
        initialDocumentTitle: null,
        currentReportData: {},
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
        unsavedFilters: null,
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
                OCA.Analytics.Backend.createDataset(urlHash.substring(3));
            } else if (urlHash[2] === 'r') {
                OCA.Analytics.Navigation.init(urlHash.substring(4));
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
};

OCA.Analytics.UI = {

    buildDataTable: function (jsondata) {
        document.getElementById('tableContainer').style.removeProperty('display');

        let columns = [];
        let data, unit = '';

        let header = jsondata.header;
        let allDimensions = jsondata.dimensions;
        (jsondata.dimensions) ? allDimensions = jsondata.dimensions : allDimensions = jsondata.header;
        let headerKeys = Object.keys(header);
        for (let i = 0; i < headerKeys.length; i++) {
            columns[i] = {'title': header[headerKeys[i]]};
            let columnType = Object.keys(allDimensions).find(key => allDimensions[key] === header[headerKeys[i]]);

            if (i === headerKeys.length - 1) {
                // prepare for later unit cloumn
                //columns[i]['render'] = function(data, type, row, meta) {
                //    return data + ' ' + row[row.length-2];
                //};
                if (header[headerKeys[i]].length === 1) {
                    unit = header[headerKeys[i]];
                }
                columns[i]['render'] = $.fn.dataTable.render.number('.', ',', 2, unit + ' ');
                columns[i]['className'] = 'dt-right';
            } else if (columnType === 'timestamp') {
                columns[i]['render'] = function (data, type, row) {
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

        thresholds = thresholds.filter(p => p.dimension1 === data[0] || p.dimension1 === '*');

        for (let threshold of thresholds) {
            const comparison = operators[threshold['option']](parseFloat(data[2]), parseFloat(threshold['value']));
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

        let chartType;
        jsondata.options.chart === '' ? chartType = 'column' : chartType = jsondata.options.chart;
        let datasets = [], xAxisCategories = [];
        let lastObject = false;
        let dataSeries = -1;
        let hidden = false;

        let header = jsondata.header;
        let headerKeys = Object.keys(header).length;
        let dataSeriesColumn = headerKeys - 3; //characteristic is taken from the second last column
        let characteristicColumn = headerKeys - 2; //characteristic is taken from the second last column
        let keyFigureColumn = headerKeys - 1; //key figures is taken from the last column

        Chart.defaults.global.elements.line.borderWidth = 2;
        Chart.defaults.global.elements.line.tension = 0.1;
        Chart.defaults.global.elements.line.fill = false;
        Chart.defaults.global.elements.point.radius = 1;
        Chart.defaults.global.tooltips.enabled = true;

        var chartOptions = {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                yAxes: [{
                    id: 'primary',
                    stacked: false,
                    position: 'left',
                    display: true,
                    gridLines: {
                        display: true,
                    },
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString();
                        },
                    },
                }, {
                    id: 'secondary',
                    stacked: false,
                    position: 'right',
                    display: false,
                    gridLines: {
                        display: false,
                    },
                }],
                xAxes: [{
                    type: 'category',
                    distribution: 'linear',
                    gridLines: {
                        display: false
                    },
                    display: true,
                }],
            },
            plugins: {
                colorschemes: {
                    scheme: 'tableau.ClassicLight10'
                }
            },
            legend: {
                display: false,
                position: 'bottom'
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
                            return datasetLabel + ': ' + parseInt(tooltipItem.yLabel).toLocaleString();
                        } else {
                            return datasetLabel;
                        }
                    }
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
                    t: values[characteristicColumn],
                    y: parseFloat(values[keyFigureColumn])
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
            chartOptions.scales.xAxes[0].type = 'time';
            chartOptions.scales.xAxes[0].distribution = 'linear';
        } else if (chartType === 'area') {
            chartOptions.scales.xAxes[0].type = 'time';
            chartOptions.scales.xAxes[0].distribution = 'linear';
            chartOptions.scales.yAxes[0].stacked = true;
            Chart.defaults.global.elements.line.fill = true;
        } else if (chartType === 'doughnut') {
            chartOptions.scales.xAxes[0].display = false;
            chartOptions.scales.yAxes[0].display = false;
            chartOptions.scales.yAxes[0].gridLines.display = false;
            chartOptions.scales.yAxes[1].display = false;
            chartOptions.scales.yAxes[1].gridLines.display = false;
            chartOptions.circumference = Math.PI;
            chartOptions.rotation = -Math.PI;
            chartOptions.legend.display = true;
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

        // the user can modify dataset/series settings
        // these are merged with the data array coming from the backend
        // e.g. assign one series to the secondary y-axis: '[{"yAxisID":"B"},{},{"yAxisID":"B"},{}]'
        //let userDatasetOptions = document.getElementById('userDatasetOptions').value;
        let userDatasetOptions = jsondata.options.dataoptions;
        if (userDatasetOptions !== '' && userDatasetOptions !== null) {
            datasets = cloner.deep.merge(JSON.parse(userDatasetOptions), datasets);
        }

        let myChart = new Chart(ctx, {
            type: OCA.Analytics.chartTypeMapping[chartType],
            data: {
                labels: xAxisCategories,
                datasets: datasets
            },
            options: chartOptions,
        });

        document.getElementById('chartLegend').addEventListener('click', function () {
            myChart.options.legend.display = !myChart.options.legend.display;
            myChart.update();
        });
    },

    resetContent: function () {
        if (document.getElementById('advanced').value === 'true') {
            document.getElementById('analytics-intro').removeAttribute('hidden');
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
            document.getElementById('reportHeader').innerHTML = '';
            document.getElementById('reportSubHeader').innerHTML = '';
            document.getElementById('reportSubHeader').style.display = 'none';
            document.getElementById('filterContainer').style.display = 'none';
            document.getElementById('optionContainer').style.display = 'none';
            document.getElementById('optionsIcon').style.display = 'none';
            document.getElementById('noDataContainer').style.display = 'none';
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
    },

    whatsNewSuccess: function (data, statusText, xhr) {
        if (xhr.status !== 200) {
            return
        }

        let item, menuItem, text, icon

        const div = document.createElement('div')
        div.classList.add('popovermenu', 'open', 'whatsNewPopover', 'menu-left')

        const list = document.createElement('ul')

        // header
        item = document.createElement('li')
        menuItem = document.createElement('span')
        menuItem.className = 'menuitem'

        text = document.createElement('span')
        text.innerText = t('core', 'New in') + ' ' + data['product']
        text.className = 'caption'
        menuItem.appendChild(text)

        icon = document.createElement('span')
        icon.className = 'icon-close'
        icon.onclick = function () {
            OCA.Analytics.Backend.whatsnewDismiss(data['version'])
        }
        menuItem.appendChild(icon)

        item.appendChild(menuItem)
        list.appendChild(item)

        // Highlights
        for (const i in data['whatsNew']['regular']) {
            const whatsNewTextItem = data['whatsNew']['regular'][i]
            item = document.createElement('li')

            menuItem = document.createElement('span')
            menuItem.className = 'menuitem'

            icon = document.createElement('span')
            icon.className = 'icon-checkmark'
            menuItem.appendChild(icon)

            text = document.createElement('p')
            text.innerHTML = _.escape(whatsNewTextItem)
            menuItem.appendChild(text)

            item.appendChild(menuItem)
            list.appendChild(item)
        }

        // Changelog URL
        if (!_.isUndefined(data['changelogURL'])) {
            item = document.createElement('li')

            menuItem = document.createElement('a')
            menuItem.href = data['changelogURL']
            menuItem.rel = 'noreferrer noopener'
            menuItem.target = '_blank'

            icon = document.createElement('span')
            icon.className = 'icon-link'
            menuItem.appendChild(icon)

            text = document.createElement('span')
            text.innerText = t('core', 'View changelog')
            menuItem.appendChild(text)

            item.appendChild(menuItem)
            list.appendChild(item)
        }

        div.appendChild(list)
        document.body.appendChild(div)
    }
};

OCA.Analytics.Datasource = {
    buildDropdown: function () {
        let options = document.createDocumentFragment();
        for (let key in OCA.Analytics.datasources) {
            let value = OCA.Analytics.datasources[key];
            let option = document.createElement('option');
            option.value = key;
            option.innerText = value;
            options.appendChild(option);
        }
        return options;
    },

    buildOptionsForm: function (datasource) {
        let template = OCA.Analytics.datasourceOptions[datasource];
        let form = document.createDocumentFragment();

        for (let templateOption of template) {
            // loop all options of the datasourcetemplate and create the input form
            let tablerow = document.createElement('div');
            tablerow.style.display = 'table-row';
            let label = document.createElement('div');
            label.style.display = 'table-cell';
            label.style.width = '100%';
            label.innerText = templateOption.name;

            let input;
            if (templateOption.type && templateOption.type === 'tf') {
                input = OCA.Analytics.Datasource.buildOptionsSelect(templateOption);
            } else {
                input = OCA.Analytics.Datasource.buildOptionsInput(templateOption);
            }
            input.style.display = 'table-cell';
            form.appendChild(tablerow);
            tablerow.appendChild(label);
            tablerow.appendChild(input);
        }
        return form;
    },

    buildOptionsInput: function (templateOption) {
        let input = document.createElement('input');
        input.style.display = 'inline-flex';
        input.classList.add('sidebarInput');
        input.placeholder = templateOption.placeholder;
        input.id = templateOption.id;
        return input;
    },

    buildOptionsSelect: function (templateOption) {
        let input = document.createElement('select');
        input.style.display = 'inline-flex';
        input.classList.add('sidebarInput');
        input.id = templateOption.id;

        let selectOptions = templateOption.placeholder.split("/")
        for (let selectOption of selectOptions) {
            let option = document.createElement('option');
            option.value = selectOption;
            option.innerText = selectOption;
            input.appendChild(option);
        }
        return input;
    },

};

OCA.Analytics.Backend = {

    getData: function () {
        OCA.Analytics.UI.resetContent();
        document.getElementById('analytics-intro').hidden = true;
        document.getElementById('analytics-content').hidden = true;
        document.getElementById('analytics-loading').hidden = false;

        let url;
        if (document.getElementById('sharingToken').value === '') {
            const datasetId = document.querySelector('#navigationDatasets .active').firstElementChild.dataset.id;
            url = OC.generateUrl('apps/analytics/data/') + datasetId;
        } else {
            const token = document.getElementById('sharingToken').value;
            url = OC.generateUrl('apps/analytics/data/public/') + token;
        }

        // send user current filteroptions to the datarequest;
        // if nothing is changed by the user, the filter which is stored for the report, will be used
        let ajaxData = {};
        if (typeof (OCA.Analytics.currentReportData.options) !== 'undefined' && typeof (OCA.Analytics.currentReportData.options.filteroptions) !== 'undefined') {
            ajaxData = {'filteroptions': JSON.stringify(OCA.Analytics.currentReportData.options.filteroptions)};
        }

        $.ajax({
            type: 'GET',
            url: url,
            data: ajaxData,
            success: function (data) {
                document.getElementById('analytics-loading').hidden = true;
                document.getElementById('analytics-content').hidden = false;
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
                    document.getElementById('reportSubHeader').style.removeProperty('display');
                }

                let canUpdate = parseInt(data.options.permissions) === OC.PERMISSION_UPDATE;
                let isInternalShare = data.options.isShare !== undefined;
                let isExternalShare = document.getElementById('sharingToken').value !== '';

                if (canUpdate) {
                    document.getElementById('filterContainer').style.removeProperty('display');
                    OCA.Analytics.Filter.refreshFilterVisualisation();
                    if (!isInternalShare && !isExternalShare) {
                        document.getElementById('optionContainer').style.removeProperty('display');
                    }
                } else if (isExternalShare) {
                    document.getElementById('filterBar').remove();
                }

                document.title = data.options.name + ' @ ' + OCA.Analytics.initialDocumentTitle;
                if (data.status !== 'nodata') {

                    let visualization = data.options.visualization;
                    if (visualization === 'chart') {
                        document.getElementById('optionsIcon').style.removeProperty('display');
                        OCA.Analytics.UI.buildChart(data);
                    } else if (visualization === 'table') {
                        OCA.Analytics.UI.buildDataTable(data);
                    } else {
                        document.getElementById('optionsIcon').style.removeProperty('display');
                        OCA.Analytics.UI.buildChart(data);
                        OCA.Analytics.UI.buildDataTable(data);
                    }
                } else {
                    document.getElementById('noDataContainer').style.removeProperty('display');
                }
            }
        });
    },

    getDatasourceDefinitions: function () {
        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/datasource'),
            success: function (data) {
                OCA.Analytics.datasourceOptions = data['options'];
                OCA.Analytics.datasources = data['datasources'];
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

    whatsnew: function (options) {
        options = options || {}
        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/whatsnew'),
            data: {'format': 'json'},
            success: options.success || function (data, statusText, xhr) {
                OCA.Analytics.UI.whatsNewSuccess(data, statusText, xhr);
            },
        });
    },

    whatsnewDismiss: function (version) {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/whatsnew'),
            data: {version: encodeURIComponent(version)}
        })
        $('.whatsNewPopover').remove();
    },

    favoriteUpdate: function (datasetId, isFavorite) {
        let params = 'favorite=' + isFavorite;
        let xhr = new XMLHttpRequest();
        xhr.open('POST', OC.generateUrl('apps/analytics/favorite/' + datasetId, true), true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.send(params);
    },
};

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.initialDocumentTitle = document.title;
    document.getElementById('analytics-warning').classList.add('hidden');

    if (document.getElementById('sharingToken').value === '') {
        OCA.Analytics.Backend.whatsnew();
        document.getElementById('analytics-intro').removeAttribute('hidden');
        OCA.Analytics.Core.initApplication();
        if (document.getElementById('advanced').value === 'false') {
            document.getElementById('createDemoReport').addEventListener('click', OCA.Analytics.Navigation.createDemoReport);
            document.getElementById('createDemoGithubReport').addEventListener('click', OCA.Analytics.Navigation.createDemoGithubReport);
            document.getElementById('optionsIcon').addEventListener('click', OCA.Analytics.Filter.openOptionsDialog);
            document.getElementById('saveIcon').addEventListener('click', OCA.Analytics.Filter.Backend.updateDataset);
            document.getElementById('addFilterIcon').addEventListener('click', OCA.Analytics.Filter.openFilterDialog);
            document.getElementById('drilldownIcon').addEventListener('click', OCA.Analytics.Filter.openDrilldownDialog);
        }
    } else {
        document.getElementById('addFilterIcon').addEventListener('click', OCA.Analytics.Filter.openFilterDialog);
        document.getElementById('drilldownIcon').addEventListener('click', OCA.Analytics.Filter.openDrilldownDialog);
        OCA.Analytics.Backend.getData();
    }

    window.addEventListener("beforeprint", function () {
        document.getElementById('chartContainer').style.height = document.getElementById('myChart').style.height;
    });
});
