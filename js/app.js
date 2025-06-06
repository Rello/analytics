/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
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
    OCA.Analytics.initialDocumentTitle = document.title;
    OCA.Analytics.Visualization.hideElement('analytics-warning');

    if (document.getElementById('advanced').value === 'true') {
        OCA.Analytics.isDataset = true;
    }

    OCA.Analytics.translationAvailable = OCA.Analytics.Core.getInitialState('translationAvailable');

    // Todo:
    // register handlers for the navigation bar as in panorama

    window.addEventListener("beforeprint", function () {
        //document.getElementById('chartContainer').style.height = document.getElementById('myChart').style.height;
    });

    OCA.Analytics.Core.init();
});

OCA.Analytics = Object.assign({}, OCA.Analytics, {
    TYPE_GROUP: 0,
    TYPE_INTERNAL_FILE: 1,
    TYPE_INTERNAL_DB: 2,
    TYPE_GIT: 3,
    TYPE_EXTERNAL_FILE: 4,
    TYPE_EXTERNAL_REGEX: 5,
    TYPE_SPREADSHEET: 7,
    TYPE_SHARED: 99,
    SHARE_TYPE_USER: 0,
    SHARE_TYPE_GROUP: 1,
    SHARE_TYPE_LINK: 3,
    SHARE_TYPE_ROOM: 10,
    SHARE_PERMISSION_UPDATE: 2,
    initialDocumentTitle: null,
    isDataset: false,
    currentReportData: {},
    chartObject: null,
    tableObject: [],
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
    datasources: [],
    datasets: [],
    reports: [],
    unsavedFilters: false,
    refreshTimer: null,
    currentXhrRequest: null,
    translationAvailable: false,
    isNewObject: false,

    /**
     * Build common request headers for backend calls
     */
    headers: function () {
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');
        return headers;
    }
});
/**
 * @namespace OCA.Analytics.Core
 */
OCA.Analytics.Core = {
    /**
     * Initialize navigation and register UI handlers
     */
    init: function () {
        if (document.getElementById('sharingToken').value !== '') {
            document.getElementById('byAnalytics').classList.toggle('analyticsFullscreen');
            OCA.Analytics.Backend.getData();
            return;
        }

        // URL semantic is analytics/*type*/id
        let regex = /\/analytics\/([a-zA-Z0-9]+)\/(\d+)/;
        let match = window.location.href.match(regex);

        if (match) {
            OCA.Analytics.Navigation.init(parseInt(match[2]));
        } else {
            OCA.Analytics.Navigation.init();
            // Dashboard has to be loaded from the navigation as it depends on the report index
        }

        OCA.Analytics.Visualization.showElement('analytics-intro');
        if (!OCA.Analytics.isDataset) {
            OCA.Analytics.UI.reportOptionsEventlisteners();
            document.getElementById("infoBoxReport").addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
            document.getElementById("infoBoxIntro").addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
            document.getElementById("infoBoxWiki").addEventListener('click', OCA.Analytics.Core.openWiki);
            document.getElementById('fullscreenToggle').addEventListener('click', OCA.Analytics.Visualization.toggleFullscreen);
        }
    },

    /**
     * Return unique values of a given column
     */
    getDistinctValues: function (array, index) {
        let unique = [];
        let distinct = [];
        if (array === undefined) {
            return distinct;
        }
        for (let i = 0; i < array.length; i++) {
            if (!unique[array[i][index]]) {
                distinct.push(array[i][index]);
                unique[array[i][index]] = 1;
            }
        }
        return distinct;
    },

    /**
     * Read a value from the initial state injected by PHP
     */
    getInitialState: function (key) {
        const app = 'analytics';
        const elem = document.querySelector('#initial-state-' + app + '-' + key);
        if (elem === null) {
            return false;
        }
        return JSON.parse(atob(elem.value))
    },

    /**
     * Open project wiki in a new browser tab
     */
    openWiki: function () {
        window.open('https://github.com/rello/analytics/wiki', '_blank');
    }
};

OCA.Analytics.UI = {

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
                OCA.Analytics.Visualization.buildChart(ctx, OCA.Analytics.currentReportData, OCA.Analytics.UI.getDefaultChartOptions());
            } else if (visualization === 'table') {
                OCA.Analytics.Visualization.buildDataTable(document.getElementById("tableContainer"), OCA.Analytics.currentReportData);
            } else {
                let ctx = document.getElementById('myChart').getContext('2d');
                OCA.Analytics.Visualization.buildChart(ctx, OCA.Analytics.currentReportData, OCA.Analytics.UI.getDefaultChartOptions());
                OCA.Analytics.Visualization.buildDataTable(document.getElementById("tableContainer"), OCA.Analytics.currentReportData);
            }
        } else {
            OCA.Analytics.Visualization.showElement('noDataContainer');
            if (parseInt(OCA.Analytics.currentReportData.error) !== 0) {
                OCA.Analytics.Notification.notification('error', OCA.Analytics.currentReportData.error);
            }
        }
        OCA.Analytics.UI.buildReportOptions();

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
        OCA.Analytics.UI.hideReportMenu();
        document.getElementById('downloadChartLink').href = OCA.Analytics.chartObject.toBase64Image();
        document.getElementById('downloadChartLink').setAttribute('download', OCA.Analytics.currentReportData.options.name + '.png');
        document.getElementById('downloadChartLink').click();
    },

    /**
     * Clear current chart and table elements
     */
    resetContentArea: function () {
        if (OCA.Analytics.isDataset) {
            OCA.Analytics.Visualization.showElement('analytics-intro');
            document.getElementById('app-sidebar').classList.add('disappear');
        } else {
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
            document.getElementById('chartZoomReset').addEventListener('click', OCA.Analytics.UI.handleZoomResetButton);
            OCA.Analytics.Visualization.hideElement('tableContainer');
            OCA.Analytics.Visualization.hideElement('tableSeparatorContainer');
            //OCA.Analytics.Visualization.hideElement('tableMenuBar');
            document.getElementById('tableContainer').innerHTML = '';

            OCA.Analytics.Visualization.hideElement('reportSubHeader');
            OCA.Analytics.Visualization.hideElement('noDataContainer');
            document.getElementById('reportHeader').innerHTML = '';
            document.getElementById('reportSubHeader').innerHTML = '';

            OCA.Analytics.Visualization.showElement('reportMenuBar');
            OCA.Analytics.UI.hideReportMenu();
            document.getElementById('reportMenuChartOptions').disabled = false;
            document.getElementById('reportMenuTableOptions').disabled = false;
            document.getElementById('reportMenuAnalysis').disabled = false;
            document.getElementById('reportMenuColumnSelection').disabled = false;
            document.getElementById('reportMenuSort').disabled = false;
            document.getElementById('reportMenuTopN').disabled = false;
            document.getElementById('reportMenuTimeAggregation').disabled = false;
            document.getElementById('reportMenuDownload').disabled = false;
            document.getElementById('reportMenuAnalysis').disabled = false;
            document.getElementById('reportMenuTranslate').disabled = false;
        }
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
                OCA.Analytics.Visualization.hideElement('reportMenuIcon');
                OCA.Analytics.Filter.refreshFilterVisualisation();
            } else {
                //document.getElementById('reportMenuBar').remove();
                OCA.Analytics.Visualization.hideElement('reportMenuBar');
                //document.getElementById('reportMenuBar').id = 'reportMenuBarHidden';
            }
            return;
        }

        if (!canUpdate) {
            OCA.Analytics.Visualization.hideElement('reportMenuBar');
        }

        if (isInternalShare) {
            OCA.Analytics.Visualization.showElement('reportMenuIcon');
        }

        if (!OCA.Analytics.translationAvailable) {
            document.getElementById('reportMenuTranslate').disabled = true;
        } else {
            document.getElementById('translateLanguage').value = (OC.getLanguage() === 'en') ? 'en-gb' : OC.getLanguage();
            OCA.Analytics.Translation.languages();
        }

        if (currentReport.options.chart === 'doughnut') {
            document.getElementById('reportMenuAnalysis').disabled = true;
        }

        let visualization = currentReport.options.visualization;
        if (visualization === 'table') {
            document.getElementById('reportMenuChartOptions').disabled = true;
            document.getElementById('reportMenuTableOptions').disabled = false;
            document.getElementById('reportMenuAnalysis').disabled = true;
            document.getElementById('reportMenuDownload').disabled = true;
        }

        if (visualization === 'chart') {
            document.getElementById('reportMenuTableOptions').disabled = true;
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
        document.getElementById('reportMenuIcon').addEventListener('click', OCA.Analytics.UI.toggleReportMenu);
        //document.getElementById('tableMenuIcon').addEventListener('click', OCA.Analytics.UI.toggleTableMenu);
        document.getElementById('saveIcon').addEventListener('click', OCA.Analytics.Filter.Backend.saveReport);
        document.getElementById('reportMenuSave').addEventListener('click', OCA.Analytics.Filter.Backend.newReport);
        document.getElementById('reportMenuColumnSelection').addEventListener('click', OCA.Analytics.Filter.openColumnsSelectionDialog);
        document.getElementById('reportMenuSort').addEventListener('click', OCA.Analytics.Filter.openSortDialog);
        document.getElementById('reportMenuTopN').addEventListener('click', OCA.Analytics.Filter.openTopNDialog);
        document.getElementById('reportMenuTimeAggregation').addEventListener('click', OCA.Analytics.Filter.openTimeAggregationDialog);
        document.getElementById('reportMenuChartOptions').addEventListener('click', OCA.Analytics.Filter.openChartOptionsDialog);
        document.getElementById('reportMenuTableOptions').addEventListener('click', OCA.Analytics.Filter.openTableOptionsDialog);

        document.getElementById('reportMenuAnalysis').addEventListener('click', OCA.Analytics.UI.showReportMenuAnalysis);
        document.getElementById('reportMenuRefresh').addEventListener('click', OCA.Analytics.UI.showReportMenuRefresh);
        document.getElementById('reportMenuTranslate').addEventListener('click', OCA.Analytics.UI.showReportMenuTranslate);
        document.getElementById('translateLanguage').addEventListener('change', OCA.Analytics.Translation.translate);
        document.getElementById('trendIcon').addEventListener('click', OCA.Analytics.Functions.trend);
        document.getElementById('disAggregateIcon').addEventListener('click', OCA.Analytics.Functions.disAggregate);
        document.getElementById('aggregateIcon').addEventListener('click', OCA.Analytics.Functions.aggregate);
        //document.getElementById('linearRegressionIcon').addEventListener('click', OCA.Analytics.Functions.linearRegression);
        document.getElementById('backIcon').addEventListener('click', OCA.Analytics.UI.showReportMenuMain);
        document.getElementById('backIcon2').addEventListener('click', OCA.Analytics.UI.showReportMenuMain);
        document.getElementById('backIcon3').addEventListener('click', OCA.Analytics.UI.showReportMenuMain);
        document.getElementById('reportMenuDownload').addEventListener('click', OCA.Analytics.UI.downloadChart);
        document.getElementById('chartLegend').addEventListener('click', OCA.Analytics.UI.toggleChartLegend);

        //document.getElementById('menuSearchBox').addEventListener('keypress', OCA.Analytics.UI.tableSearch);

        let refresh = document.getElementsByName('refresh');
        for (let i = 0; i < refresh.length; i++) {
            refresh[i].addEventListener('change', OCA.Analytics.Filter.Backend.saveRefresh);
        }
    },

    /**
     * Close the report menu overlay
     */
    hideReportMenu: function () {
        if (document.getElementById('reportMenu') !== null) {
            document.getElementById('reportMenu').classList.remove('open');
        }
    },

    /**
     * Toggle visibility of the report menu
     */
    toggleReportMenu: function () {
        document.getElementById('reportMenu').classList.toggle('open');
        document.getElementById('reportMenuMain').style.removeProperty('display');
        document.getElementById('reportMenuSubAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubRefresh').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubTranslate').style.setProperty('display', 'none', 'important');
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
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubAnalysis').style.removeProperty('display');
    },

    /**
     * Display refresh interval options
     */
    showReportMenuRefresh: function () {
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubRefresh').style.removeProperty('display');
    },

    /**
     * Display translation options
     */
    showReportMenuTranslate: function () {
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubTranslate').style.removeProperty('display');
    },

    /**
     * Return to the main report menu view
     */
    showReportMenuMain: function () {
        document.getElementById('reportMenuSubAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubRefresh').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubTranslate').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuMain').style.removeProperty('display');
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
            li.id = /\s/.test(item) ? `'${item}'` : item;;
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
        document.addEventListener('click', OCA.Analytics.UI.handleDropDownListClicked);

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
                        OCA.Analytics.UI.hideDropDownList();
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
            OCA.Analytics.UI.hideDropDownList();
        }
        // If the click is outside the list, hide the list
        else if (!isClickInside && !isClickOnInput) {
            OCA.Analytics.UI.hideDropDownList();
        }
    },

    /**
     * Remove dropdown list from the DOM
     */
    hideDropDownList: function () {
        // remove the global event listener again
        document.removeEventListener('click', OCA.Analytics.UI.handleDropDownListClicked);
        let dropDownList = document.getElementById('tmpList');
        let inputField = dropDownList.previousElementSibling;
        inputField.classList.remove('dropDownListParentOpen');
        dropDownList.remove();
    }
};

OCA.Analytics.Translation = {
    /**
     * Send current report text to translation service
     */
    translate: function () {
        let name = OCA.Analytics.currentReportData.options.name;
        let subheader = OCA.Analytics.currentReportData.options.subheader;
        let header = OCA.Analytics.currentReportData.header;
        let dimensions = JSON.stringify(OCA.Analytics.currentReportData.dimensions);
        let text = name + '**' + subheader + '**' + header + '**' + dimensions;

        let targetLanguage = document.getElementById('translateLanguage').value;
        targetLanguage = targetLanguage === 'EN' ? 'EN-US' : targetLanguage;

        let requestUrl = OC.generateUrl('ocs/v2.php/translation/translate');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                fromLanguage: null,
                text: text,
                toLanguage: targetLanguage
            })
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 400) {
                        OCA.Analytics.Notification.notification('error', t('analytics', 'Translation error. Possibly wrong ISO code?'));
                        return Promise.reject('400 Error');
                    }
                }
                return response.text();
            })
            .then(data => {
                let parser = new DOMParser();
                let xmlDoc = parser.parseFromString(data, "text/xml");

                let text = xmlDoc.getElementsByTagName("text")[0].childNodes[0].nodeValue;
                text = text.split('**');
                let from = xmlDoc.getElementsByTagName("from")[0].childNodes[0].nodeValue;

                OCA.Analytics.currentReportData.options.name = text[0];
                OCA.Analytics.currentReportData.options.subheader = text[1];
                OCA.Analytics.currentReportData.header = text[2].split(',');
                OCA.Analytics.currentReportData.dimensions = JSON.parse(text[3]);
                OCA.Analytics.UI.resetContentArea();
                OCA.Analytics.UI.buildReport();
            })
            .catch(error => {
                console.log('There has been a problem with your fetch operation: ', error);
            });

    },

    /**
     * Populate translation language selector
     */
    languages: function () {
        const elem = document.querySelector('#initial-state-analytics-translationLanguages');
        let translateLanguage = document.getElementById('translateLanguage');
        translateLanguage.innerHTML = '';

        let option = document.createElement('option');
        option.text = t('analytics', 'Choose language');
        option.value = '';
        translateLanguage.appendChild(option);

        const set = new Set();
        for (const item of JSON.parse(atob(elem.value))) {
            if (!set.has(item.from)) {
                set.add(item.from)
                let option = document.createElement('option');
                option.text = item.fromLabel;
                option.value = item.from;
                translateLanguage.appendChild(option);
            }
        }
    }
};

OCA.Analytics.Functions = {

    /**
     * Add a linear trend line to the visible datasets
     */
    trend: function () {
        OCA.Analytics.Visualization.showElement('chartLegendContainer');
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
        OCA.Analytics.Functions.aggregationFunction('aggregate');
        // const cumulativeSum = (sum => value => sum += value)(0);
        // datasets[0]['data'] = datasets[0]['data'].map(cumulativeSum)
    },

    /**
     * Reverse aggregation of a data series
     */
    disAggregate: function () {
        OCA.Analytics.Functions.aggregationFunction('disaggregate');
    },

    /**
     * Internal helper to (dis-)aggregate datasets
     */
    aggregationFunction: function (mode) {
        OCA.Analytics.Visualization.showElement('chartLegendContainer');
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
    /**
     * Load data source list and fill dropdown element
     */
    buildDropdown: async function (target, filter) {
        let optionsInit = document.createDocumentFragment();
        let optionInit = document.createElement('option');
        optionInit.value = '';
        optionInit.innerText = t('analytics', 'Loading');
        optionsInit.appendChild(optionInit);
        document.getElementById(target).innerHTML = '';
        document.getElementById(target).appendChild(optionsInit);

        let filterDatasource = '';
        if (filter) {
            filterDatasource = '/' + filter;
        }
        // need to offer an await here, because the data source options are important for subsequent functions in the sidebar
        let requestUrl = OC.generateUrl('apps/analytics/datasource' + filterDatasource);
        let response = await fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        });
        let data = await response.json();

        OCA.Analytics.datasources = data;
        let options = document.createDocumentFragment();
        let option = document.createElement('option');
        option.value = '';
        option.innerText = t('analytics', 'Please select');
        options.appendChild(option);

        let sortedOptions = OCA.Analytics.Datasource.sortOptions(data['datasources']);
        sortedOptions.forEach((entry) => {
            let value = entry[1];
            option = document.createElement('option');
            option.value = entry[0];
            option.innerText = value;
            options.appendChild(option);
        });
        document.getElementById(target).innerHTML = '';
        document.getElementById(target).appendChild(options);
        if (document.getElementById(target).dataset.typeId) {
            // in case the value was set in the sidebar before the dropdown was ready
            document.getElementById(target).value = document.getElementById(target).dataset.typeId;
        }
    },

    /**
     * Sort object keys alphabetically by value
     */
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

    /**
     * Generate configuration form for the selected data source
     */
    buildDatasourceRelatedForm: function (datasource) {
        let template = OCA.Analytics.datasources.options[datasource];
        let form = document.createElement('div');
        let insideSection = false;
        form.id = 'dataSourceOptions';

        if (typeof (template) === 'undefined') {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Data source not available anymore'));
            return form;
        }

        // create a hidden dummy for the data source type
        form.appendChild(OCA.Analytics.Datasource.buildOptionHidden('dataSourceType', datasource));

        for (let templateOption of template) {
            // loop all options of the data source template and create the input form

            // if it is a section, we don´t need the usual labels
            if (templateOption.type === 'section') {
                let tableRow = OCA.Analytics.Datasource.buildOptionsSection(templateOption);
                form.appendChild(tableRow);
                insideSection = true;
                continue;
            }

            // create the label column
            let tableRow = document.createElement('div');
            tableRow.style.display = insideSection === false ? 'table-row' : 'none';
            let label = document.createElement('div');
            label.style.display = 'table-cell';
            label.style.width = '100%';
            label.innerText = templateOption.name;
            // create the info icon column
            let infoColumn = document.createElement('div');
            infoColumn.style.display = 'table-cell';
            infoColumn.style.minWidth = '20px';

            //create the input fields
            let input = OCA.Analytics.Datasource.buildOptionsInput(templateOption);
            input.style.display = 'table-cell';
            if (templateOption.type) {
                if (templateOption.type === 'tf') {
                    input = OCA.Analytics.Datasource.buildOptionsSelect(templateOption);
                } else if (templateOption.type === 'filePicker') {
                    input.addEventListener('click', OCA.Analytics.Datasource.handleFilepicker);
                } else if (templateOption.type === 'columnPicker') {
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

    /**
     * Create hidden form field used for options
     */
    buildOptionHidden: function (id, value) {
        let dataSourceType = document.createElement('input');
        dataSourceType.hidden = true;
        dataSourceType.id = id;
        dataSourceType.innerText = value;
        return dataSourceType;
    },

    /**
     * Create input element based on template definition
     */
    buildOptionsInput: function (templateOption) {
        let type = templateOption.type && templateOption.type === 'longtext' ? 'textarea' : 'input';
        let input = document.createElement(type);
        input.style.display = 'inline-flex';
        input.classList.add('sidebarInput');
        input.placeholder = templateOption.placeholder;
        input.id = templateOption.id;
        input.dataset.type = templateOption.type;
        if (templateOption.type && templateOption.type === 'number') {
            input.type = 'number';
            input.min = '1';
        }
        if (templateOption.type) {
            input.autocomplete = 'off';
        }
        return input;
    },

    /**
     * Create collapsible section header
     */
    buildOptionsSection: function (templateOption) {
        let tableRow = document.createElement('div');
        tableRow.classList.add('sidebarHeaderClosed');
        let label = document.createElement('a');
        label.style.display = 'table-cell';
        label.style.width = '100%';
        label.innerText = templateOption.name;
        label.id = 'optionSectionHeader';
        label.addEventListener('click', OCA.Analytics.Datasource.showHiddenOptions);
        tableRow.appendChild(label);
        return tableRow;
    },

    /**
     * Create checkbox with editable label indicator
     */
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
        edit.classList.add('icon', 'icon-rename');
        edit.style.minHeight = '36px';

        let div = document.createElement('div');
        div.style.display = 'table-cell';
        div.appendChild(input);
        div.appendChild(edit);
        return div;
    },

    /**
     * Create select box from placeholder string
     */
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
            let index = selectOption.indexOf('-');
            let keyValue = [selectOption.substring(0, index), selectOption.substring(index + 1)];
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

    /**
     * Reveal additional options in the sidebar
     */
    showHiddenOptions: function () {
        const dataSourceOptionsDiv = document.getElementById("dataSourceOptions");
        const divElements = dataSourceOptionsDiv.children;

        for (let i = 0; i < divElements.length; i++) {
            if (divElements[i].tagName.toLowerCase() === "div") {
                divElements[i].style.display = "table-row";
            }
        }
        document.getElementById('optionSectionHeader').parentElement.remove();
    },

    /**
     * Request data preview and show column picker dialog
     */
    handleColumnPicker: function () {
        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Column Picker'),
            OCA.Analytics.Datasource.processColumnPickerDialog
        );

        // Get the values from all input fields but not the column picker
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

    /**
     * Render the column picker list from provided data
     */
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

        // create the list element
        const list = document.createElement("ul");
        list.id = 'sortable-list';
        list.style.display = 'inline-block';
        list.style.listStyle = 'none';
        list.style.margin = '0';
        list.style.padding = '0';
        list.style.width = "400px"
        items.forEach((item) => {
            list.appendChild(OCA.Analytics.Datasource.buildColumnPickerRow(item));
        });

        // create the button below to add a custom column
        const button = document.createElement('div');
        button.innerText = t('analytics', 'Add custom column');
        button.style.backgroundPosition = '5px center';
        button.style.paddingLeft = '25px';
        button.classList.add('icon-add', 'sidebarPointer');
        button.id = 'addColumnButton';
        button.addEventListener('click', OCA.Analytics.Datasource.addFixedColumn);

        // create span for reference to the old values
        const hint = document.createElement('span');
        hint.style.paddingLeft = '25px';
        hint.classList.add('userGuidance');
        hint.id = 'addColumnHint'
        hint.innerText

        const content = document.createDocumentFragment();
        content.appendChild(list);
        content.appendChild(document.createElement('br'));
        content.appendChild(document.createElement('br'));
        content.appendChild(button);
        content.appendChild(document.createElement('br'));
        content.appendChild(hint);

        OCA.Analytics.Notification.htmlDialogUpdate(
            content,
            t('analytics', 'Select the required columns.<br>Rearrange the sequence with drag & drop.<br>Remove all selections to reset.<br>Add custom columns including text variables for dates. See the {linkstart}Wiki{linkend}.')
                .replace('{linkstart}', '<a href="https://github.com/Rello/analytics/wiki/Filter,-chart-options-&-drilldown#text-variables" target="_blank">')
                .replace('{linkend}', '</a>')
        );
    },

    /**
     * Append a new custom column to the picker
     */
    addFixedColumn: function () {
        const currentColumns = document.querySelector('input[data-type="columnPicker"]').value;
        const preText = t('analytics', 'Previous Values: ');
        document.getElementById('addColumnHint').innerText = preText + currentColumns;

        const sortableList = document.querySelector("#analyticsDialogContent > #sortable-list");
        const item = {
            id: 'fixedColumn',
            name: t('analytics', 'Enter the fixed value:'),
            text: 'new',
            checked: true,
            contenteditable: true
        }
        sortableList.appendChild(OCA.Analytics.Datasource.buildColumnPickerRow(item));
    },

    /**
     * Build a draggable list row for the column picker
     */
    buildColumnPickerRow: function (item) {
        const li = document.createElement("li");
        li.style.display = 'flex';
        li.style.alignItems = 'center';
        li.style.margin = '5px';
        li.style.backgroundColor = 'var(--color-background-hover)';
        li.draggable = true;
        li.addEventListener("dragstart", OCA.Analytics.Notification.handleDragStart);
        li.addEventListener("dragover", OCA.Analytics.Notification.handleDragOver);
        li.addEventListener("drop", OCA.Analytics.Notification.handleDrop);

        const gripLines = document.createElement('div')
        gripLines.classList.add('icon-analytics-gripLines', 'sidebarPointer');

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
        item.contenteditable === true ? spanContent.contentEditable = 'true' : false;
        li.appendChild(gripLines);
        li.appendChild(checkbox);
        li.appendChild(span);
        li.appendChild(spanContent);
        return li;
    },

    /**
     * Collect chosen columns from the picker dialog
     */
    processColumnPickerDialog: function () {
        //get the list and sequence of the selected items
        const checkboxList = document.querySelectorAll('#sortable-list input[type="checkbox"]');
        const checkboxIds = [];

        checkboxList.forEach(function (checkbox) {
            let id = checkbox.id;

            // if it is a custom column, the entered text has to be used as id
            if (id === 'fixedColumn') {
                const li = checkbox.closest('li');
                const secondSpan = li.querySelector('span[contenteditable]');
                id = secondSpan.textContent;
            }
            if (checkbox.checked) {
                checkboxIds.push(id);
            }
        });
        document.querySelector('[data-type="columnPicker"]').value = checkboxIds;
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Open a file picker dialog for selecting a file path
     */
    handleFilepicker: function () {
        let type = parseInt(document.getElementById('dataSourceType').innerText);

        let mime;
        if (type === OCA.Analytics.TYPE_INTERNAL_FILE) {
            mime = ['text/csv', 'text/plain'];
        } else if (type === OCA.Analytics.TYPE_SPREADSHEET) {
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

    /**
     * Convert parameter object to query string
     */
    formatParams: function (params) {
        return "?" + Object
            .keys(params)
            .map(function (key) {
                return key + "=" + encodeURIComponent(params[key])
            })
            .join("&")
    },

    /**
     * Fetch report data from the backend
     */
    getData: function () {
        if (OCA.Analytics.currentXhrRequest) OCA.Analytics.currentXhrRequest.abort();
        OCA.Analytics.UI.resetContentArea();
        OCA.Analytics.Visualization.hideElement('analytics-intro');
        OCA.Analytics.Visualization.hideElement('analytics-content');
        OCA.Analytics.Visualization.showElement('analytics-loading');

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
                OCA.Analytics.Visualization.showElement('analytics-content');

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

                OCA.Analytics.UI.buildReport();

                let refresh = parseInt(OCA.Analytics.currentReportData.options.refresh);
                OCA.Analytics.Backend.startRefreshTimer(refresh);
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
