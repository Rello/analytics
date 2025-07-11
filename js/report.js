/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2025 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

document.addEventListener('DOMContentLoaded', function () {

    // register handlers for the navigation bar
    OCA.Analytics.registerHandler('create', 'report', function () {
        OCA.Analytics.Report.newReport();
    });

    OCA.Analytics.registerHandler('navigationClicked', 'report', function (event) {
        OCA.Analytics.Report.handleNavigationClicked(event);
    });

    OCA.Analytics.registerHandler('delete', 'report', function (event) {
        OCA.Analytics.Sidebar.Report.handleDeleteButton(event);
    });

    OCA.Analytics.registerHandler('favoriteUpdate', 'report', function (id, isFavorite) {
        OCA.Analytics.Report.favoriteUpdate(id, isFavorite);
    });

    OCA.Analytics.registerHandler('saveIcon', 'report', function () {
        OCA.Analytics.Filter.Backend.saveReport();
    });

})

OCA = OCA || {};

OCA.Analytics.Report = OCA.Analytics.Report || {};
Object.assign(OCA.Analytics.Report, {

    newReport: function () {
        OCA.Analytics.Sidebar.close();
        OCA.Analytics.Wizard.sildeArray = [
            ['', ''],
            ['wizardNewGeneral', OCA.Analytics.Sidebar.Report.wizard],
            ['wizardNewType', ''],
            ['wizardNewVisual', '']
        ];
        OCA.Analytics.Wizard.show();
    },

    handleNavigationClicked: function () {

        OCA.Analytics.currentContentType = 'report';
        OCA.Analytics.Visualization.showContentByType('loading');

        document.getElementById('filterVisualisation').innerHTML = '';
        if (typeof (OCA.Analytics.currentReportData.options) !== 'undefined') {
            // reset any user-filters and display the filters stored for the report
            delete OCA.Analytics.currentReportData.options;
        }
        OCA.Analytics.unsavedChanges = false;
        OCA.Analytics.Sidebar.close();
        OCA.Analytics.Report.Backend.getData();
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

    /**
     * Render report data as chart or table
     */
    buildReport: function () {
        document.getElementById('reportHeader').innerText = OCA.Analytics.currentReportData.options.name;
        if (OCA.Analytics.currentReportData.options.subheader !== '') {
            document.getElementById('reportSubHeader').innerText = OCA.Analytics.currentReportData.options.subheader;
            OCA.Analytics.Visualization.showElement('reportSubHeader');
        }

        document.title = OCA.Analytics.currentReportData.options.name + ' - ' + OCA.Analytics.initialDocumentTitle;
        if (OCA.Analytics.currentReportData.status !== 'nodata' && parseInt(OCA.Analytics.currentReportData.error) === 0) {

            /*
            Sorting of integer values on x-axis #389
            Natural sorting needs to be selectable with a report or column parameter (drilldown dialog)

                        OCA.Analytics.currentReportData.data.sort((a, b) => {
                            let result = a[0].localeCompare(b[0], undefined, { numeric: true });
                            if (result === 0) {
                                return a[1].localeCompare(b[1], undefined, { numeric: true });
                            }
                            return result;
                        });
            */

            OCA.Analytics.currentReportData.data = OCA.Analytics.Visualization.formatDates(OCA.Analytics.currentReportData.data);
            let visualization = OCA.Analytics.currentReportData.options.visualization;
            if (visualization === 'chart') {
                let ctx = document.getElementById('myChart').getContext('2d');
                OCA.Analytics.Visualization.buildChart(ctx, OCA.Analytics.currentReportData, OCA.Analytics.Report.getDefaultChartOptions());
            } else if (visualization === 'table') {
                OCA.Analytics.Visualization.buildDataTable(document.getElementById("tableContainer"), OCA.Analytics.currentReportData);
            } else {
                let ctx = document.getElementById('myChart').getContext('2d');
                OCA.Analytics.Visualization.buildChart(ctx, OCA.Analytics.currentReportData, OCA.Analytics.Report.getDefaultChartOptions());
                OCA.Analytics.Visualization.buildDataTable(document.getElementById("tableContainer"), OCA.Analytics.currentReportData);
            }
        } else {
            OCA.Analytics.Visualization.showElement('noDataContainer');
            if (parseInt(OCA.Analytics.currentReportData.error) !== 0) {
                OCA.Analytics.Notification.notification('error', OCA.Analytics.currentReportData.error);
            }
        }
        OCA.Analytics.Report.buildReportOptions();
        OCA.Analytics.Visualization.showContentByType('report');
    },

    /**
     * Provide default configuration for Chart.js
     */
    getDefaultChartOptions: function () {
        return {
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
                'x': {
                    stacked: false,
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
    },

    /**
     * Show zoom reset button when chart is zoomed
     */
    toggleZoomResetButton: function () {
        OCA.Analytics.Visualization.showElement('chartZoomReset');
    },

    /**
     * Reset chart zoom level
     */
    handleZoomResetButton: function () {
        OCA.Analytics.chartObject.resetZoom();
        OCA.Analytics.Visualization.hideElement('chartZoomReset');
    },

    /**
     * Toggle visibility of the chart legend
     */
    toggleChartLegend: function () {
        OCA.Analytics.chartObject.legend.options.display = !OCA.Analytics.chartObject.legend.options.display
        OCA.Analytics.chartObject.update();
    },

    /**
     * Trigger download of the current chart image
     */
    downloadChart: function () {
        OCA.Analytics.Report.hideReportMenu();
        document.getElementById('downloadChartLink').href = OCA.Analytics.chartObject.toBase64Image();
        document.getElementById('downloadChartLink').setAttribute('download', OCA.Analytics.currentReportData.options.name + '.png');
        document.getElementById('downloadChartLink').click();
    },

    /**
     * Clear current chart and table elements
     */
    resetContentArea: function () {
        let key = Object.keys(OCA.Analytics.tableObject)[0];
        if (key !== undefined) {
            // Remove the event listener for 'order.dt' first
            OCA.Analytics.tableObject[key].off('length.dt order.dt column-reorder');
            OCA.Analytics.tableObject[key].destroy();
        }

        OCA.Analytics.tableObject = [];
        OCA.Analytics.Visualization.hideElement('chartContainer');
        OCA.Analytics.Visualization.hideElement('chartLegendContainer');
        document.getElementById('chartContainer').innerHTML = '';
        document.getElementById('chartContainer').innerHTML = '<button id="chartZoomReset" hidden>' + t('analytics', 'Reset zoom') + '</button><canvas id="myChart" ></canvas>';
        document.getElementById('chartZoomReset').addEventListener('click', OCA.Analytics.Report.handleZoomResetButton);
        OCA.Analytics.Visualization.hideElement('tableContainer');
        OCA.Analytics.Visualization.hideElement('tableSeparatorContainer');
        document.getElementById('tableContainer').innerHTML = '';

        OCA.Analytics.Visualization.showElement('menuBar');
        OCA.Analytics.Visualization.showElement('addFilterIcon');
        OCA.Analytics.Visualization.showElement('filterVisualisation');
        OCA.Analytics.Visualization.hideElement('reportSubHeader');
        OCA.Analytics.Visualization.hideElement('noDataContainer');
        document.getElementById('reportHeader').innerHTML = '';
        document.getElementById('reportSubHeader').innerHTML = '';

        OCA.Analytics.Report.hideReportMenu();
        document.getElementById('optionsMenuChartOptions').disabled = false;
        document.getElementById('optionsMenuTableOptions').disabled = false;
        document.getElementById('optionsMenuAnalysis').disabled = false;
        document.getElementById('optionsMenuColumnSelection').disabled = false;
        document.getElementById('optionsMenuSort').disabled = false;
        document.getElementById('optionsMenuTopN').disabled = false;
        document.getElementById('optionsMenuTimeAggregation').disabled = false;
        document.getElementById('optionsMenuDownload').disabled = false;
        document.getElementById('optionsMenuAnalysis').disabled = false;
        document.getElementById('optionsMenuTranslate').disabled = false;
    },

    /**
     * Enable or disable report menu entries
     */
    buildReportOptions: function () {
        let currentReport = OCA.Analytics.currentReportData;
        let canUpdate = parseInt(currentReport.options['permissions']) === OC.PERMISSION_UPDATE;
        let isInternalShare = currentReport.options['isShare'] !== undefined;
        let isExternalShare = document.getElementById('sharingToken').value !== '';

        if (isExternalShare) {
            if (canUpdate) {
                OCA.Analytics.Visualization.hideElement('optionsMenuIcon');
                OCA.Analytics.Filter.refreshFilterVisualisation();
            } else {
                document.getElementById('menuBar').style.visibility = 'hidden';
            }
            return;
        }

        if (!canUpdate) {
            OCA.Analytics.Visualization.hideElement('menuBar');
        }

        if (isInternalShare) {
            OCA.Analytics.Visualization.showElement('optionsMenuIcon');
        }

        if (!OCA.Analytics.translationAvailable) {
            document.getElementById('optionsMenuTranslate').disabled = true;
        } else {
            document.getElementById('translateLanguage').value = (OC.getLanguage() === 'en') ? 'en-gb' : OC.getLanguage();
            OCA.Analytics.Translation.languages();
        }

        if (currentReport.options.chart === 'doughnut') {
            document.getElementById('optionsMenuAnalysis').disabled = true;
        }

        let visualization = currentReport.options.visualization;
        if (visualization === 'table') {
            document.getElementById('optionsMenuChartOptions').disabled = true;
            document.getElementById('optionsMenuTableOptions').disabled = false;
            document.getElementById('optionsMenuAnalysis').disabled = true;
            document.getElementById('optionsMenuDownload').disabled = true;
        }

        if (visualization === 'chart') {
            document.getElementById('optionsMenuTableOptions').disabled = true;
        }

        let refresh = parseInt(currentReport.options.refresh);
        isNaN(refresh) ? refresh = 0 : refresh;
        document.getElementById('refresh' + refresh).checked = true;

        OCA.Analytics.Filter.refreshFilterVisualisation();
    },

    /**
     * Attach click handlers for report menu elements
     */
    reportOptionsEventlisteners: function () {
        document.getElementById('addFilterIcon').addEventListener('click', OCA.Analytics.Filter.openFilterDialog);
        document.getElementById('optionsMenuSave').addEventListener('click', OCA.Analytics.Filter.Backend.newReport);
        document.getElementById('optionsMenuColumnSelection').addEventListener('click', OCA.Analytics.Filter.openColumnsSelectionDialog);
        document.getElementById('optionsMenuSort').addEventListener('click', OCA.Analytics.Filter.openSortDialog);
        document.getElementById('optionsMenuTopN').addEventListener('click', OCA.Analytics.Filter.openTopNDialog);
        document.getElementById('optionsMenuTimeAggregation').addEventListener('click', OCA.Analytics.Filter.openTimeAggregationDialog);
        document.getElementById('optionsMenuChartOptions').addEventListener('click', OCA.Analytics.Filter.openChartOptionsDialog);
        document.getElementById('optionsMenuTableOptions').addEventListener('click', OCA.Analytics.Filter.openTableOptionsDialog);

        document.getElementById('optionsMenuAnalysis').addEventListener('click', OCA.Analytics.Report.showReportMenuAnalysis);
        document.getElementById('optionsMenuRefresh').addEventListener('click', OCA.Analytics.Report.showReportMenuRefresh);
        document.getElementById('optionsMenuTranslate').addEventListener('click', OCA.Analytics.Report.showReportMenuTranslate);
        document.getElementById('translateLanguage').addEventListener('change', OCA.Analytics.Translation.translate);
        document.getElementById('trendIcon').addEventListener('click', OCA.Analytics.Report.Functions.trend);
        document.getElementById('disAggregateIcon').addEventListener('click', OCA.Analytics.Report.Functions.disAggregate);
        document.getElementById('aggregateIcon').addEventListener('click', OCA.Analytics.Report.Functions.aggregate);
        //document.getElementById('linearRegressionIcon').addEventListener('click', OCA.Analytics.Report.Functions.linearRegression);
        document.getElementById('backIcon').addEventListener('click', OCA.Analytics.Report.showReportMenuMain);
        document.getElementById('backIcon2').addEventListener('click', OCA.Analytics.Report.showReportMenuMain);
        document.getElementById('backIcon3').addEventListener('click', OCA.Analytics.Report.showReportMenuMain);
        document.getElementById('optionsMenuDownload').addEventListener('click', OCA.Analytics.Report.downloadChart);
        document.getElementById('chartLegend').addEventListener('click', OCA.Analytics.Report.toggleChartLegend);

        //document.getElementById('menuSearchBox').addEventListener('keypress', OCA.Analytics.Report.tableSearch);

        let refresh = document.getElementsByName('refresh');
        for (let i = 0; i < refresh.length; i++) {
            refresh[i].addEventListener('change', OCA.Analytics.Filter.Backend.saveRefresh);
        }
    },

    /**
     * Close the report menu overlay
     */
    hideReportMenu: function () {
        if (document.getElementById('optionsMenu') !== null) {
            document.getElementById('optionsMenu').classList.remove('open');
        }
    },

    /**
     * Toggle the table options menu
     */
    toggleTableMenu: function () {
        document.getElementById('tableMenu').classList.toggle('open');
        document.getElementById('tableMenuMain').style.removeProperty('display');
    },

    /**
     * Display analysis related options
     */
    showReportMenuAnalysis: function () {
        document.getElementById('optionsMenuMainReport').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuSubAnalysis').style.removeProperty('display');
    },

    /**
     * Display refresh interval options
     */
    showReportMenuRefresh: function () {
        document.getElementById('optionsMenuMainReport').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuSubRefresh').style.removeProperty('display');
    },

    /**
     * Display translation options
     */
    showReportMenuTranslate: function () {
        document.getElementById('optionsMenuMainReport').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuSubTranslate').style.removeProperty('display');
    },

    /**
     * Return to the main report menu view
     */
    showReportMenuMain: function () {
        document.getElementById('optionsMenuSubAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuSubRefresh').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuSubTranslate').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuMainReport').style.removeProperty('display');
    },

    /**
     * Search inside the DataTable
     */
    tableSearch: function () {
        OCA.Analytics.tableObject.search(this.value).draw();
    },

    /**
     * Build and display a dropdown for filter values
     */
    showDropDownList: function (evt) {
        if (document.getElementById('tmpList')) {
            return;
        }
        let inputField = evt.target;
        if (!inputField.hasAttribute('data-dropDownListIndex')) {
            return;
        }
        // make the list align-able
        inputField.style.position = 'relative';
        let dropDownListIndex = inputField.dataset.dropdownlistindex;

        // get the values for the list from the report data
        let listValues = OCA.Analytics.Core.getDistinctValues(OCA.Analytics.currentReportData.data, dropDownListIndex);

        let ul = document.createElement('ul');
        ul.id = 'tmpList';
        ul.classList.add('dropDownList');
        ul.style.width = '198px';

        // take over the class from the input field to ensure same size
        for (let className of inputField.classList) {
            ul.classList.add(className);
        }

        let listCount = 0;
        let listCountMax = 4;
        for (let item of listValues) {
            let li = document.createElement('li');
            li.id = /\s/.test(item) ? `'${item}'` : item;
            ;
            li.innerText = item;
            li.title = item;
            listCount > listCountMax ? li.style.display = 'none' : li.style.display = '';
            ul.appendChild(li);
            listCount++;
        }

        // show a dummy element when the list is longer
        let liDummy = document.createElement('li');
        liDummy.innerText = '...';
        liDummy.id = 'dummy';
        listCount <= listCountMax ? liDummy.style.display = 'none' : liDummy.style.display = '';
        ul.appendChild(liDummy);

        // add the list to the input field and "open" its border at the bottom
        inputField.insertAdjacentElement('afterend', ul);
        inputField.classList.add('dropDownListParentOpen');

        // create an event listener on document level to recognize a click outside the list
        document.addEventListener('click', OCA.Analytics.Report.handleDropDownListClicked);

        inputField.addEventListener('keyup', function (event) {
            let li = document.getElementById('tmpList').getElementsByTagName('li');

            if (event.key === 'Tab') {
                // if the Tab key is pressed, we mimic autocompletion by using the first visible entry
                event.preventDefault(); // prevent the focus from moving to the next element

                // loop through all li items in the list
                for (let i = 0; i < li.length; i++) {
                    // if the li is visible, set the input value to its text and hide the list
                    if (li[i].style.display !== 'none') {
                        inputField.value = li[i].textContent;
                        OCA.Analytics.Report.hideDropDownList();
                        break; // exit the loop after finding the first visible li
                    }
                }
            } else {
                // for every keypress, we filter the list vor matching entries
                let filter = inputField.value.toUpperCase(); // get the typed text in uppercase
                // loop through all li items in the list
                listCount = 0;
                for (let i = 0; i < li.length; i++) {
                    let txtValue = li[i].textContent || li[i].innerText; // get the text in the li
                    // if the li text doesn't match the typed text, hide it
                    if (txtValue.toUpperCase().indexOf(filter) > -1 && listCount < listCountMax) {
                        li[i].style.display = "";
                        listCount++;
                    } else if (li[i].id === 'dummy' && listCount >= listCountMax) {
                        // always show the ... when there are more values available
                        li[i].style.display = "";
                        listCount++;
                    } else {
                        li[i].style.display = "none";
                    }
                }
            }
        });
    },

    /**
     * Handle selection or closing of dropdown list
     */
    handleDropDownListClicked: function (event) {
        let dropDownList = document.getElementById('tmpList');
        let inputField = dropDownList.previousElementSibling;
        let isClickInside = dropDownList.contains(event.target);
        let isClickOnInput = inputField === event.target;

        // If the click is inside the list and the target is an LI
        if (isClickInside && event.target.tagName === 'LI') {
            inputField.value = event.target.id;
            OCA.Analytics.Report.hideDropDownList();
        }
        // If the click is outside the list, hide the list
        else if (!isClickInside && !isClickOnInput) {
            OCA.Analytics.Report.hideDropDownList();
        }
    },

    /**
     * Remove dropdown list from the DOM
     */
    hideDropDownList: function () {
        // remove the global event listener again
        document.removeEventListener('click', OCA.Analytics.Report.handleDropDownListClicked);
        let dropDownList = document.getElementById('tmpList');
        let inputField = dropDownList.previousElementSibling;
        inputField.classList.remove('dropDownListParentOpen');
        dropDownList.remove();
    }
});

OCA.Analytics.Report.Functions = OCA.Analytics.Report.Functions || {};
Object.assign(OCA.Analytics.Report.Functions = {

    /**
     * Add a linear trend line to the visible datasets
     */
    trend: function () {
        OCA.Analytics.Visualization.showElement('chartLegendContainer');
        OCA.Analytics.Report.hideReportMenu();

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

            let regression = OCA.Analytics.Report.Functions.regressionFunction(xValues, yValues);
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

    /**
     * Calculate slope and intercept for linear regression
     */
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

    /**
     * Add an aggregated data series
     */
    aggregate: function () {
        OCA.Analytics.Report.Functions.aggregationFunction('aggregate');
        // const cumulativeSum = (sum => value => sum += value)(0);
        // datasets[0]['data'] = datasets[0]['data'].map(cumulativeSum)
    },

    /**
     * Reverse aggregation of a data series
     */
    disAggregate: function () {
        OCA.Analytics.Report.Functions.aggregationFunction('disaggregate');
    },

    /**
     * Internal helper to (dis-)aggregate datasets
     */
    aggregationFunction: function (mode) {
        OCA.Analytics.Visualization.showElement('chartLegendContainer');
        OCA.Analytics.Report.hideReportMenu();

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

});

OCA.Analytics.Report.Backend = OCA.Analytics.Report.Backend || {};
Object.assign(OCA.Analytics.Report.Backend = {

    /**
     * Fetch report data from the backend
     */
    getData: function () {
        if (OCA.Analytics.currentXhrRequest) OCA.Analytics.currentXhrRequest.abort();
        OCA.Analytics.Report.resetContentArea();

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
                ajaxData.dataoptions = JSON.stringify(OCA.Analytics.currentReportData.options.dataoptions);
            }
            if (typeof (OCA.Analytics.currentReportData.options.chartoptions) !== 'undefined') {
                ajaxData.chartoptions = JSON.stringify(OCA.Analytics.currentReportData.options.chartoptions);
            }
            if (typeof (OCA.Analytics.currentReportData.options.tableoptions) !== 'undefined') {
                ajaxData.tableoptions = JSON.stringify(OCA.Analytics.currentReportData.options.tableoptions);
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

                OCA.Analytics.Visualization.hideElement('analytics-loading');
                OCA.Analytics.Visualization.showElement('analytics-content-report');

                OCA.Analytics.currentReportData = data;
                try {
                    // Chart.js v4.4.3 changed from xAxes to x. In case the user has old chart options, they need to be corrected
                    let parsedChartOptions = JSON.parse(OCA.Analytics.currentReportData.options.chartoptions.replace(/xAxes/g, 'x'));
                    OCA.Analytics.currentReportData.options.chartoptions = (parsedChartOptions !== null && typeof parsedChartOptions === 'object') ? parsedChartOptions : {};
                } catch (e) {
                    OCA.Analytics.currentReportData.options.chartoptions = {};
                }

                try {
                    let parsedDataOptions = JSON.parse(OCA.Analytics.currentReportData.options.dataoptions);
                    OCA.Analytics.currentReportData.options.dataoptions = (parsedDataOptions !== null && typeof parsedDataOptions === 'object') ? parsedDataOptions : {};
                } catch (e) {
                    OCA.Analytics.currentReportData.options.dataoptions = {};
                }

                try {
                    let parsedFilterOptions = JSON.parse(OCA.Analytics.currentReportData.options.filteroptions);
                    OCA.Analytics.currentReportData.options.filteroptions = (parsedFilterOptions !== null && typeof parsedFilterOptions === 'object') ? parsedFilterOptions : {};
                } catch (e) {
                    OCA.Analytics.currentReportData.options.filteroptions = {};
                }

                try {
                    let parsedTableOptions = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions);
                    OCA.Analytics.currentReportData.options.tableoptions = (parsedTableOptions !== null && typeof parsedTableOptions === 'object') ? parsedTableOptions : {};
                } catch (e) {
                    OCA.Analytics.currentReportData.options.tableoptions = {};
                }

                document.getElementById('reportHeader').innerText = data.options.name;
                if (data.options.subheader !== '') {
                    document.getElementById('reportSubHeader').innerText = data.options.subheader;
                    OCA.Analytics.Visualization.showElement('reportSubHeader');
                }

                // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
                OCA.Analytics.currentReportData = OCA.Analytics.Visualization.sortDates(OCA.Analytics.currentReportData);
                OCA.Analytics.currentReportData = OCA.Analytics.Visualization.applyTimeAggregation(OCA.Analytics.currentReportData);
                OCA.Analytics.currentReportData = OCA.Analytics.Visualization.applyTopN(OCA.Analytics.currentReportData);

                OCA.Analytics.Report.buildReport();

                let refresh = parseInt(OCA.Analytics.currentReportData.options.refresh);
                OCA.Analytics.Report.Backend.startRefreshTimer(refresh);
            }
        };
        xhr.send();
    },

    /**
     * Retrieve available dataset templates from server
     */
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

    /**
     * Continuously refresh data in given interval
     */
    startRefreshTimer(minutes) {
        if (minutes !== 0 && !isNaN(minutes)) {
            if (OCA.Analytics.refreshTimer === null) {
                OCA.Analytics.refreshTimer = setTimeout(OCA.Analytics.Report.Backend.getData, minutes * 60 * 1000)
            } else {
                clearTimeout(OCA.Analytics.refreshTimer)
                OCA.Analytics.refreshTimer = null
                OCA.Analytics.Report.Backend.startRefreshTimer(minutes);
            }
        } else {
            if (OCA.Analytics.refreshTimer !== null) {
                clearTimeout(OCA.Analytics.refreshTimer)
                OCA.Analytics.refreshTimer = null
            }
        }
    },
});