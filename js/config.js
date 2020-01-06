/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */
/** global: OCA */
/** global: OCP */
/** global: OC */
'use strict';

/**
 * @namespace OCA.Analytics.Config
 */
OCA.Analytics.Config = {};

/**
 * @namespace OCA.Analytics.Config.Dataload
 */
OCA.Analytics.Config.Dataload = {
    datasourceTemplates: [],
    dataloadArray: [],

    tabContainerDataload: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderDataload').classList.add('selected');
        document.getElementById('tabContainerDataload').classList.remove('hidden');
        document.getElementById('tabContainerDataload').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('analytics', 'Reading data') + '</p></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataload/') + datasetId,
            success: function (data) {
                let table;
                table = document.getElementById('templateDataload').cloneNode(true);
                table.id = 'tableDataload';
                document.getElementById('tabContainerDataload').innerHTML = '';
                document.getElementById('tabContainerDataload').appendChild(table);
                document.getElementById('createDataloadButton').addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadCreateButton);
                document.getElementById('dataloadList').innerHTML = '';
                for (let dataload of data['dataloads']) {
                    const li = OCA.Analytics.Config.Dataload.buildDataloadRow(dataload);
                    document.getElementById('dataloadList').appendChild(li);
                }
                OCA.Analytics.Config.Dataload.datasourceTemplates = data['templates'];
                OCA.Analytics.Config.Dataload.dataloadArray = data['dataloads'];
            }
        });
    },

    handleDataloadCreateButton: function () {
        OCA.Analytics.Config.Dataload.createDataload();
    },

    handleDataloadUpdateButton: function () {
        OCA.Analytics.Config.Dataload.updateDataload();
    },

    handleDataloadDeleteButton: function () {
        OC.dialogs.confirm(
            t('analytics', 'Are you sure?'),
            t('analytics', 'Delete Dataload'),
            function (e) {
                if (e === true) {
                    OCA.Analytics.Config.Dataload.deleteDataload();
                }
            },
            true
        );
    },

    handleDataloadEditClick: function (evt) {
        OCA.Analytics.Config.Dataload.bildDataloadDetails(evt);
    },

    handleDataloadExecuteButton: function (evt) {
        OCA.Analytics.Config.Dataload.executeDataload();
    },

    buildDataloadRow: function (dataload) {

        let item = document.createElement('div');
        item.classList.add('dataloadItem');

        let typeINT = parseInt(dataload.datasource);
        let typeIcon;
        if (typeINT === OCA.Analytics.TYPE_INTERNAL_FILE) {
            typeIcon = 'icon-file';
        } else if (typeINT === OCA.Analytics.TYPE_INTERNAL_DB) {
            typeIcon = 'icon-projects';
        } else if (typeINT === OCA.Analytics.TYPE_GIT || typeINT === OCA.Analytics.TYPE_EXTERNAL_FILE) {
            typeIcon = 'icon-external';
        } else {
            typeIcon = '';
        }
        let a = document.createElement('a');
        //a.setAttribute('href', '#');
        a.classList.add(typeIcon);
        a.innerText = dataload.name;
        a.dataset.id = dataload.id;
        a.dataset.datasourceId = dataload.datasource;
        a.addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadEditClick);
        item.appendChild(a);
        return item;
    },

    bildDataloadDetails: function (evt) {
        let dataload = OCA.Analytics.Config.Dataload.dataloadArray.find(x => x.id === parseInt(evt.target.dataset.id));
        let template = OCA.Analytics.Config.Dataload.datasourceTemplates[evt.target.dataset.datasourceId];

        document.getElementById('dataloadDetail').dataset.id = dataload.id;
        document.getElementById('dataloadName').value = dataload.name;
        document.getElementById('dataloadDetailHeader').hidden = false;
        document.getElementById('dataloadDetailButtons').hidden = false;
        document.getElementById('dataloadUpdateButton').addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadUpdateButton);
        document.getElementById('dataloadDeleteButton').addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadDeleteButton);
        document.getElementById('dataloadScheduleButton').addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadUpdateButton);

        let item = document.getElementById('dataloadDetailItems');
        item.innerHTML = '';

        for (let option of template) {
            let tablerow = document.createElement('div');
            tablerow.style.display = 'table-row';
            let label = document.createElement('div');
            label.style.display = 'table-cell';
            label.classList.add('input150');
            label.innerText = option.name;
            let input = document.createElement('input');
            input.style.display = 'table-cell';
            input.classList.add('input150');
            input.placeholder = option.placeholder;
            input.id = option.id;
            let fieldValues = JSON.parse(dataload.option);
            if (option.id in fieldValues) {
                input.value = fieldValues[option.id];
            }

            item.appendChild(tablerow);
            tablerow.appendChild(label);
            tablerow.appendChild(input);
        }

        document.getElementById('dataloadRun').hidden = false;
        document.getElementById('dataloadExecuteButton').addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadExecuteButton);
        //scheduleButton.addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadExecuteButton);
        //useButton.addEventListener('click', OCA.Analytics.Config.Dataload.handleDataloadExecuteButton);

    },

    createDataload: function () {
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);

        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataload'),
            data: {
                'datasetId': datasetId,
                'datasourceId': document.getElementById('dataloadType').value,
            },
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    updateDataload: function () {
        const dataloadId = document.getElementById('dataloadDetail').dataset.id;
        let option = {};

        let inputFields = document.querySelectorAll('#dataloadDetailItems input');
        for (let inputField of inputFields) {
            option[inputField.id] = inputField.value;
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
                OCA.Analytics.UI.notification('success', t('analytics', 'Dataload saved'));
            }
        });
    },

    deleteDataload: function () {
        const dataloadId = document.getElementById('dataloadDetail').dataset.id;
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/dataload/') + dataloadId,
            success: function (data) {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    executeDataload: function () {
        const dataloadId = document.getElementById('dataloadDetail').dataset.id;
        let mode;
        if (document.getElementById('testrunCheckbox').checked) {
            mode = 'simulate';
        } else {
            mode = 'execute';
        }

        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/dataload/') + mode,
            data: {
                'dataloadId': dataloadId,
            },
            success: function (data) {
                if (mode === 'simulate') {
                    OC.dialogs.message(
                        JSON.stringify(data.data),
                        t('analytics', 'Datasource simulation'),
                        'info',
                        OC.dialogs.OK_BUTTON,
                        function (e) {
                        },
                        true,
                        true
                    );
                } else {
                    if (data.error === 0) {
                        OCA.Analytics.UI.notification('success', data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated'));
                        //document.querySelector('#navigationDatasets [data-id="' + datasetId + '"]').click();
                    } else {
                        OCA.Analytics.UI.notification('error', data.error);
                    }
                }
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
        action: OCA.Analytics.Config.Dataload.tabContainerDataload,
    });

    OCA.Analytics.Sidebar.registerSidebarTab({
        id: 'tabHeaderThreshold',
        class: 'tabContainerThreshold',
        tabindex: '3',
        name: t('analytics', 'Thresholds'),
        action: OCA.Analytics.Sidebar.Threshold.tabContainerThreshold,
    });

});