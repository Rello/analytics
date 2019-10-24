/**
 * Nextcloud Data
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2016-2019 Marcel Scherello
 */

'use strict';

if (!OCA.Data) {
    /**
     * @namespace
     */
    OCA.Data = {};
}

/**
 * @namespace OCA.Data.Sidebar
 */
OCA.Data.Sidebar = {
    sidebar_tabs: {},

    showSidebar: function (evt) {
        var navigationItem = evt.target;
        var datasetId = navigationItem.dataset.id;
        var datasetType = navigationItem.dataset.type;
        var appsidebar = document.getElementById('app-sidebar');

        if (appsidebar.dataset.id === datasetId) {
            OCA.Data.Sidebar.hideSidebar();
        } else {

            document.getElementById('sidebarTitle').innerHTML = navigationItem.dataset.name;
            //document.getElementById('sidebarMime').innerHTML = navigationItem.dataset.id;

            if (appsidebar.dataset.id === '') {
                $('#sidebarClose').on('click', OCA.Data.Sidebar.hideSidebar);

                OCA.Data.Sidebar.constructTabs(datasetType);
                document.getElementById('tabHeaderDataset').classList.add('selected');
                OC.Apps.showAppSidebar();
            }
            appsidebar.dataset.id = datasetId;
            document.querySelector('.tabHeader.selected').click();
        }
    },

    registerSidebarTab: function (tab) {
        var id = tab.id;
        this.sidebar_tabs[id] = tab;
    },

    constructTabs: function (datasetType) {
        var tab = {};

        document.querySelector('.tabHeaders').innerHTML = '';
        document.querySelector('.tabsContainer').innerHTML = '';

        OCA.Data.Sidebar.registerSidebarTab({
            id: 'tabHeaderDataset',
            class: 'tabContainerDataset',
            tabindex: '1',
            name: t('data', 'Options'),
            action: OCA.Data.Sidebar.tabContainerDataset,
        });

        OCA.Data.Sidebar.registerSidebarTab({
            id: 'tabHeaderData',
            class: 'tabContainerData',
            tabindex: '2',
            name: t('data', 'Data'),
            action: OCA.Data.Sidebar.tabContainerData,
        });

        OCA.Data.Sidebar.registerSidebarTab({
            id: 'tabHeaderSharing',
            class: 'tabContainerSharing',
            tabindex: '3',
            name: t('data', 'Sharing'),
            action: OCA.Data.Sidebar.tabContainerSharing,
        });

        var items = _.map(OCA.Data.Sidebar.sidebar_tabs, function (item) {
            return item;
        });
        items.sort(OCA.Data.Sidebar.sortByName);

        for (tab in items) {
            var li = $('<li/>').addClass('tabHeader')
                .attr({
                    'id': items[tab].id,
                    'tabindex': items[tab].tabindex
                });
            var atag = $('<a/>').text(items[tab].name);
            atag.prop('title', items[tab].name);
            li.append(atag);
            $('.tabHeaders').append(li);

            var div = $('<div/>').addClass('tab ' + items[tab].class)
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

    tabContainerDataset: function () {
        var datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderDataset').classList.add('selected');
        document.getElementById('tabContainerDataset').classList.remove('hidden');
        document.getElementById('tabContainerDataset').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('audioplayer', 'Reading data') + '</p></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/data/dataset/') + datasetId,
            success: function (data) {
                if (data !== false) {

                    var table = document.getElementById('templateDataset').cloneNode(true);
                    document.getElementById('tabContainerDataset').innerHTML = '';
                    document.getElementById('tabContainerDataset').appendChild(table);
                    document.getElementById('tableName').value = data.name;
                    document.getElementById('tableParent').value = data.parent;
                    document.getElementById('tableType').value = data.type;
                    document.getElementById('tableLink').value = data.link;
                    document.getElementById('tableVisualization').value = data.visualization;
                    document.getElementById('tableChart').value = data.chart;
                    document.getElementById('dimension1').value = data.dimension1;
                    document.getElementById('dimension2').value = data.dimension2;
                    document.getElementById('dimension3').value = data.dimension3;
                    document.getElementById('deleteDatasetButton').addEventListener('click', OCA.Data.Sidebar.handleDeleteDatasetButton);
                    document.getElementById('updateDatasetButton').addEventListener('click', OCA.Data.Sidebar.handleUpdateDatasetButton);
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('data', 'No maintenance possible') + '</p></div>';
                    document.getElementById('tabContainerDataset').innerHTML = table;
                }
            }
        });

    },

    tabContainerData: function () {
        var trackid = document.getElementById('app-sidebar').dataset.id;

        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderData').classList.add('selected');
        document.getElementById('tabContainerData').classList.remove('hidden');
        document.getElementById('tabContainerData').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('audioplayer', 'Reading data') + '</p></div>';

        var table = document.getElementById('templateData').cloneNode(true);
        document.getElementById('tabContainerData').innerHTML = '';
        document.getElementById('tabContainerData').appendChild(table);
        document.getElementById('updateDataButton').addEventListener('click', OCA.Data.Sidebar.handleUpdateDataButton);
        document.getElementById('deleteDataButton').addEventListener('click', OCA.Data.Sidebar.handleDeleteDataButton);
        document.getElementById('importDataFileButton').addEventListener('click', OCA.Data.Sidebar.handleImportDataFileButton);
        document.getElementById('importDataClipboardButton').addEventListener('click', OCA.Data.Sidebar.handleImportDataClipboardButton);

    },

    tabContainerSharing: function () {
        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderSharing').classList.add('selected');
        document.getElementById('tabContainerSharing').classList.remove('hidden');

        var table = document.createElement('div');
        table.style.display = 'table';
        table.classList.add('table');

        var visualization = document.createElement('input');
        visualization.value = data.visualization;

        var tablerow = document.createElement('div');
        tablerow.style.display = 'table-row';

        var tablekey = document.createElement('div');
        tablekey.innerText = 'Name';

        var name = document.createElement('input');
        name.value = data.name;

        var tablevalue = document.createElement('div');
        tablevalue.appendChild(name);

        tablerow.appendChild(tablekey);
        tablerow.appendChild(tablevalue);
        table.append(tablerow);

        var tablerow = document.createElement('div');
        tablerow.style.display = 'table-row';

        var tablekey = document.createElement('div');
        tablekey.innerText = 'Type';

        var type = document.createElement('input');
        type.value = data.type;

        var tablevalue = document.createElement('div');
        tablevalue.appendChild(type);

        tablerow.appendChild(tablekey);
        tablerow.appendChild(tablevalue);
        table.append(tablerow);

        var tablerow = document.createElement('div');
        tablerow.style.display = 'table-row';

        var tablekey = document.createElement('div');
        tablekey.innerText = 'Visualization';

        var visualization = document.createElement('input');
        visualization.value = data[0][0].visualization;

        var tablevalue = document.createElement('div');
        tablevalue.appendChild(visualization);

        tablerow.appendChild(tablekey);
        tablerow.appendChild(tablevalue);
        table.append(tablerow);

        var html = '<div style="margin-left: 2em; background-position: initial;" class="icon-info">';
        html += '<p style="margin-left: 2em;">' + t('audioplayer', 'Available Audio Player Add-Ons:') + '</p>';
        html += '<p style="margin-left: 2em;"><br></p>';
        html += '<a href="https://github.com/rello/audioplayer_editor"  target="_blank" >';
        html += '<p style="margin-left: 2em;">- ' + t('audioplayer', 'ID3 editor') + '</p>';
        html += '</a>';
        html += '<a href="https://github.com/rello/audioplayer_sonos"  target="_blank" >';
        html += '<p style="margin-left: 2em;">- ' + t('audioplayer', 'SONOS playback') + '</p>';
        html += '</a></div>';
        document.getElementById('tabContainerVisualization').innerHTML = html;
    },

    resetView: function () {
        document.querySelector('.tabHeader.selected').classList.remove('selected');
        $('.tab').addClass('hidden');
    },

    sortByName: function (a, b) {
        var aName = a.tabindex;
        var bName = b.tabindex;
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },

    handleDeleteDatasetButton: function () {
        var id = document.getElementById('app-sidebar').dataset.id
        OCA.Data.Sidebar.Backend.deleteDataset(id);
        OCA.Data.Sidebar.hideSidebar();
    },

    handleUpdateDatasetButton: function () {
        var id = document.getElementById('app-sidebar').dataset.id
        OCA.Data.Sidebar.Backend.updateDataset(id);
    },

    handleUpdateDataButton: function () {
        var id = document.getElementById('app-sidebar').dataset.id
        OCA.Data.Sidebar.Backend.updateData(id);
    },

    handleDeleteDataButton: function () {
        OCA.Data.Sidebar.Backend.updateDataset();
    },

    handleImportDataFileButton: function () {
        var id = document.getElementById('app-sidebar').dataset.id
        var mimeparts = ['text/csv', 'text/plain'];
        OC.dialogs.filepicker(t('audioplayer', 'Select file'), OCA.Data.Sidebar.Backend.importFileData.bind(this), false, mimeparts, true, 1);
    },

    handleImportDataClipboardButton: function () {
        document.getElementById('importDataClipboardText').attributes.removeNamedItem('hidden');
        document.getElementById('importDataClipboardButtonGo').attributes.removeNamedItem('hidden');
        document.getElementById('importDataClipboardButtonGo').addEventListener('click', OCA.Data.Sidebar.Backend.importCsvData);
    },
};
OCA.Data.Sidebar.Backend = {

    deleteDataset: function (datasetId) {
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/data/dataset/') + datasetId,
            success: function (data) {
                OCA.Data.Core.initNavigation();
            }.bind()
        });
    },

    updateDataset: function () {
        var datasetId = document.getElementById('app-sidebar').dataset.id
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/data/dataset/') + datasetId,
            data: {
                'name': document.getElementById('tableName').value,
                'parent': document.getElementById('tableParent').value,
                'type': document.getElementById('tableType').value,
                'link': document.getElementById('tableLink').value,
                'visualization': document.getElementById('tableVisualization').value,
                'chart': document.getElementById('tableChart').value,
                'dimension1': document.getElementById('dimension1').value,
                'dimension2': document.getElementById('dimension2').value,
                'dimension3': document.getElementById('dimension3').value
            },
            success: function (data) {
                OCA.Data.Core.initNavigation();
            }.bind()
        });
    },

    updateData: function (datasetId) {
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/data/data/') + datasetId,
            data: {
                'object': document.getElementById('tableObject').value,
                'value': document.getElementById('tableValue').value,
                'date': document.getElementById('tableDate').value,
            },
            success: function (data) {
                document.querySelector('#navigationDatasets .active').click();
            }.bind()
        });
    },

    importCsvData: function () {
        var datasetId = document.getElementById('app-sidebar').dataset.id
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/data/data/importCSV'),
            data: {
                'datasetId': datasetId,
                'import': document.getElementById('importDataClipboardText').value,
            },
            success: function (data) {
                OC.Notification.showTemporary(data.insert + ' records inserted, ' + data.update + ' records updated');
                document.querySelector('#navigationDatasets .active').click();
            }.bind()
        });
    },

    importFileData: function (path) {
        var datasetId = document.getElementById('app-sidebar').dataset.id
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/data/data/importFile'),
            data: {
                'datasetId': datasetId,
                'path': path,
            },
            success: function (data) {
                OC.Notification.showTemporary(data.insert + ' records inserted, ' + data.update + ' records updated');
                document.querySelector('#navigationDatasets .active').click();
            }.bind()
        });
    },

};
