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

OCA.Analytics = Object.assign({}, OCA.Analytics, {
    TYPE_GROUP: 0,
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
    tableObject: [],
    // flexible mapping depending on type required by the used chart library
    chartTypeMapping: {
        'datetime': 'line',
        'column': 'bar',
        'columnSt': 'bar', // map stacked type also to base type; needed in filter
        'columnSt100': 'bar', // map stacked type also to base type; needed in filter
        'area': 'line',
        'line': 'line',
        'doughnut': 'doughnut'
    },
    datasources: [],
    datasets: [],
    reports: [],
    unsavedFilters: null,
    refreshTimer: null,
    currentXhrRequest: null,
    translationAvailable: false,

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
    init: function () {

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

    buildReport: function () {
        document.getElementById('reportHeader').innerText = OCA.Analytics.currentReportData.options.name;
        if (OCA.Analytics.currentReportData.options.subheader !== '') {
            document.getElementById('reportSubHeader').innerText = OCA.Analytics.currentReportData.options.subheader;
            OCA.Analytics.Visualization.showElement('reportSubHeader');
        }

        document.title = OCA.Analytics.currentReportData.options.name + ' - ' + OCA.Analytics.initialDocumentTitle;
        if (OCA.Analytics.currentReportData.status !== 'nodata' && parseInt(OCA.Analytics.currentReportData.error) === 0) {

            // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
            OCA.Analytics.currentReportData = OCA.Analytics.Visualization.sortDates(OCA.Analytics.currentReportData);

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
                'xAxes': {
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

    toggleZoomResetButton: function () {
        OCA.Analytics.Visualization.showElement('chartZoomReset');
    },

    handleZoomResetButton: function () {
        OCA.Analytics.chartObject.resetZoom();
        OCA.Analytics.Visualization.hideElement('chartZoomReset');
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
            OCA.Analytics.Visualization.showElement('analytics-intro');
            document.getElementById('app-sidebar').classList.add('disappear');
        } else {
            let key = Object.keys(OCA.Analytics.tableObject)[0];
                if (key !== undefined) {
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
            document.getElementById('reportMenuDrilldown').disabled = false;
            document.getElementById('reportMenuDownload').disabled = false;
            document.getElementById('reportMenuAnalysis').disabled = false;
            document.getElementById('reportMenuTranslate').disabled = false;
        }
    },

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
            document.getElementById('reportMenuResetTableState').disabled = true;
            document.getElementById('reportMenuTableOptions').disabled = true;
        } else {
            document.getElementById('reportMenuResetTableState').disabled = false;
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
        document.getElementById('reportMenuSave').addEventListener('click', OCA.Analytics.Filter.Backend.newReport);
        document.getElementById('reportMenuDrilldown').addEventListener('click', OCA.Analytics.Filter.openDrilldownDialog);
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
        document.getElementById('reportMenuResetTableState').addEventListener('click', OCA.Analytics.UI.resetTableState);
        document.getElementById('chartLegend').addEventListener('click', OCA.Analytics.UI.toggleChartLegend);

        //document.getElementById('menuSearchBox').addEventListener('keypress', OCA.Analytics.UI.tableSearch);

        let refresh = document.getElementsByName('refresh');
        for (let i = 0; i < refresh.length; i++) {
            refresh[i].addEventListener('change', OCA.Analytics.Filter.Backend.saveRefresh);
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
        document.getElementById('reportMenuSubAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubRefresh').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubTranslate').style.setProperty('display', 'none', 'important');
    },

    toggleTableMenu: function () {
        document.getElementById('tableMenu').classList.toggle('open');
        document.getElementById('tableMenuMain').style.removeProperty('display');
    },

    showReportMenuAnalysis: function () {
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubAnalysis').style.removeProperty('display');
    },

    showReportMenuRefresh: function () {
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubRefresh').style.removeProperty('display');
    },

    showReportMenuTranslate: function () {
        document.getElementById('reportMenuMain').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubTranslate').style.removeProperty('display');
    },

    showReportMenuMain: function () {
        document.getElementById('reportMenuSubAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubRefresh').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuSubTranslate').style.setProperty('display', 'none', 'important');
        document.getElementById('reportMenuMain').style.removeProperty('display');
    },

    tableSearch: function () {
        OCA.Analytics.tableObject.search(this.value).draw();
    },

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

        // take over the class from the input field to ensure same size
        for (let className of inputField.classList) {
            ul.classList.add(className);
        }

        let listCount = 0;
        let listCountMax = 4;
        for (let item of listValues) {
            let li = document.createElement('li');
            li.id = item;
            li.innerText = item;
            listCount > listCountMax ? li.style.display = 'none' : li.style.display = '';
            ul.appendChild(li);
            listCount++;
        }

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
                    } else {
                        li[i].style.display = "none";
                    }
                }
            }
        });
    },

    handleDropDownListClicked: function (event) {
        let dropDownList = document.getElementById('tmpList');
        let inputField = dropDownList.previousElementSibling;
        let isClickInside = dropDownList.contains(event.target);
        let isClickOnInput = inputField === event.target;

        // If the click is inside the list and the target is an LI
        if (isClickInside && event.target.tagName === 'LI') {
            inputField.value = event.target.textContent;
            OCA.Analytics.UI.hideDropDownList();
        }
        // If the click is outside the list, hide the list
        else if (!isClickInside && !isClickOnInput) {
            OCA.Analytics.UI.hideDropDownList();
        }
    },

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
    buildDropdown: async function (target) {
        let optionsInit = document.createDocumentFragment();
        let optionInit = document.createElement('option');
        optionInit.value = '';
        optionInit.innerText = t('analytics', 'Loading');
        optionsInit.appendChild(optionInit);
        document.getElementById(target).innerHTML = '';
        document.getElementById(target).appendChild(optionsInit);

        // need to offer an await here, because the datasourceOptions is important for subsequent functions in the sidebar
        let requestUrl = OC.generateUrl('apps/analytics/datasource');
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
            // in case the value was set in the sidebar befor the dropdown was ready
            document.getElementById(target).value = document.getElementById(target).dataset.typeId;
        }
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
            // loop all options of the datasource template and create the input form

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

    buildOptionHidden: function (id, value) {
        let dataSourceType = document.createElement('input');
        dataSourceType.hidden = true;
        dataSourceType.id = id;
        dataSourceType.innerText = value;
        return dataSourceType;
    },

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
                .replace('{linkstart}', '<a href="https://github.com/Rello/analytics/wiki/Filter,-chart-options-&-drilldown##text-variables" target="_blank">')
                .replace('{linkend}', '</a>')
        );
    },

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
                ajaxData.dataoptions = OCA.Analytics.currentReportData.options.dataoptions;
            }
            if (typeof (OCA.Analytics.currentReportData.options.chartoptions) !== 'undefined') {
                ajaxData.chartoptions = OCA.Analytics.currentReportData.options.chartoptions;
            }
            if (typeof (OCA.Analytics.currentReportData.options.tableoptions) !== 'undefined') {
                ajaxData.tableoptions = OCA.Analytics.currentReportData.options.tableoptions;
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
                    OCA.Analytics.Visualization.showElement('reportSubHeader');
                }

                OCA.Analytics.UI.buildReport();

                let refresh = parseInt(OCA.Analytics.currentReportData.options.refresh);
                OCA.Analytics.Backend.startRefreshTimer(refresh);
            }
        };
        xhr.send();
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
    OCA.Analytics.Visualization.hideElement('analytics-warning');

    if (document.getElementById('sharingToken').value === '') {
        OCA.Analytics.Visualization.showElement('analytics-intro');
        OCA.Analytics.Core.init();
        if (!OCA.Analytics.isAdvanced) {
            OCA.Analytics.UI.reportOptionsEventlisteners();
            document.getElementById("infoBoxReport").addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
            document.getElementById("infoBoxIntro").addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
            document.getElementById("infoBoxWiki").addEventListener('click', OCA.Analytics.Core.openWiki);
        }
    } else {
        OCA.Analytics.Backend.getData();
    }

    OCA.Analytics.translationAvailable = OCA.Analytics.Core.getInitialState('translationAvailable');
    window.addEventListener("beforeprint", function () {
        //document.getElementById('chartContainer').style.height = document.getElementById('myChart').style.height;
    });
});
