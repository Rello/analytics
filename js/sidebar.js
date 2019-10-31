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
                    document.getElementById('tableType').addEventListener('change', OCA.Data.Sidebar.handleDatasetTypeChange);
                    document.getElementById('datasetLink').value = data.link;
                    document.getElementById('datasetLinkButton').addEventListener('click', OCA.Data.Sidebar.handleDatasetParameterButton);
                    document.getElementById('tableVisualization').value = data.visualization;
                    document.getElementById('tableChart').value = data.chart;
                    document.getElementById('dimension1').value = data.dimension1;
                    document.getElementById('dimension2').value = data.dimension2;
                    document.getElementById('dimension3').value = data.dimension3;
                    document.getElementById('deleteDatasetButton').addEventListener('click', OCA.Data.Sidebar.handleDatasetDeleteButton);
                    document.getElementById('updateDatasetButton').addEventListener('click', OCA.Data.Sidebar.handleDatasetUpdateButton);
                    OCA.Data.Sidebar.handleDatasetTypeChange();
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('data', 'No maintenance possible') + '</p></div>';
                    document.getElementById('tabContainerDataset').innerHTML = table;
                }
            }
        });

    },

    tabContainerData: function () {
        var datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderData').classList.add('selected');
        document.getElementById('tabContainerData').classList.remove('hidden');
        document.getElementById('tabContainerData').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('audioplayer', 'Reading data') + '</p></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/data/dataset/') + datasetId,
            success: function (data) {
                if (data !== false && data.type === OCA.Data.TYPE_INTERNAL_DB) {
                    var table = document.getElementById('templateData').cloneNode(true);
                    document.getElementById('tabContainerData').innerHTML = '';
                    document.getElementById('tabContainerData').appendChild(table);
                    document.getElementById('DataTextDimension1').innerText = data.dimension1;
                    document.getElementById('DataTextDimension2').innerText = data.dimension2;
                    document.getElementById('DataTextDimension3').innerText = data.dimension3;
                    document.getElementById('updateDataButton').addEventListener('click', OCA.Data.Sidebar.handleUpdateDataButton);
                    document.getElementById('deleteDataButton').addEventListener('click', OCA.Data.Sidebar.handleDeleteDataButton);
                    document.getElementById('importDataFileButton').addEventListener('click', OCA.Data.Sidebar.handleImportDataFileButton);
                    document.getElementById('importDataClipboardButton').addEventListener('click', OCA.Data.Sidebar.handleImportDataClipboardButton);
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('data', 'Data maintenance is not possible for this type of dataset') + '</p></div>';
                    document.getElementById('tabContainerData').innerHTML = table;
                }
            }
        });
    },

    tabContainerSharing: function () {
        var datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderSharing').classList.add('selected');
        document.getElementById('tabContainerSharing').classList.remove('hidden');
        document.getElementById('tabContainerSharing').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('audioplayer', 'Reading data') + '</p></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/data/share/') + datasetId,
            success: function (data) {

                var linkShareView = document.createElement('div');
                linkShareView.classList.add('linkShareView', 'subView');
                var shareWithList = document.createElement('div');
                shareWithList.classList.add('shareWithList');
                linkShareView.appendChild(shareWithList);

                var shareeListView = document.createElement('div');
                shareeListView.classList.add('shareeListView', 'subView');
                var shareWithList_sharee = document.createElement('div');
                shareWithList_sharee.classList.add('shareWithList');
                shareeListView.appendChild(shareWithList_sharee);

                var li = OCA.Data.Sidebar.buildShareLinkRow(0, 0, true);
                shareWithList.appendChild(li);

                if (data !== false) {

                    for (var share of data) {

                        if (share.share_type === OCA.Data.SHARE_TYPE_LINK) {
                            var li = OCA.Data.Sidebar.buildShareLinkRow(share.id, share.token, false);
                            shareWithList.appendChild(li);
                        } else if (share.share_type === OCA.Data.SHARE_USER) {
                            var li = OCA.Data.Sidebar.buildShareeRow(share.id, share.uid_owner);
                            shareWithList_sharee.appendChild(li);
                        }
                    }

                    document.getElementById('tabContainerSharing').innerHTML = '';
                    document.getElementById('tabContainerSharing').appendChild(linkShareView);
                    document.getElementById('tabContainerSharing').appendChild(shareeListView);
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('data', 'No Shares found') + '</p></div>';
                    document.getElementById('tabContainerData').innerHTML = table;
                }

            }
        });

    },

    buildShareLinkRow: function (id, token, add) {

        var li = document.createElement('li');
        li.dataset.id = id;

        var avatar = document.createElement('div');
        avatar.classList.add('avatar', 'icon-public-white');

        var name = document.createElement('span');
        name.classList.add('username');
        name.innerText = 'Share Link';

        var moreIcon = document.createElement('a');
        moreIcon.classList.add('icon', 'icon-more');

        var sharingOptionsGroup = document.createElement('span');
        sharingOptionsGroup.classList.add('sharingOptionsGroup');

        var clipboardIcon = document.createElement('a');

        if (add === true) {
            clipboardIcon.classList.add('icon-add', 'icon', 'new-share');
            clipboardIcon.href = '#';
            sharingOptionsGroup.appendChild(clipboardIcon);
        } else {
            clipboardIcon.classList.add('clipboard-button', 'icon', 'icon-clippy');
            clipboardIcon.href = 'https://nc16/nextcloud/apps/data/p/' + token;
            clipboardIcon.target = '_blank';
            sharingOptionsGroup.appendChild(clipboardIcon);
            sharingOptionsGroup.appendChild(moreIcon);
        }

        li.appendChild(avatar);
        li.appendChild(name);
        li.appendChild(sharingOptionsGroup);
        return li;
    },

    buildShareeRow: function (id, uid_owner) {

        var li = document.createElement('li');
        li.dataset.id = id;

        var avatar = document.createElement('div');
        avatar.classList.add('avatar', 'imageplaceholderseed');
        avatar.style = 'width: 32px;height: 32px;/* background-color: rgb(188, 92, 145); */color: rgb(255, 255, 255);font-weight: normal;text-align: center;line-height: 32px;font-size: 17.6px;';
        avatar.innerText = uid_owner.charAt(0);

        var name = document.createElement('span');
        name.classList.add('username');
        name.innerText = uid_owner;

        var moreIcon = document.createElement('a');
        moreIcon.classList.add('icon', 'icon-more');

        var sharingOptionsGroup = document.createElement('span');
        sharingOptionsGroup.classList.add('sharingOptionsGroup');
        sharingOptionsGroup.appendChild(moreIcon);

        li.appendChild(avatar);
        li.appendChild(name);
        li.appendChild(sharingOptionsGroup);
        return li;
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

    handleDatasetDeleteButton: function () {
        var id = document.getElementById('app-sidebar').dataset.id;
        OCA.Data.Sidebar.Backend.deleteDataset(id);
        OCA.Data.Sidebar.hideSidebar();
    },

    handleDatasetUpdateButton: function () {
        var id = document.getElementById('app-sidebar').dataset.id;
        OCA.Data.Sidebar.Backend.updateDataset(id);
    },

    handleUpdateDataButton: function () {
        var id = document.getElementById('app-sidebar').dataset.id;
        OCA.Data.Sidebar.Backend.updateData(id);
    },

    handleDatasetTypeChange: function () {
        var type = parseInt(document.getElementById('tableType').value);
        if (type === OCA.Data.TYPE_INTERNAL_DB) {
            document.getElementById('datasetLinkRow').style.display = 'none';
            document.getElementById('datasetDimensionSection').style.display = 'table';
            document.getElementById('datasetDimensionSectionHeader').style.removeProperty('display');
            document.getElementById('datasetVisualizationSection').style.display = 'table';
            document.getElementById('datasetVisualizationSectionHeader').style.removeProperty('display');
        } else if (type === OCA.Data.TYPE_INTERNAL_FILE) {
            document.getElementById('datasetLinkRow').style.display = 'table-row';
            document.getElementById('datasetDimensionSection').style.display = 'none';
            document.getElementById('datasetDimensionSectionHeader').style.display = 'none';
            document.getElementById('datasetVisualizationSection').style.display = 'table';
            document.getElementById('datasetVisualizationSectionHeader').style.removeProperty('display');
        } else if (type === OCA.Data.TYPE_GIT) {
            document.getElementById('datasetLinkRow').style.display = 'table-row';
            document.getElementById('datasetDimensionSection').style.display = 'none';
            document.getElementById('datasetDimensionSectionHeader').style.display = 'none';
            document.getElementById('datasetVisualizationSection').style.display = 'table';
            document.getElementById('datasetVisualizationSectionHeader').style.removeProperty('display');
        } else {
            document.getElementById('datasetLinkRow').style.display = 'none';
            document.getElementById('datasetDimensionSection').style.display = 'none';
            document.getElementById('datasetDimensionSectionHeader').style.display = 'none';
            document.getElementById('datasetVisualizationSection').style.display = 'none';
            document.getElementById('datasetVisualizationSectionHeader').style.display = 'none';
        }
    },

    handleDatasetParameterButton: function () {
        var type = parseInt(document.getElementById('tableType').value);
        if (type === OCA.Data.TYPE_GIT) {
            OC.dialogs.prompt(
                "Enter GitHub User/Repositors. the '/' is important here",
                "Github API source",
                function (button, val) {
                    if (button === true) document.getElementById('datasetLink').value = val;
                },
                true,
                "user/repo");
        } else if (type === OCA.Data.TYPE_INTERNAL_FILE) {
            var mimeparts = ['text/csv', 'text/plain'];
            OC.dialogs.filepicker(
                t('data', 'Select file'),
                function (path) {
                    document.getElementById('datasetLink').value = path;
                },
                false,
                mimeparts,
                true,
                1);
        }
    },

    handleDeleteDataButton: function () {
        OCA.Data.Sidebar.Backend.updateDataset();
    },

    handleImportDataFileButton: function () {
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
                'link': document.getElementById('datasetLink').value,
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
                'dimension1': document.getElementById('DataDimension1').value,
                'dimension2': document.getElementById('DataDimension2').value,
                'dimension3': document.getElementById('DataDimension3').value,
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
