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
'use strict';

/**
 * @namespace OCA.Analytics.Advanced
 */
OCA.Analytics.Advanced = {
    sidebar_tabs: {},

    showSidebar: function (evt) {
        let navigationItem = evt.target;
        if (navigationItem.dataset.id === undefined) navigationItem = evt.target.closest('div');
        const datasetId = navigationItem.dataset.id;
        const datasetType = navigationItem.dataset.type;
        let appsidebar = document.getElementById('app-sidebar');

        if (appsidebar.dataset.id === datasetId && document.getElementById('advanced').value === 'false') {
            OCA.Analytics.Advanced.hideSidebar();
        } else {
            document.getElementById('sidebarTitle').innerText = navigationItem.dataset.name;
            OCA.Analytics.Advanced.constructTabs(datasetType);

            if (document.getElementById('advanced').value === 'false') {
                if (appsidebar.dataset.id === '') {
                    $('#sidebarClose').on('click', OCA.Analytics.Advanced.hideSidebar);
                    OC.Apps.showAppSidebar();
                }
            } else {
                OCA.Analytics.UI.hideElement('analytics-intro');
                OCA.Analytics.UI.showElement('analytics-content');
                OC.Apps.showAppSidebar();
            }
            appsidebar.dataset.id = datasetId;
            appsidebar.dataset.type = datasetType;

            document.getElementById('tabHeaderDataset').classList.add('selected');
            document.querySelector('.tabHeader.selected').click();
        }
    },

    registerSidebarTab: function (tab) {
        const id = tab.id;
        this.sidebar_tabs[id] = tab;
    },

    constructTabs: function () {

        document.querySelector('.tabHeaders').innerHTML = '';
        document.querySelector('.tabsContainer').innerHTML = '';

        OCA.Analytics.Advanced.registerSidebarTab({
            id: 'tabHeaderDataset',
            class: 'tabContainerDataset',
            tabindex: '1',
            name: t('analytics', 'Dataset'),
            action: OCA.Analytics.Advanced.Dataset.tabContainerDataset,
        });

        OCA.Analytics.Advanced.registerSidebarTab({
            id: 'tabHeaderData',
            class: 'tabContainerData',
            tabindex: '2',
            name: t('analytics', 'Data'),
            action: OCA.Analytics.Sidebar.Data.tabContainerData,
        });

        OCA.Analytics.Advanced.registerSidebarTab({
            id: 'tabHeaderDataload',
            class: 'tabContainerDataload',
            tabindex: '2',
            name: t('analytics', 'Automation'),
            action: OCA.Analytics.Advanced.Dataload.tabContainerDataload,
        });

        let items = _.map(OCA.Analytics.Advanced.sidebar_tabs, function (item) {
            return item;
        });
        items.sort(OCA.Analytics.Advanced.sortByName);

        for (let tab in items) {
            let li = $('<li/>').addClass('tabHeader')
                .attr({
                    'id': items[tab].id,
                    'tabindex': items[tab].tabindex
                });
            let atag = $('<a/>').text(items[tab].name);
            atag.prop('title', items[tab].name);
            li.append(atag);
            $('.tabHeaders').append(li);

            let div = $('<div/>').addClass('tab ' + items[tab].class)
                .attr({
                    'id': items[tab].class
                });
            $('.tabsContainer').append(div);
            $('#' + items[tab].id).on('click', items[tab].action);
        }
    },

    hideSidebar: function () {
        document.getElementById('app-sidebar').dataset.id = '';
        OC.Apps.hideAppSidebar();
        document.querySelector('.tabHeaders').innerHTML = '';
        document.querySelector('.tabsContainer').innerHTML = '';
    },

    resetView: function () {
        document.querySelector('.tabHeader.selected').classList.remove('selected');
        let tabs = document.querySelectorAll('.tabsContainer .tab');
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
};

/**
 * @namespace OCA.Analytics.Advanced.Dataload
 */
OCA.Analytics.Advanced.Dataload = {
    dataloadArray: [],

    tabContainerDataload: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Advanced.resetView();
        document.getElementById('tabHeaderDataload').classList.add('selected');
        OCA.Analytics.UI.showElement('tabContainerDataload');
        document.getElementById('tabContainerDataload').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        if (parseInt(document.getElementById('app-sidebar').dataset.type) !== OCA.Analytics.TYPE_INTERNAL_DB) {
            let message = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'Data maintenance is not possible for this type of report') + '</p></div>';
            document.getElementById('tabContainerDataload').innerHTML = message;
            return;
        }

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataload'),
            data: {
                'datasetId': datasetId
            },
            success: function (data) {
                // clone the DOM template
                let table = document.importNode(document.getElementById('templateDataload').content, true);
                table.id = 'tableDataload';
                document.getElementById('tabContainerDataload').innerHTML = '';
                document.getElementById('tabContainerDataload').appendChild(table);
                document.getElementById('createDataloadButton').addEventListener('click', OCA.Analytics.Advanced.Dataload.handleCreateButton);
                document.getElementById('createDataDeletionButton').addEventListener('click', OCA.Analytics.Advanced.Dataload.handleCreateDataDeletionButton);
                document.getElementById('dataLoadList').innerHTML = '';
                document.getElementById('dataDeleteList').innerHTML = '';

                OCA.Analytics.Advanced.Dataload.dataloadArray = [];

                // list all available data loads for dataset
                for (let dataload of data['dataloads']) {
                    if (parseInt(dataload['datasource']) === 0) {
                        document.getElementById('dataDeleteList').appendChild(OCA.Analytics.Advanced.Dataload.buildDataloadRow(dataload));
                    } else {
                        document.getElementById('dataLoadList').appendChild(OCA.Analytics.Advanced.Dataload.buildDataloadRow(dataload));
                    }
                    // keys need to be int; some instances deliver strings from the backend
                    dataload['dataset'] = parseInt(dataload['dataset']);
                    dataload['datasource'] = parseInt(dataload['datasource']);
                    dataload['id'] = parseInt(dataload['id']);
                    // store reulable array
                    OCA.Analytics.Advanced.Dataload.dataloadArray.push(dataload);
                }
                if (OCA.Analytics.Advanced.Dataload.dataloadArray.length === 0) {
                    document.getElementById('dataLoadList').innerHTML = '<span class="userGuidance">'
                        + t('analytics', 'Choose a data source from the dropdown and press "+"')
                        + '</span><br>';
                } else {
                    document.getElementById('dataloadDetailItems').innerHTML = '<span class="userGuidance">'
                        + t('analytics', 'Choose a data load from the list to change its settings')
                        + '</span>';
                }

                let dataLoadCreated = document.getElementById('app-sidebar').dataset.dataLoadCreated;
                if (dataLoadCreated !== 'null' && typeof(dataLoadCreated) !== 'undefined') {
                    OCA.Analytics.Advanced.Dataload.buildDataloadOptions(null, dataLoadCreated);
                }
                document.getElementById('app-sidebar').dataset.dataLoadCreated = null;

                // list all available datasources
                document.getElementById('datasourceSelect').appendChild(OCA.Analytics.Datasource.buildDropdown());

                // write dimension structure to datasource array [0] in case it is required for a deletion job later
                OCA.Analytics.Advanced.Dataload.updateDatasourceDeletionOption();

            }
        });
    },

    updateDatasourceDeletionOption: function () {
        // write dimension structure to datasource array [0] in case it is required for a deletion job later

        // fill Options dropdown
        let optionSelectOptions = '';
        for (let i = 0; i < Object.keys(OCA.Analytics.Filter.optionTextsArray).length; i++) {
            optionSelectOptions = optionSelectOptions + Object.keys(OCA.Analytics.Filter.optionTextsArray)[i] + '-' + Object.values(OCA.Analytics.Filter.optionTextsArray)[i] + '/';
        }

        const datasetId = document.getElementById('app-sidebar').dataset.id;
        let dataset = OCA.Analytics.datasets.find(x => parseInt(x.id) === parseInt(datasetId));

        let datasetOptions = [];
        datasetOptions.push({id: 'filterDimension', name: t('analytics', 'Filter by'), type: 'tf', placeholder: 'dimension1-'+dataset['dimension1'] + '/' + 'dimension2-'+dataset['dimension2']});
        datasetOptions.push({id: 'filterOption', name: t('analytics', 'Operator'), type: 'tf', placeholder: optionSelectOptions});
        datasetOptions.push({id: 'filterValue', name: t('analytics', 'Value'), placeholder: ''});
        OCA.Analytics.datasourceOptions[0] = datasetOptions;
    },

    buildDataloadRow: function (dataload) {
        let item = document.createElement('div');
        item.classList.add('dataloadItem');

        let typeINT = parseInt(dataload['datasource']);
        let typeIcon;
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE || typeINT === OCA.Analytics.TYPE_EXCEL) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else {
            typeIcon = 'icon-external';
        }
        let a = document.createElement('a');
        a.classList.add(typeIcon);
        a.innerText = dataload['name'];
        a.dataset.id = parseInt(dataload['id']);
        a.dataset.datasourceId = parseInt(dataload['datasource']);
        a.addEventListener('click', OCA.Analytics.Advanced.Dataload.handleDataloadSelect);
        item.appendChild(a);
        return item;
    },

    handleDataloadSelect: function (evt) {
        OCA.Analytics.Advanced.Dataload.buildDataloadOptions(evt);
    },

    buildDataloadOptions: function (evt, id = null) {
        let dataload;
        if (id === null) {
            dataload = OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(evt.target.dataset.id));
        } else {
            dataload = OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(id));
        }

        document.getElementById('dataloadDetail').dataset.id = dataload['id'];
        document.getElementById('dataloadName').value = dataload['name'];
        OCA.Analytics.UI.showElement('dataloadDetailHeader');
        OCA.Analytics.UI.showElement('dataloadDetailButtons');
        OCA.Analytics.UI.showElement('dataloadDetailDelete');
        document.getElementById('dataloadUpdateButton').addEventListener('click', OCA.Analytics.Advanced.Dataload.handleUpdateButton);
        document.getElementById('dataloadDeleteButton').addEventListener('click', OCA.Analytics.Advanced.Dataload.handleDeleteButton);
        document.getElementById('dataloadSchedule').value = dataload['schedule'];
        document.getElementById('dataloadSchedule').addEventListener('change', OCA.Analytics.Advanced.Dataload.updateDataload);
        document.getElementById('dataloadOCC').innerText = 'occ analytics:load ' + dataload['id'];

        // get all the options (fields, descriptions, defaults) for a datasource
        // they are read from the generic data source definitions
        document.getElementById('dataloadDetailItems').innerHTML = '';
        document.getElementById('dataloadDetailItems').appendChild(OCA.Analytics.Datasource.buildOptionsForm(dataload['datasource']));

        // set  the options for a datasource
        let fieldValues = JSON.parse(dataload['option']);
        for (let fieldValue in fieldValues) {
            document.getElementById(fieldValue) ? document.getElementById(fieldValue).value = OCA.Analytics.Advanced.Dataload.decodeEscapedHtml(fieldValues[fieldValue]) : null;
        }

        if (dataload['datasource'] === OCA.Analytics.TYPE_INTERNAL_FILE || dataload['datasource'] === OCA.Analytics.TYPE_EXCEL) {
            document.getElementById('link').addEventListener('click', OCA.Analytics.Advanced.Dataload.handleFilepicker);
        }

        OCA.Analytics.UI.showElement('dataloadRun');
        document.getElementById('dataloadExecuteButton').addEventListener('click', OCA.Analytics.Advanced.Dataload.handleExecuteButton);
    },

    handleCreateButton: function () {
        if (document.getElementById('datasourceSelect').value === '') {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Please select data source'));
        } else {
            OCA.Analytics.Advanced.Dataload.createDataload();
        }
    },

    createDataload: function () {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataload'),
            data: {
                'datasetId': parseInt(document.getElementById('app-sidebar').dataset.id),
                'datasourceId': document.getElementById('datasourceSelect').value,
            },
            success: function (data) {
                document.getElementById('app-sidebar').dataset.dataLoadCreated = data.id;
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    handleCreateDataDeletionButton: function () {
        OCA.Analytics.Advanced.Dataload.createDataDelete();
    },

    createDataDelete: function () {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataload'),
            data: {
                'datasetId': parseInt(document.getElementById('app-sidebar').dataset.id),
                'datasourceId': 0,
            },
            success: function (data) {
                document.getElementById('app-sidebar').dataset.dataLoadCreated = data.id;
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    handleUpdateButton: function () {
        OCA.Analytics.Advanced.Dataload.updateDataload();
    },

    updateDataload: function () {
        const dataloadId = document.getElementById('dataloadDetail').dataset.id;
        let option = {};

        // loop all dynamic options of the selected datasource
        let inputFields = document.querySelectorAll('#dataloadDetailItems input, #dataloadDetailItems select, #dataloadDetailDelete select');
        for (let inputField of inputFields) {
            option[inputField['id']] = inputField['value'];
        }
        option = JSON.stringify(option);

        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/dataload/') + dataloadId,
            data: {
                'name': document.getElementById('dataloadName').value,
                'schedule': document.getElementById('dataloadSchedule').value,
                'option': option,
            },
            success: function () {
                OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
                OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => x.id === parseInt(dataloadId))['schedule'] = document.getElementById('dataloadSchedule').value;
                OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => x.id === parseInt(dataloadId))['name'] = document.getElementById('dataloadName').value;
                OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => x.id === parseInt(dataloadId))['option'] = option;
                document.querySelector('[data-id="' + parseInt(dataloadId) + '"]').innerHTML = document.getElementById('dataloadName').value;
            }
        });
    },

    handleDeleteButton: function () {
        OC.dialogs.confirm(
            t('analytics', 'Are you sure?'),
            t('analytics', 'Delete data load'),
            function (e) {
                if (e === true) {
                    OCA.Analytics.Advanced.Dataload.deleteDataload();
                }
            },
            true
        );
    },

    deleteDataload: function () {
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/dataload/') + document.getElementById('dataloadDetail').dataset.id,
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    handleExecuteButton: function () {
        OCA.Analytics.Advanced.Dataload.executeDataload();
    },

    executeDataload: function () {
        let mode;
        if (document.getElementById('testrunCheckbox').checked) {
            mode = 'simulate';
            OC.dialogs.message(
                '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading" id="dataloadLoadingIndicator">Loading</div>',
                t('analytics', 'Data source simulation'),
                'info',
                OC.dialogs.OK_BUTTON,
                function () {
                },
                true,
                true
            );
        } else {
            mode = 'execute';
            OCA.Analytics.Notification.notification('success', t('analytics', 'Load started'));
        }

        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataload/') + mode,
            data: {
                'dataloadId': document.getElementById('dataloadDetail').dataset.id,
            },
            success: function (data) {
                if (mode === 'simulate') {
                    if (parseInt(data.error) === 0) {
                        document.querySelector("[id*=oc-dialog-]").innerHTML = '<div id="simulationData">' + JSON.stringify(data.data) + '</div>';
                    } else {
                        document.querySelector("[id*=oc-dialog-]").innerHTML = 'Error: '
                            + data.error
                            + '<br><br><textarea style="width: 500px;" cols="200" rows="15">'
                            + new Option(data.rawdata).innerHTML
                            + '</textarea>';

                    }
                } else {
                    let messageType;
                    if (parseInt(data.error) === 0) {
                        messageType = 'success';
                    } else {
                        messageType = 'error';
                    }
                    OCA.Analytics.Notification.notification(messageType, data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' '  + t('analytics', 'records updated') + ', '  + data.error + ' '  + t('analytics', 'errors') + ', '  + data.delete + ' '  + t('analytics', 'deletions'));
                }
            }
        });
    },

    handleFilepicker: function () {
        let dataloadId = document.getElementById('dataloadDetail').dataset.id;
        let type = OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(dataloadId))['datasource'];

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
                document.getElementById('link').value = path;
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
        return text.replace(/&amp;|&lt;|&gt;|&quot;|&#039;/g, function(m) {return map[m];});
    },

};

OCA.Analytics.Advanced.Dataset = {
    dataloadArray: [],

    tabContainerDataset: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Advanced.resetView();
        document.getElementById('tabHeaderDataset').classList.add('selected');
        OCA.Analytics.UI.showElement('tabContainerDataset');
        document.getElementById('tabContainerDataset').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
            success: function (data) {
                let table;
                if (data !== false) {
                    // clone the DOM template
                    table = document.importNode(document.getElementById('templateDataset').content, true);
                    table.id = 'sidebarDataset';
                    document.getElementById('tabContainerDataset').innerHTML = '';
                    document.getElementById('tabContainerDataset').appendChild(table);

                    document.getElementById('sidebarDatasetName').value = data['name'];
                    document.getElementById('sidebarDatasetDimension1').value = data['dimension1'];
                    document.getElementById('sidebarDatasetDimension2').value = data['dimension2'];
                    document.getElementById('sidebarDatasetValue').value = data['value'];
                    document.getElementById('sidebarDatasetDeleteButton').addEventListener('click', OCA.Analytics.Advanced.Dataset.handleDeleteButton);
                    document.getElementById('sidebarDatasetUpdateButton').addEventListener('click', OCA.Analytics.Advanced.Dataset.handleUpdateButton);
                    //document.getElementById('sidebarDatasetExportButton').addEventListener('click', OCA.Analytics.Advanced.Dataset.handleExportButton);

                    // get status information like report and data count
                    OCA.Analytics.Advanced.Dataset.getStatus();
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No maintenance possible') + '</p></div>';
                    document.getElementById('tabContainerDataset').innerHTML = table;
                }
            }
        });

    },


    getStatus: function () {
        // get status information like report and data count
        const datasetId = document.getElementById('app-sidebar').dataset.id;
        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId + '/status',
            success: function (data) {
                document.getElementById('sidebarDatasetStatusRecords').innerText = parseInt(data['data']['count']).toLocaleString();

                let text = '';
                for (let report of data['reports']) {
                    text = text + '- ' +report['name']+ '<br>';
                }
                if (text === '') text = t('analytics', 'This dataset is not used!');
                document.getElementById('sidebarDatasetStatusReports').innerHTML = text;
            }
        });

    },

    handleDeleteButton: function (evt) {
        let id = evt.target.parentNode.dataset.id;
        if (id === undefined) id = document.getElementById('app-sidebar').dataset.id;

        OC.dialogs.confirm(
            t('analytics', 'Are you sure?') + ' ' + t('analytics', 'All data including all reports will be deleted!'),
            t('analytics', 'Delete'),
            function (e) {
                if (e === true) {
                    OCA.Analytics.Advanced.Dataset.delete(id);
                    OCA.Analytics.Advanced.hideSidebar();
                }
            },
            true
        );
    },

    handleUpdateButton: function () {
        OCA.Analytics.Advanced.Dataset.update();
    },

    handleExportButton: function () {
        OCA.Analytics.Advanced.Dataset.export();
    },

    wizard: function () {
        document.getElementById('wizardNewCreate').addEventListener('click', OCA.Analytics.Advanced.Dataset.create);
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

        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataset'),
            data: {
                'name': name,
                'dimension1': dimension1,
                'dimension2': dimension2,
                'value': value
            },
            success: function () {
                OCA.Analytics.Wizard.close();
                OCA.Analytics.Navigation.init();
            }
        });
    },

    delete: function (reportId) {
        document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/dataset/') + reportId,
            success: function (data) {
                OCA.Analytics.Navigation.init();
            }
        });
    },

    export: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        window.open(OC.generateUrl('apps/analytics/report/dataset/') + reportId, '_blank')
    },

    update: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('sidebarDatasetUpdateButton');
        button.classList.add('loading');
        button.disabled = true;


        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/dataset/') + reportId,
            data: {
                'name': document.getElementById('sidebarDatasetName').value,
                'dimension1': document.getElementById('sidebarDatasetDimension1').value,
                'dimension2': document.getElementById('sidebarDatasetDimension2').value,
                'value': document.getElementById('sidebarDatasetValue').value
            },
            success: function () {
                button.classList.remove('loading');
                button.disabled = false;

                OCA.Analytics.Navigation.init(reportId);
                OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
            }
        });
    },
};

OCA.Analytics.Advanced.Backend = {

    updateData: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('updateDataButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/data/') + reportId,
            data: {
                'dimension1': document.getElementById('DataDimension1').value,
                'dimension2': document.getElementById('DataDimension2').value,
                'value': document.getElementById('DataValue').value,
            },
            success: function (data) {
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.Notification.notification('success', data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' ' + t('analytics', 'records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            }
        });
    },

    deleteDataSimulate: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('deleteDataButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/data/deleteDataSimulate'),
            data: {
                'reportId': reportId,
                'dimension1': document.getElementById('DataDimension1').value,
                'dimension2': document.getElementById('DataDimension2').value,
            },
            success: function (data) {
                OC.dialogs.confirm(
                    t('analytics', 'Are you sure?') + ' ' + t('analytics', 'Records to be deleted: ') + data.delete.count,
                    t('analytics', 'Delete data'),
                    function (e) {
                        if (e === true) {
                            OCA.Analytics.Sidebar.Backend.deleteData();
                        } else if (e === false) {
                            button.classList.remove('loading');
                            button.disabled = false;
                        }
                    },
                    true
                );
            }
        });
    },

    deleteData: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('deleteDataButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/data/') + reportId,
            data: {
                'dimension1': document.getElementById('DataDimension1').value,
                'dimension2': document.getElementById('DataDimension2').value,
            },
            success: function (data) {
                button.classList.remove('loading');
                button.disabled = false;
                if (document.getElementById('advanced').value === 'false') {
                    OCA.Analytics.UI.resetContentArea();
                    OCA.Analytics.Backend.getData();
                }
            }
        });
    },

    importCsvData: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('importDataClipboardButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/data/importCSV'),
            data: {
                'reportId': reportId,
                'import': document.getElementById('importDataClipboardText').value,
            },
            success: function (data) {
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.Notification.notification('success', data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' ' + t('analytics', 'records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            },
            error: function () {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Technical error. Please check the logs.'));
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    },

    importFileData: function (path) {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('importDataFileButton');
        button.classList.add('loading');
        button.disabled = true;

        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/data/importFile'),
            data: {
                'reportId': reportId,
                'path': path,
            },
            success: function (data) {
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.Notification.notification('success', data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' ' + t('analytics', 'records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            },
            error: function () {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Technical error. Please check the logs.'));
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    },
};
