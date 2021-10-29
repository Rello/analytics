/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
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
            name: t('analytics', 'Dataload'),
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

    indicateImportantField: function (element) {
        document.getElementById(element).classList.add('indicateImportantField');
    },

    resetImportantFields: function () {
        let fields = document.querySelectorAll('.indicateImportantField');
        for (let i = 0; i < fields.length; i++) {
            fields[i].classList.remove('indicateImportantField');
        }
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
                document.getElementById('dataloadList').innerHTML = '';

                OCA.Analytics.Advanced.Dataload.dataloadArray = [];

                // list all available dataloads for dataset
                for (let dataload of data['dataloads']) {
                    document.getElementById('dataloadList').appendChild(OCA.Analytics.Advanced.Dataload.buildDataloadRow(dataload));
                    // keys need to be int; some instances deliver strings from the backend
                    dataload['dataset'] = parseInt(dataload['dataset']);
                    dataload['datasource'] = parseInt(dataload['datasource']);
                    dataload['id'] = parseInt(dataload['id']);
                    // store reulable array
                    OCA.Analytics.Advanced.Dataload.dataloadArray.push(dataload);
                }
                if (OCA.Analytics.Advanced.Dataload.dataloadArray.length === 0) {
                    document.getElementById('dataloadList').innerHTML = '<span class="userGuidance">'
                        + t('analytics', 'This report does not have any dataloads. <br>Choose a datasource from the dropdown and press "+"')
                        + '</span><br><br>';
                } else {
                    document.getElementById('dataloadDetailItems').innerHTML = '<span class="userGuidance">'
                        + t('analytics', 'Choose a dataload from the list to change its settings')
                        + '</span>';
                }

                // list all available datasources
                document.getElementById('datasourceSelect').appendChild(OCA.Analytics.Datasource.buildDropdown());
            }
        });
    },

    buildDataloadRow: function (dataload) {
        let item = document.createElement('div');
        item.classList.add('dataloadItem');

        let typeINT = parseInt(dataload['datasource']);
        let typeIcon;
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE) {
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

    buildDataloadOptions: function (evt) {
        let dataload = OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(evt.target.dataset.id));

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

        // get all the options for a datasource
        document.getElementById('dataloadDetailItems').innerHTML = '';
        document.getElementById('dataloadDetailItems').appendChild(OCA.Analytics.Datasource.buildOptionsForm(dataload['datasource']));

        // set  the options for a datasource
        let fieldValues = JSON.parse(dataload['option']);
        for (let fieldValue in fieldValues) {
            document.getElementById(fieldValue) ? document.getElementById(fieldValue).value = fieldValues[fieldValue] : null;
        }

        if (dataload['datasource'] === OCA.Analytics.TYPE_INTERNAL_FILE || dataload['datasource'] === OCA.Analytics.TYPE_EXCEL) {
            document.getElementById('link').addEventListener('click', OCA.Analytics.Advanced.Dataset.handleFilepicker);
        }

        OCA.Analytics.UI.showElement('dataloadRun');
        document.getElementById('dataloadExecuteButton').addEventListener('click', OCA.Analytics.Advanced.Dataload.handleExecuteButton);
    },

    handleCreateButton: function () {
        OCA.Analytics.Advanced.Dataload.createDataload();
    },

    createDataload: function () {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataload'),
            data: {
                'datasetId': null,
                'reportId': parseInt(document.getElementById('app-sidebar').dataset.id),
                'datasourceId': document.getElementById('datasourceSelect').value,
            },
            success: function () {
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
            }
        });
    },

    handleDeleteButton: function () {
        OC.dialogs.confirm(
            t('analytics', 'Are you sure?'),
            t('analytics', 'Delete dataload'),
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
                t('analytics', 'Datasource simulation'),
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
                        document.querySelector("[id*=oc-dialog-]").innerHTML = 'Error: ' + data.error;
                    }
                } else {
                    let messageType;
                    if (parseInt(data.error) === 0) {
                        messageType = 'success';
                    } else {
                        messageType = 'error';
                    }
                    OCA.Analytics.Notification.notification(messageType, data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated, ') + data.error + t('analytics', ' errors'));
                }
            }
        });
    },

};

/*
OCA.Analytics.Advanced.Threshold = {

    tabContainerThreshold: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderThreshold').classList.add('selected');
        OCA.Analytics.UI.showElement('tabContainerThreshold');
        document.getElementById('tabContainerThreshold').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/report/') + datasetId,
            success: function (data) {
                // clone the DOM template
                let table = document.importNode(document.getElementById('templateThreshold').content, true);
                table.id = 'tableThreshold';
                document.getElementById('tabContainerThreshold').innerHTML = '';
                document.getElementById('tabContainerThreshold').appendChild(table);
                document.getElementById('sidebarThresholdTextDimension1').innerText = data.dimension1 || t('analytics', 'Column 1');
                document.getElementById('sidebarThresholdTextValue').innerText = data.value || t('analytics', 'Column 3');
                document.getElementById('createThresholdButton').addEventListener('click', OCA.Analytics.Advanced.Threshold.handleThresholdCreateButton);
                if (parseInt(data.type) !== OCA.Analytics.TYPE_INTERNAL_DB) {
                    document.getElementById('sidebarThresholdSeverity').remove(0);
                }

                $.ajax({
                    type: 'GET',
                    url: OC.generateUrl('apps/analytics/threshold/') + datasetId,
                    success: function (data) {
                        if (data !== false) {
                            document.getElementById('sidebarThresholdList').innerHTML = '';
                            for (let threshold of data) {
                                const li = OCA.Analytics.Advanced.Threshold.buildThresholdRow(threshold);
                                document.getElementById('sidebarThresholdList').appendChild(li);
                            }
                        }
                    }
                });
            }
        });
    },

    handleThresholdCreateButton: function () {
        OCA.Analytics.Advanced.Threshold.createThreashold();
    },

    handleThresholdDeleteButton: function (evt) {
        const thresholdId = evt.target.dataset.id;
        OCA.Analytics.Advanced.Threshold.deleteThreshold(thresholdId);
    },

    buildThresholdRow: function (data) {
        let bulletColor, bullet;
        data.severity = parseInt(data.severity);
        if (data.severity === 2) {
            bulletColor = 'red';
        } else if (data.severity === 3) {
            bulletColor = 'orange';
        } else {
            bulletColor = 'green';
        }

        if (data.severity === 1) {
            bullet = document.createElement('img');
            bullet.src = OC.imagePath('notifications', 'notifications-dark.svg');
            bullet.classList.add('thresholdBullet');
        } else {
            bullet = document.createElement('div');
            bullet.style.backgroundColor = bulletColor;
            bullet.classList.add('thresholdBullet');
        }

        let item = document.createElement('div');
        item.classList.add('thresholdItem');

        let text = document.createElement('div');
        text.classList.add('thresholdText');
        text.innerText = data.dimension1 + ' ' + data.option + ' ' + data.value;

        let tDelete = document.createElement('div');
        tDelete.classList.add('icon-close');
        tDelete.classList.add('analyticsTesting');
        tDelete.dataset.id = data.id;
        tDelete.addEventListener('click', OCA.Analytics.Advanced.Threshold.handleThresholdDeleteButton);

        item.appendChild(bullet);
        item.appendChild(text);
        item.appendChild(tDelete);
        return item;
    },

    createThreashold: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const url = OC.generateUrl('apps/analytics/threshold');

        $.ajax({
            type: 'POST',
            url: url,
            data: {
                'reportId': reportId,
                'dimension1': document.getElementById('sidebarThresholdDimension1').value,
                'option': document.getElementById('sidebarThresholdOption').value,
                'value': document.getElementById('sidebarThresholdValue').value,
                'severity': document.getElementById('sidebarThresholdSeverity').value,
            },
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    deleteThreshold: function (thresholdId) {

        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/threshold/') + thresholdId,
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },
};
*/

OCA.Analytics.Advanced.Dataset = {

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
                    document.getElementById('sidebarDatasetExportButton').addEventListener('click', OCA.Analytics.Advanced.Dataset.handleExportButton);

                    OCA.Analytics.Advanced.Dataset.getStatus();

                    if (OCA.Analytics.Navigation.newReportId === parseInt(data['id'])) {
                        OCA.Analytics.Advanced.indicateImportantField('sidebarDatasetName');
                    }

                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No maintenance possible') + '</p></div>';
                    document.getElementById('tabContainerDataset').innerHTML = table;
                }
            }
        });

    },

    getStatus: function () {
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
                if (text === '') text = t('analytics', '! This dataset is not used !');
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
                    OCA.Analytics.Advanced.Backend.deleteDataset(id);
                    OCA.Analytics.Advanced.hideSidebar();
                }
            },
            true
        );
    },

    handleUpdateButton: function () {
        OCA.Analytics.Advanced.Backend.updateDataset();
    },

    handleExportButton: function () {
        OCA.Analytics.Advanced.Backend.exporteDataset();
    },
};

OCA.Analytics.Advanced.Backend = {

    exportDataset: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        window.open(OC.generateUrl('apps/analytics/report/dataset/') + reportId, '_blank')
    },

    deleteDataset: function (reportId) {
        document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/dataset/') + reportId,
            success: function (data) {
                OCA.Analytics.Navigation.init();
                OCA.Analytics.Navigation.handleOverviewButton();
            }
        });
    },

    updateDataset: function () {
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

                OCA.Analytics.Navigation.newReportId = 0;
                OCA.Analytics.Navigation.init(reportId);
                OCA.Analytics.Backend.getDatasetDefinitions();
                OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
            }
        });
    },

    createDataset: function () {
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataset'),
            data: {
            },
            success: function (data) {
                OCA.Analytics.Navigation.newReportId = data;
                OCA.Analytics.Navigation.init(data);
            }
        });
    },

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
                    OCA.Analytics.Notification.notification('success', data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated'));
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
                    OCA.Analytics.Notification.notification('success', data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            },
            error: function () {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Technical error. Please check the logs'));
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
                    OCA.Analytics.Notification.notification('success', data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            },
            error: function () {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Technical error. Please check the logs'));
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    },
};