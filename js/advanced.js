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
OCA.Analytics.Advanced = {};

/**
 * @namespace OCA.Analytics.Advanced.Dataload
 */
OCA.Analytics.Advanced.Dataload = {
    dataloadArray: [],

    tabContainerDataload: function () {
        const reportId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
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
                'datasetId': null,
                'reportId': reportId,
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
            document.getElementById('link').addEventListener('click', OCA.Analytics.Sidebar.Dataset.handleFilepicker);
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

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.Sidebar.registerSidebarTab({
        id: 'tabHeaderDataload',
        class: 'tabContainerDataload',
        tabindex: '2',
        name: t('analytics', 'Dataload'),
        action: OCA.Analytics.Advanced.Dataload.tabContainerDataload,
    });

    OCA.Analytics.Sidebar.registerSidebarTab({
        id: 'tabHeaderThreshold',
        class: 'tabContainerThreshold',
        tabindex: '3',
        name: t('analytics', 'Thresholds'),
        action: OCA.Analytics.Advanced.Threshold.tabContainerThreshold,
    });

});