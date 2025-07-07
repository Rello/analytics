/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: OCP */
/** global: OC */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // register handlers for the navigation bar
    OCA.Analytics.registerHandler('create', 'dataset', function () {
        OCA.Analytics.Wizard.sildeArray = [
            ['', ''],
            ['wizardDatasetGeneral', OCA.Analytics.Dataset.Dataset.wizard],
        ];
        OCA.Analytics.Wizard.show();
    });

    OCA.Analytics.registerHandler('navigationClicked', 'dataset', function (event) {
        OCA.Analytics.Dataset.handleNavigationClicked(event);
    });

    OCA.Analytics.registerHandler('delete', 'dataset', function (event) {
        OCA.Analytics.Dataset.Dataset.handleDeleteButton(event);
    });

    OCA.Analytics.registerHandler('favoriteUpdate', 'dataset', function (id, isFavorite) {
        OCA.Analytics.Panorama.Dashboard.favoriteUpdate(id, isFavorite);
    });
})

OCA = OCA || {};

OCA.Analytics.Dataset = OCA.Analytics.Dataset || {};
Object.assign(OCA.Analytics.Dataset = {
    sidebar_tabs: {},

    handleNavigationClicked: function (evt) {
        OCA.Analytics.Sidebar.close();
        OCA.Analytics.Visualization.showContentByType('dataset');
        OCA.Analytics.Visualization.hideElement('menuBar');

        let navigationItem = evt.target;
        if (navigationItem.dataset.id === undefined) navigationItem = evt.target.closest('div');
        OCA.Analytics.currentDataset = navigationItem.dataset.id;
        OCA.Analytics.currentDatasetType = navigationItem.dataset.type;

        OCA.Analytics.Dataset.constructTabs();

        document.getElementById('tabHeaderDataset').classList.add('selected');
        document.querySelector('.tabHeader.selected').click();
    },

    registerSidebarTab: function (tab) {
        const id = tab.id;
        this.sidebar_tabs[id] = tab;
    },

    constructTabs: function () {
        document.querySelector('.datasetTabHeaders').innerHTML = '';
        document.querySelector('.datasetTabContainer').innerHTML = '';

        OCA.Analytics.Dataset.registerSidebarTab({
            id: 'tabHeaderDataset',
            class: 'tabContainerDataset',
            tabindex: '1',
            name: t('analytics', 'Settings'),
            action: OCA.Analytics.Dataset.Dataset.tabContainerDataset,
        });

        OCA.Analytics.Dataset.registerSidebarTab({
            id: 'tabHeaderData',
            class: 'tabContainerData',
            tabindex: '2',
            name: t('analytics', 'Data'),
            action: OCA.Analytics.Sidebar.Data.tabContainerData,
        });

        OCA.Analytics.Dataset.registerSidebarTab({
            id: 'tabHeaderDataload',
            class: 'tabContainerDataload',
            tabindex: '2',
            name: t('analytics', 'Automation'),
            action: OCA.Analytics.Dataset.Dataload.tabContainerDataload,
        });

        let items = _.map(OCA.Analytics.Dataset.sidebar_tabs, function (item) {
            return item;
        });
        items.sort(OCA.Analytics.Dataset.sortByName);

        let tabHeaders = document.querySelector('.datasetTabHeaders');
        for (let tab in items) {
            let li = document.createElement('li');
            li.classList.add('tabHeader');
            li.setAttribute('id', items[tab].id);
            li.setAttribute('tabindex', items[tab].tabindex);
            let atag = document.createElement('a');
            atag.textContent = items[tab].name;
            atag.setAttribute('title', items[tab].name);
            li.append(atag);
            tabHeaders.appendChild(li);

            let div = document.createElement('div');
            div.classList.add('tab', items[tab].class);
            div.setAttribute('id', items[tab].class);
            let tabContainer = document.querySelector('.datasetTabContainer');
            tabContainer.appendChild(div);
            document.getElementById(items[tab].id).addEventListener('click', items[tab].action);
        }
    },

    resetView: function () {
        document.querySelector('.tabHeader.selected').classList.remove('selected');
        let tabs = document.querySelectorAll('.datasetTabContainer .tab');
        for (let i = 0; i < tabs.length; i++) {
            tabs[i].hidden = true;
            tabs[i].innerHTML = '';
        }

    },

    sortByName: function (a, b) {
        const aName = a.tabindex;
        const bName = b.tabindex;
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },
});

OCA.Analytics.Dataset.Dataload = OCA.Analytics.Dataset.Dataload || {};
Object.assign(OCA.Analytics.Dataset.Dataload = {
    dataloadArray: [],

    tabContainerDataload: function () {
        const datasetId = OCA.Analytics.currentDataset;

        OCA.Analytics.Dataset.resetView();
        document.getElementById('tabHeaderDataload').classList.add('selected');
        OCA.Analytics.Visualization.showElement('tabContainerDataload');
        document.getElementById('tabContainerDataload').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        if (parseInt(OCA.Analytics.currentDatasetType) !== OCA.Analytics.TYPE_INTERNAL_DB) {
            let message = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'Data maintenance is not possible for this type of report') + '</p></div>';
            document.getElementById('tabContainerDataload').innerHTML = message;
            return;
        }

        let url = OC.generateUrl('apps/analytics/dataload');
        let params = new URLSearchParams({datasetId: datasetId});
        let requestUrl = `${url}?${params}`;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                // clone the DOM template
                let table = document.importNode(document.getElementById('templateDataload').content, true);
                table.id = 'tableDataload';
                document.getElementById('tabContainerDataload').innerHTML = '';
                document.getElementById('tabContainerDataload').appendChild(table);
                document.getElementById('createDataloadButton').addEventListener('click', OCA.Analytics.Dataset.Dataload.handleCreateButton);
                document.getElementById('createDataDeletionButton').addEventListener('click', OCA.Analytics.Dataset.Dataload.handleCreateDataDeletionButton);
                document.getElementById('dataLoadList').innerHTML = '';
                document.getElementById('dataDeleteList').innerHTML = '';

                OCA.Analytics.Dataset.Dataload.dataloadArray = [];

                // list all available data loads for dataset
                for (let dataload of data['dataloads']) {
                    if (parseInt(dataload['datasource']) === 0) {
                        document.getElementById('dataDeleteList').appendChild(OCA.Analytics.Dataset.Dataload.buildDataloadRow(dataload));
                    } else {
                        document.getElementById('dataLoadList').appendChild(OCA.Analytics.Dataset.Dataload.buildDataloadRow(dataload));
                    }
                    // keys need to be int; some instances deliver strings from the backend
                    dataload['dataset'] = parseInt(dataload['dataset']);
                    dataload['datasource'] = parseInt(dataload['datasource']);
                    dataload['id'] = parseInt(dataload['id']);
                    // store reulable array
                    OCA.Analytics.Dataset.Dataload.dataloadArray.push(dataload);
                }
                if (OCA.Analytics.Dataset.Dataload.dataloadArray.length === 0) {
                    document.getElementById('dataLoadList').innerHTML = '<span class="userGuidance">'
                        + t('analytics', 'Choose a data source from the dropdown and press "+"')
                        + '</span><br>';
                } else {
                    document.getElementById('dataloadDetailItems').innerHTML = '<span class="userGuidance">'
                        + t('analytics', 'Choose a data load from the list to change its settings')
                        + '</span>';
                }

                let dataLoadCreated = document.getElementById('app-sidebar').dataset.dataLoadCreated;
                if (dataLoadCreated !== 'null' && typeof (dataLoadCreated) !== 'undefined') {
                    OCA.Analytics.Dataset.Dataload.buildDataloadOptions(null, dataLoadCreated);
                }
                document.getElementById('app-sidebar').dataset.dataLoadCreated = null;

                // list all available data sources
                OCA.Analytics.Datasource.buildDropdown('datasourceSelect');

                // write dimension structure to data source array [0] in case it is required for a deletion job later
                //OCA.Analytics.Dataset.Dataload.updateDatasourceDeletionOption();
            });
    },

    updateDatasourceDeletionOption: function () {
        // write dimension structure to data source array [0] in case it is required for a deletion job later

        // fill Options dropdown
        let optionSelectOptions = '';
        for (let i = 0; i < Object.keys(OCA.Analytics.Filter.optionTextsArray).length; i++) {
            optionSelectOptions = optionSelectOptions + Object.keys(OCA.Analytics.Filter.optionTextsArray)[i] + '-' + Object.values(OCA.Analytics.Filter.optionTextsArray)[i] + '/';
        }

        const datasetId = OCA.Analytics.currentDataset;
        let dataset = OCA.Analytics.datasets.find(x => parseInt(x.id) === parseInt(datasetId));

        let datasetOptions = [];
        datasetOptions.push({
            id: 'filterDimension',
            name: t('analytics', 'Filter by'),
            type: 'tf',
            placeholder: 'dimension1-' + dataset['dimension1'] + '/' + 'dimension2-' + dataset['dimension2']
        });
        datasetOptions.push({
            id: 'filterOption',
            name: t('analytics', 'Operator'),
            type: 'tf',
            placeholder: optionSelectOptions
        });
        datasetOptions.push({id: 'filterValue', name: t('analytics', 'Value'), placeholder: ''});
        OCA.Analytics.datasources.options[0] = datasetOptions;
    },

    buildDataloadRow: function (dataload) {
        let item = document.createElement('div');
        item.classList.add('dataloadItem');

        let typeINT = parseInt(dataload['datasource']);
        let typeIcon;
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE || typeINT === OCA.Analytics.TYPE_SPREADSHEET) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else {
            typeIcon = 'icon-external';
        }
        let a = document.createElement('a');
        a.classList.add(typeIcon);
        a.innerText = dataload['name'];
        a.dataset.dataloadId = parseInt(dataload['id']);
        a.dataset.datasourceId = parseInt(dataload['datasource']);
        a.addEventListener('click', OCA.Analytics.Dataset.Dataload.handleDataloadSelect);
        item.appendChild(a);
        return item;
    },

    handleDataloadSelect: function (evt) {
        OCA.Analytics.Dataset.Dataload.buildDataloadOptions(evt);
    },

    buildDataloadOptions: function (evt, id = null) {
        // write dimension structure to data source array [0] in case it is required for a deletion job later
        OCA.Analytics.Dataset.Dataload.updateDatasourceDeletionOption();

        let dataload;
        if (id === null) {
            dataload = OCA.Analytics.Dataset.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(evt.target.dataset.dataloadId));
        } else {
            dataload = OCA.Analytics.Dataset.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(id));
        }

        document.getElementById('dataloadDetail').dataset.dataloadId = dataload['id'];
        document.getElementById('dataloadName').value = dataload['name'];
        document.getElementById('dataloadType').innerText = OCA.Analytics.datasources.datasources[dataload['datasource']];
        OCA.Analytics.Visualization.showElement('dataloadDetailHeader');
        OCA.Analytics.Visualization.showElement('dataloadDetailButtons');
        OCA.Analytics.Visualization.showElement('dataloadDetailDelete');
        document.getElementById('dataloadUpdateButton').addEventListener('click', OCA.Analytics.Dataset.Dataload.handleUpdateButton);
        document.getElementById('dataloadDeleteButton').addEventListener('click', OCA.Analytics.Dataset.Dataload.handleDeleteButton);
        document.getElementById('dataloadCopyButton').addEventListener('click', OCA.Analytics.Dataset.Dataload.handleCopyButton);
        document.getElementById('dataloadSchedule').value = dataload['schedule'];
        document.getElementById('dataloadSchedule').addEventListener('change', OCA.Analytics.Dataset.Dataload.updateDataload);
        document.getElementById('dataloadOCC').innerText = 'occ analytics:load ' + dataload['id'];

        // get all the options for a data source
        document.getElementById('dataloadDetailItems').innerHTML = '';
        document.getElementById('dataloadDetailItems').appendChild(OCA.Analytics.Datasource.buildDatasourceRelatedForm(dataload['datasource']));

        // set the options for a data source
        let fieldValues = JSON.parse(dataload['option']);
        for (let fieldValue in fieldValues) {
            document.getElementById(fieldValue) ? document.getElementById(fieldValue).value = OCA.Analytics.Dataset.Dataload.decodeEscapedHtml(fieldValues[fieldValue]) : null;
        }

        if (dataload['datasource'] === 0) {
            // this is a deletion job
            OCA.Analytics.Visualization.hideElement('dataloadDetailDelete');
        }

        OCA.Analytics.Visualization.showElement('dataloadRun');
        document.getElementById('dataloadExecuteButton').addEventListener('click', OCA.Analytics.Dataset.Dataload.handleExecuteButton);
    },

    handleCreateButton: function () {
        if (document.getElementById('datasourceSelect').value === '') {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Please select data source'));
        } else {
            OCA.Analytics.Dataset.Dataload.createDataload();
        }
    },

    createDataload: function () {
        let requestUrl = OC.generateUrl('apps/analytics/dataload');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                datasetId: parseInt(OCA.Analytics.currentDataset),
                datasourceId: document.getElementById('datasourceSelect').value,
            })
        })
            .then(response => response.json())
            .then(data => {
                document.getElementById('app-sidebar').dataset.dataLoadCreated = data.id;
                document.querySelector('.tabHeader.selected').click();
            });
    },

    handleCreateDataDeletionButton: function () {
        OCA.Analytics.Dataset.Dataload.createDataDelete();
    },

    createDataDelete: function () {
        let requestUrl = OC.generateUrl('apps/analytics/dataload');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                datasetId: parseInt(OCA.Analytics.currentDataset),
                datasourceId: 0,
            })
        })
            .then(response => response.json())
            .then(data => {
                document.getElementById('app-sidebar').dataset.dataLoadCreated = data.id;
                document.querySelector('.tabHeader.selected').click();
            });
    },

    handleUpdateButton: function () {
        OCA.Analytics.Dataset.Dataload.updateDataload();
    },

    updateDataload: function () {
        const dataloadId = parseInt(document.getElementById('dataloadDetail').dataset.dataloadId);
        let option = {};

        // loop all dynamic options of the selected data source
        let inputFields = document.querySelectorAll('#dataloadDetailItems input, #dataloadDetailItems select, #dataloadDetailItems textarea, #dataloadDetailDelete select');
        for (let inputField of inputFields) {
            option[inputField['id']] = inputField['value'];
        }
        option = JSON.stringify(option);

        let requestUrl = OC.generateUrl('apps/analytics/dataload/') + dataloadId;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                name: document.getElementById('dataloadName').value,
                schedule: document.getElementById('dataloadSchedule').value,
                option: option,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
                OCA.Analytics.Dataset.Dataload.dataloadArray.find(x => x.id === dataloadId)['schedule'] = document.getElementById('dataloadSchedule').value;
                OCA.Analytics.Dataset.Dataload.dataloadArray.find(x => x.id === dataloadId)['name'] = document.getElementById('dataloadName').value;
                OCA.Analytics.Dataset.Dataload.dataloadArray.find(x => x.id === dataloadId)['option'] = option;
                document.querySelector('[data-dataload-id="' + dataloadId + '"]').innerHTML = document.getElementById('dataloadName').value;
            });
    },

    handleCopyButton: function () {
        OCA.Analytics.Dataset.Dataload.copyDataload();
    },

    copyDataload: function () {
        let requestUrl = OC.generateUrl('apps/analytics/dataload/copy');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                dataloadId: document.getElementById('dataloadDetail').dataset.dataloadId,
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                OCA.Analytics.Notification.notification('success', t('analytics', 'Data load copied'));
                document.querySelector('.tabHeader.selected').click();
            })
            .catch(error => {
                // stop if the file link is missing
                OCA.Analytics.Notification.notification('error', t('analytics', 'Parameter missing'));
                OCA.Analytics.Notification.dialogClose();
            });
    },

    handleDeleteButton: function () {
        OCA.Analytics.Notification.confirm(
            t('analytics', 'Delete data load'),
            t('analytics', 'Are you sure?'),
            function (e) {
                OCA.Analytics.Dataset.Dataload.deleteDataload();
                OCA.Analytics.Notification.dialogClose();
            }
        );
    },

    deleteDataload: function () {
        let requestUrl = OC.generateUrl('apps/analytics/dataload/') + document.getElementById('dataloadDetail').dataset.dataloadId;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
    },

    handleExecuteButton: function () {
        OCA.Analytics.Dataset.Dataload.executeDataload();
    },

    executeDataload: function () {
        let mode;
        if (document.getElementById('testrunCheckbox').checked) {
            mode = 'simulate';
            OCA.Analytics.Notification.htmlDialogInitiate(
                t('analytics', 'Data source simulation'),
                OCA.Analytics.Notification.dialogClose,
            );
        } else {
            mode = 'execute';
            OCA.Analytics.Notification.notification('success', t('analytics', 'Load started'));
        }

        let requestUrl = OC.generateUrl('apps/analytics/dataload/') + mode;
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                dataloadId: document.getElementById('dataloadDetail').dataset.dataloadId,
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (mode === 'simulate') {
                    let dialogContent;
                    let errorData = '';
                    if (parseInt(data.error) === 0) {
                        dialogContent = document.createElement('div');
                        dialogContent.id = 'simulationData';
                        dialogContent.innerHTML = JSON.stringify(data.data);
                    } else {
                        dialogContent = document.createElement('div');
                        dialogContent.innerHTML = '<textarea style="width: 500px;" cols="200" rows="15">'
                            + new Option(JSON.stringify(data.rawdata)).innerHTML + '</textarea>';
                        errorData = 'Error: ' + data.error;
                    }
                    OCA.Analytics.Notification.htmlDialogUpdate(
                        dialogContent,
                        errorData,
                    );

                } else {
                    let messageType;
                    if (parseInt(data.error) === 0) {
                        messageType = 'success';
                    } else {
                        messageType = 'error';
                    }
                    OCA.Analytics.Notification.notification(messageType, data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' ' + t('analytics', 'records updated') + ', ' + data.error + ' ' + t('analytics', 'errors') + ', ' + data.delete + ' ' + t('analytics', 'deletions'));
                }
            })
            .catch(error => {
                // stop if the file link is missing
                OCA.Analytics.Notification.notification('error', t('analytics', 'Parameter missing'));
                OCA.Analytics.Notification.dialogClose();
            });
    },

    handleFilepicker: function () {
        let dataloadId = document.getElementById('dataloadDetail').dataset.dataloadId;
        let type = OCA.Analytics.Dataset.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(dataloadId))['datasource'];

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

    decodeEscapedHtml: function (text) {
        let map =
            {
                '&amp;': '&',
                '&lt;': '<',
                '&gt;': '>',
                '&quot;': '"',
                '&#039;': "'"
            };
        return text.replace(/&amp;|&lt;|&gt;|&quot;|&#039;/g, function (m) {
            return map[m];
        });
    },

});

OCA.Analytics.Dataset.Dataset = OCA.Analytics.Dataset.Dataset || {};
Object.assign(OCA.Analytics.Dataset.Dataset = {
    dataloadArray: [],

    tabContainerDataset: function () {
        const datasetId = OCA.Analytics.currentDataset;

        OCA.Analytics.Dataset.resetView();
        document.getElementById('tabHeaderDataset').classList.add('selected');
        OCA.Analytics.Visualization.showElement('tabContainerDataset');
        document.getElementById('tabContainerDataset').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        let requestUrl = OC.generateUrl('apps/analytics/dataset/') + datasetId;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                let table;
                if (data !== false) {
                    // clone the DOM template
                    table = document.importNode(document.getElementById('templateDataset').content, true);
                    table.id = 'sidebarDataset';

                    document.getElementById('tabContainerDataset').innerHTML = '';
                    document.getElementById('tabContainerDataset').appendChild(table);

                    document.getElementById('sidebarDatasetName').value = data['name'];
                    document.getElementById('sidebarDatasetSubheader').value = data['subheader'];
                    document.getElementById('sidebarDatasetDimension1').value = data['dimension1'];
                    document.getElementById('sidebarDatasetDimension2').value = data['dimension2'];
                    document.getElementById('sidebarDatasetValue').value = data['value'];
                    document.getElementById('sidebarDatasetAiIndex').checked = parseInt(data['ai_index']) === 1;

                    document.getElementById('sidebarDatasetDeleteButton').addEventListener('click', OCA.Analytics.Dataset.Dataset.handleDeleteButton);
                    document.getElementById('sidebarDatasetUpdateButton').addEventListener('click', OCA.Analytics.Dataset.Dataset.handleUpdateButton);
                    document.getElementById('sidebarDatasetAiUpdateButton').addEventListener('click', OCA.Analytics.Dataset.Dataset.handleAiUpdateButton);

                    if (OCA.Analytics.Core.getInitialState('contextChatAvailable') === true) {
                        document.getElementById('datasetAiSectionDisabled').remove();
                    }

                    OCA.Analytics.Sidebar.assignSectionHeaderClickEvents();
                    //document.getElementById('sidebarDatasetExportButton').addEventListener('click', OCA.Analytics.Dataset.Dataset.handleExportButton);

                    // get status information like report and data count
                    OCA.Analytics.Dataset.Dataset.getStatus();
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No changes possible') + '</p></div>';
                    document.getElementById('tabContainerDataset').innerHTML = table;
                }
            });
    },


    getStatus: function () {
        // get status information like report and data count

        let requestUrl = OC.generateUrl('apps/analytics/dataset/') + OCA.Analytics.currentDataset + '/status';
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                document.getElementById('sidebarDatasetStatusRecords').innerText = parseInt(data['data']['count']).toLocaleString();

                let text = '';
                for (let report of data['reports']) {
                    text = text + '- ' + report['name'] + '<br>';
                }
                if (text === '') text = t('analytics', 'This dataset is not used!');
                document.getElementById('sidebarDatasetStatusReports').innerHTML = text;
            });
    },

    handleDeleteButton: function (evt) {
        let id = evt.target.parentNode.dataset.id;
        if (id === undefined) id = OCA.Analytics.currentDataset;

        OCA.Analytics.Notification.confirm(
            t('analytics', 'Delete'),
            t('analytics', 'Are you sure?') + ' ' + t('analytics', 'All data including all reports will be deleted!'),
            function (e) {
                OCA.Analytics.Dataset.Dataset.delete(id);
                OCA.Analytics.Notification.dialogClose();
            }
        );
    },

    handleUpdateButton: function () {
        OCA.Analytics.Dataset.Dataset.update();
    },

    handleAiUpdateButton: function () {
        OCA.Analytics.Dataset.Dataset.aiUpdate();
    },

    handleExportButton: function () {
        OCA.Analytics.Dataset.Dataset.export();
    },

    wizard: function () {
        document.getElementById('wizardNewCreate').addEventListener('click', OCA.Analytics.Dataset.Dataset.create);
        document.getElementById('wizardNewCancel').addEventListener('click', OCA.Analytics.Wizard.close);
    },

    create: function () {
        let name = document.getElementById('wizardDatasetName').value;
        let dimension1 = document.getElementById('wizardDatasetDimension1').value;
        let dimension2 = document.getElementById('wizardDatasetDimension2').value;
        let value = document.getElementById('wizardDatasetValue').value;
        let error;

        if (name === '') {
            error = t('analytics', 'The dataset name is missing');
            OCA.Analytics.Notification.notification('error', error);
            return;
        }

        let requestUrl = OC.generateUrl('apps/analytics/dataset');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                name: name,
                dimension1: dimension1,
                dimension2: dimension2,
                value: value
            })
        })
            .then(response => response.json())
            .then(id => {
                return fetch(OC.generateUrl('apps/analytics/dataset/') + id, {
                    method: 'GET',
                    headers: OCA.Analytics.headers(),
                });
            })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Wizard.close();
                data.item_type = 'dataset';
                OCA.Analytics.Navigation.addNavigationItem(data);
                const anchor = document.querySelector('#navigationDatasets a[data-id="' + data.id + '"][data-item_type="dataset"]');
                anchor?.click();
            });
    },

    delete: function (reportId) {
        let requestUrl = OC.generateUrl('apps/analytics/dataset/') + reportId;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.removeNavigationItem(reportId, 'dataset');
                OCA.Analytics.Navigation.handleOverviewButton();
            });
    },

    export: function () {
        window.open(OC.generateUrl('apps/analytics/report/dataset/') + OCA.Analytics.currentDataset, '_blank')
    },

    aiUpdate: function () {
        const reportId = parseInt(OCA.Analytics.currentDataset);
        let requestUrl = OC.generateUrl('apps/analytics/dataset/') + reportId + '/provider';
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Notification.notification('success', t('analytics', 'Done'));
            });
    },

    update: function () {
        const reportId = parseInt(OCA.Analytics.currentDataset);
        const button = document.getElementById('sidebarDatasetUpdateButton');
        button.classList.add('loading');
        button.disabled = true;

        let requestUrl = OC.generateUrl('apps/analytics/dataset/') + reportId;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                name: document.getElementById('sidebarDatasetName').value,
                subheader: document.getElementById('sidebarDatasetSubheader').value,
                dimension1: document.getElementById('sidebarDatasetDimension1').value,
                dimension2: document.getElementById('sidebarDatasetDimension2').value,
                value: document.getElementById('sidebarDatasetValue').value,
                aiIndex: document.getElementById('sidebarDatasetAiIndex').checked ? 1 : null,
            })
        })
            .then(response => response.json())
            .then(data => {
                button.classList.remove('loading');
                button.disabled = false;

                OCA.Analytics.Navigation.init(reportId);
                OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
            });
    },
});