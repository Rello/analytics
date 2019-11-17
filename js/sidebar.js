/**
 * Data Analytics
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
        let navigationItem = evt.target;
        let datasetId = navigationItem.dataset.id;
        let datasetType = navigationItem.dataset.type;
        let appsidebar = document.getElementById('app-sidebar');

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
        let id = tab.id;
        this.sidebar_tabs[id] = tab;
    },

    constructTabs: function (datasetType) {
        let tab = {};

        document.querySelector('.tabHeaders').innerHTML = '';
        document.querySelector('.tabsContainer').innerHTML = '';

        OCA.Data.Sidebar.registerSidebarTab({
            id: 'tabHeaderDataset',
            class: 'tabContainerDataset',
            tabindex: '1',
            name: t('data', 'Options'),
            action: OCA.Data.Sidebar.Dataset.tabContainerDataset,
        });

        OCA.Data.Sidebar.registerSidebarTab({
            id: 'tabHeaderData',
            class: 'tabContainerData',
            tabindex: '2',
            name: t('data', 'Data'),
            action: OCA.Data.Sidebar.Data.tabContainerData,
        });

        OCA.Data.Sidebar.registerSidebarTab({
            id: 'tabHeaderShare',
            class: 'tabContainerShare',
            tabindex: '3',
            name: t('data', 'Share'),
            action: OCA.Data.Sidebar.Share.tabContainerShare,
        });

        let items = _.map(OCA.Data.Sidebar.sidebar_tabs, function (item) {
            return item;
        });
        items.sort(OCA.Data.Sidebar.sortByName);

        for (tab in items) {
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
        $('.tab').addClass('hidden');
    },

    sortByName: function (a, b) {
        let aName = a.tabindex;
        let bName = b.tabindex;
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },

};
OCA.Data.Sidebar.Dataset = {

    tabContainerDataset: function () {
        let datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderDataset').classList.add('selected');
        document.getElementById('tabContainerDataset').classList.remove('hidden');
        document.getElementById('tabContainerDataset').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('audioplayer', 'Reading data') + '</p></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
            success: function (data) {
                let table;
                if (data !== false) {
                    table = document.getElementById('templateDataset').cloneNode(true);
                    document.getElementById('tabContainerDataset').innerHTML = '';
                    document.getElementById('tabContainerDataset').appendChild(table);
                    document.getElementById('tableName').value = data.name;
                    document.getElementById('tableParent').value = data.parent;
                    document.getElementById('tableType').value = data.type;
                    document.getElementById('tableType').addEventListener('change', OCA.Data.Sidebar.Dataset.handleDatasetTypeChange);
                    document.getElementById('datasetLink').value = data.link;
                    document.getElementById('datasetLinkButton').addEventListener('click', OCA.Data.Sidebar.Dataset.handleDatasetParameterButton);
                    document.getElementById('tableVisualization').value = data.visualization;
                    document.getElementById('tableChart').value = data.chart;
                    document.getElementById('dimension1').value = data.dimension1;
                    document.getElementById('dimension2').value = data.dimension2;
                    document.getElementById('dimension3').value = data.dimension3;
                    document.getElementById('deleteDatasetButton').addEventListener('click', OCA.Data.Sidebar.Dataset.handleDatasetDeleteButton);
                    document.getElementById('updateDatasetButton').addEventListener('click', OCA.Data.Sidebar.Dataset.handleDatasetUpdateButton);
                    OCA.Data.Sidebar.Dataset.handleDatasetTypeChange();
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('data', 'No maintenance possible') + '</p></div>';
                    document.getElementById('tabContainerDataset').innerHTML = table;
                }
            }
        });

    },

    handleDatasetDeleteButton: function () {
        let id = document.getElementById('app-sidebar').dataset.id;
        OCA.Data.Sidebar.Backend.deleteDataset(id);
        OCA.Data.Sidebar.hideSidebar();
    },

    handleDatasetUpdateButton: function () {
        let id = document.getElementById('app-sidebar').dataset.id;
        OCA.Data.Sidebar.Backend.updateDataset(id);
    },

    handleDatasetTypeChange: function () {
        let type = parseInt(document.getElementById('tableType').value);
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
        let type = parseInt(document.getElementById('tableType').value);
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
            let mimeparts = ['text/csv', 'text/plain'];
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
};

OCA.Data.Sidebar.Data = {

    tabContainerData: function () {
        let datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderData').classList.add('selected');
        document.getElementById('tabContainerData').classList.remove('hidden');
        document.getElementById('tabContainerData').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('audioplayer', 'Reading data') + '</p></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
            success: function (data) {
                let table;
                if (data !== false && parseInt(data.type) === OCA.Data.TYPE_INTERNAL_DB) {
                    table = document.getElementById('templateData').cloneNode(true);
                    document.getElementById('tabContainerData').innerHTML = '';
                    document.getElementById('tabContainerData').appendChild(table);
                    document.getElementById('DataTextDimension1').innerText = data.dimension1;
                    document.getElementById('DataTextDimension2').innerText = data.dimension2;
                    document.getElementById('DataTextDimension3').innerText = data.dimension3;
                    //document.getElementById('DataTextDimension3').addEventListener('keydown', OCA.Data.Sidebar.Data.handleDataInputEnter);
                    document.getElementById('updateDataButton').addEventListener('click', OCA.Data.Sidebar.Data.handleDataUpdateButton);
                    document.getElementById('deleteDataButton').addEventListener('click', OCA.Data.Sidebar.Data.handleDataDeleteButton);
                    document.getElementById('importDataFileButton').addEventListener('click', OCA.Data.Sidebar.Data.handleDataImportFileButton);
                    document.getElementById('importDataClipboardButton').addEventListener('click', OCA.Data.Sidebar.Data.handleDataImportClipboardButton);

                    document.getElementById('DataDimension3').addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            OCA.Data.Sidebar.Backend.updateData();
                            document.getElementById('DataDimension2').focus();
                            document.getElementById('DataDimension2').value = '';
                            document.getElementById('DataDimension3').value = '';
                        }
                    });
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('data', 'Data maintenance is not possible for this type of dataset') + '</p></div>';
                    document.getElementById('tabContainerData').innerHTML = table;
                }
            }
        });
    },

    handleDataUpdateButton: function () {
        OCA.Data.Sidebar.Backend.updateData();
    },

    handleDataDeleteButton: function () {
        //OCA.Data.Sidebar.Backend.updateDataset();
    },

    handleDataImportFileButton: function () {
        let mimeparts = ['text/csv', 'text/plain'];
        OC.dialogs.filepicker(t('audioplayer', 'Select file'), OCA.Data.Sidebar.Backend.importFileData.bind(this), false, mimeparts, true, 1);
    },

    handleDataImportClipboardButton: function () {
        document.getElementById('importDataClipboardText').attributes.removeNamedItem('hidden');
        document.getElementById('importDataClipboardButtonGo').attributes.removeNamedItem('hidden');
        document.getElementById('importDataClipboardButtonGo').addEventListener('click', OCA.Data.Sidebar.Backend.importCsvData);
    },
};

OCA.Data.Sidebar.Share = {

    tabContainerShare: function () {
        let datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Data.Sidebar.resetView();
        document.getElementById('tabHeaderShare').classList.add('selected');
        document.getElementById('tabContainerShare').classList.remove('hidden');
        document.getElementById('tabContainerShare').innerHTML = '<div style="text-align:center; word-wrap:break-word;" class="get-metadata"><p><img src="' + OC.imagePath('core', 'loading.gif') + '"><br><br></p><p>' + t('audioplayer', 'Reading data') + '</p></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/share/') + datasetId,
            success: function (data) {

                let linkShareView = document.createElement('div');
                linkShareView.classList.add('linkShareView', 'subView');
                let shareWithList = document.createElement('div');
                shareWithList.classList.add('shareWithList');
                linkShareView.appendChild(shareWithList);

                let shareeListView = document.createElement('div');
                shareeListView.classList.add('shareeListView', 'subView');
                let shareWithList_sharee = document.createElement('div');
                shareWithList_sharee.classList.add('shareWithList');
                shareeListView.appendChild(shareWithList_sharee);

                let li = OCA.Data.Sidebar.Share.buildShareLinkRow(0, 0, true);
                shareWithList.appendChild(li);

                if (data !== false) {

                    for (let share of data) {

                        if (parseInt(share.type) === OCA.Data.SHARE_TYPE_LINK) {
                            let li = OCA.Data.Sidebar.Share.buildShareLinkRow(share.id, share.token, false, share.pass);
                            shareWithList.appendChild(li);
                        } else if (parseInt(share.type) === OCA.Data.SHARE_TYPE_USER) {
                            let li = OCA.Data.Sidebar.Share.buildShareeRow(share.id, share.uid_owner);
                            shareWithList_sharee.appendChild(li);
                        }
                    }

                    document.getElementById('tabContainerShare').innerHTML = '';
                    document.getElementById('tabContainerShare').appendChild(linkShareView);
                    document.getElementById('tabContainerShare').appendChild(shareeListView);
                } else {
                    let table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('data', 'No Shares found') + '</p></div>';
                    document.getElementById('tabContainerData').innerHTML = table;
                }

            }
        });

    },

    buildShareLinkRow: function (id, token, add = false, pw = false) {

        let li = document.createElement('li');
        li.dataset.id = id;

        let avatar = document.createElement('div');
        avatar.classList.add('avatar', 'icon-public-white');

        let name = document.createElement('span');
        name.classList.add('username');
        if (add) name.innerText = 'Add Share Link';
        else name.innerText = 'Share Link';

        let moreIcon = document.createElement('a');
        moreIcon.classList.add('icon', 'icon-more');

        let ShareOptionsGroup = document.createElement('span');
        ShareOptionsGroup.classList.add('sharingOptionsGroup');

        let clipboardIcon = document.createElement('a');

        if (add) {
            clipboardIcon.classList.add('icon-add', 'icon', 'new-share');
            clipboardIcon.href = '#';
            clipboardIcon.addEventListener('click', OCA.Data.Sidebar.Share.createLinkShare);
            ShareOptionsGroup.appendChild(clipboardIcon);
        } else {
            clipboardIcon.classList.add('clipboard-button', 'icon', 'icon-clippy');
            clipboardIcon.href = 'https://nc16/nextcloud/apps/data/p/' + token;
            clipboardIcon.target = '_blank';
            ShareOptionsGroup.appendChild(clipboardIcon);
            ShareOptionsGroup.appendChild(OCA.Data.Sidebar.Share.buildShareMenu(id, pw));
        }

        li.appendChild(avatar);
        li.appendChild(name);
        li.appendChild(ShareOptionsGroup);
        return li;
    },

    buildShareeRow: function (id, uid_owner) {

        let li = document.createElement('li');
        li.dataset.id = id;

        let avatar = document.createElement('div');
        avatar.classList.add('avatar', 'imageplaceholderseed');
        avatar.style = 'width: 32px;height: 32px;/* background-color: rgb(188, 92, 145); */color: rgb(255, 255, 255);font-weight: normal;text-align: center;line-height: 32px;font-size: 17.6px;';
        avatar.innerText = uid_owner.charAt(0);

        let name = document.createElement('span');
        name.classList.add('username');
        name.innerText = uid_owner;

        let moreIcon = document.createElement('a');
        moreIcon.classList.add('icon', 'icon-more');

        let ShareOptionsGroup = document.createElement('span');
        ShareOptionsGroup.classList.add('sharingOptionsGroup');
        ShareOptionsGroup.appendChild(moreIcon);

        li.appendChild(avatar);
        li.appendChild(name);
        li.appendChild(ShareOptionsGroup);
        return li;
    },

    buildShareMenu: function (id, pw) {
        let shareMenu = document.createElement('div');
        shareMenu.classList.add('share-menu');

        let moreIcon = document.createElement('a');
        moreIcon.classList.add('icon', 'icon-more');
        moreIcon.addEventListener('click', OCA.Data.Sidebar.Share.showShareMenu);
        shareMenu.appendChild(moreIcon);

        let popoverMenue = document.createElement('div');
        popoverMenue.classList.add('popovermenu', 'menu');
        popoverMenue.style.display = 'none';
        shareMenu.appendChild(popoverMenue);

        let ul = document.createElement('ul');
        popoverMenue.appendChild(ul);

        let liShowPassword = document.createElement('li');
        let spanShowPassword = document.createElement('span');
        spanShowPassword.classList.add('menuitem');
        let inputShowPassword = document.createElement('input');
        inputShowPassword.type = 'checkbox';
        inputShowPassword.name = 'showPassword';
        inputShowPassword.id = 'showPassword-' + id;
        inputShowPassword.classList.add('checkbox', 'showPasswordCheckbox');
        inputShowPassword.addEventListener('click', OCA.Data.Sidebar.Share.showPassMenu);
        let labelShowPassword = document.createElement('label');
        labelShowPassword.setAttribute('for', 'showPassword-' + id);
        labelShowPassword.innerText = 'Password protect';
        liShowPassword.appendChild(spanShowPassword);
        spanShowPassword.appendChild(inputShowPassword);
        spanShowPassword.appendChild(labelShowPassword);

        let liPassMenu = document.createElement('li');
        liPassMenu.classList.add('linkPassMenu', 'hidden');
        let spanPassMenu = document.createElement('span');
        spanPassMenu.classList.add('menuitem', 'icon-share-pass');
        let inputPassMenu = document.createElement('input');
        inputPassMenu.type = 'password';
        inputPassMenu.placeholder = 'Choose a password for the public link';
        inputPassMenu.id = 'linkPassText-' + id;
        inputPassMenu.classList.add('linkPassText');
        let inputPassConfirm = document.createElement('input');
        inputPassConfirm.type = 'submit';
        inputPassConfirm.dataset.id = id;
        inputPassConfirm.value = '';
        inputPassConfirm.classList.add('icon-confirm', 'share-pass-submit');
        inputPassConfirm.setAttribute('style', 'width: auto !important');
        inputPassConfirm.addEventListener('click', OCA.Data.Sidebar.Share.updateShare);
        liPassMenu.appendChild(spanPassMenu);
        spanPassMenu.appendChild(inputPassMenu);
        spanPassMenu.appendChild(inputPassConfirm);

        let liUnshare = document.createElement('li');
        let aUnshare = document.createElement('a');
        aUnshare.href = '#';
        aUnshare.classList.add('unshare');
        aUnshare.dataset.id = id;
        aUnshare.addEventListener('click', OCA.Data.Sidebar.Share.removeShare);
        let spanUnshare = document.createElement('span');
        spanUnshare.classList.add('icon', 'icon-delete');
        let spanUnshareTxt = document.createElement('span');
        spanUnshareTxt.innerText = 'Delete share link';
        liUnshare.appendChild(aUnshare);
        aUnshare.appendChild(spanUnshare);
        aUnshare.appendChild(spanUnshareTxt);

        ul.appendChild(liShowPassword);
        ul.appendChild(liPassMenu);
        ul.appendChild(liUnshare);

        if (pw) {
            liPassMenu.classList.remove('hidden');
            inputShowPassword.checked = true;
        }
        return shareMenu;
    },

    showShareMenu: function (evt) {
        let toggleState = evt.target.nextSibling.style.display;
        if (toggleState === 'none') evt.target.nextSibling.style.display = 'block';
        else evt.target.nextSibling.style.display = 'none';
    },

    showPassMenu: function (evt) {
        let toggleState = evt.target.checked;
        if (toggleState === true) evt.target.parentNode.parentNode.nextSibling.classList.remove('hidden');
        else evt.target.parentNode.parentNode.nextSibling.classList.add('hidden');
    },

    createLinkShare: function () {
        let datasetId = document.getElementById('app-sidebar').dataset.id;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/share'),
            data: {
                'datasetId': datasetId,
                'type': OCA.Data.SHARE_TYPE_LINK,
            },
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }.bind()
        });
    },

    removeShare: function (evt) {
        let shareId = evt.target.parentNode.dataset.id;
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/share/') + shareId,
            success: function (data) {
                document.querySelector('.tabHeader.selected').click();
            }.bind()
        });
    },

    updateShare: function (evt) {
        let shareId = evt.target.dataset.id;
        let password = evt.target.previousSibling.value;
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/share/') + shareId,
            data: {
                'password': password
            },
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }.bind()
        });
    },

};

OCA.Data.Sidebar.Backend = {

    deleteDataset: function (datasetId) {
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
            success: function (data) {
                OCA.Data.Core.initNavigation();
            }.bind()
        });
    },

    updateDataset: function () {
        let datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
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
            success: function () {
                OCA.Data.Core.initNavigation();
            }.bind()
        });
    },

    updateData: function () {
        let datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/data/') + datasetId,
            data: {
                'dimension1': document.getElementById('DataDimension1').value,
                'dimension2': document.getElementById('DataDimension2').value,
                'dimension3': document.getElementById('DataDimension3').value,
            },
            success: function () {
                //document.querySelector('#navigationDatasets [data-id="' + datasetId + '"]').click();
                OCA.Data.UI.resetContent();
                OCA.Data.Backend.getData();
            }
        });
    },

    importCsvData: function () {
        let datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        let button = document.getElementById('importDataClipboardButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/data/importCSV'),
            data: {
                'datasetId': datasetId,
                'import': document.getElementById('importDataClipboardText').value,
            },
            success: function (data) {
                OCA.Data.UI.notification('success', data.insert + ' records inserted, ' + data.update + ' records updated');
                button.classList.remove('loading');
                button.disabled = false;
                document.querySelector('#navigationDatasets [data-id="' + datasetId + '"]').click();
            }.bind(),
            error: function () {
                OCA.Data.UI.notification('error', 'Technical error. Please check the logs');
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    },

    importFileData: function (path) {
        let datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        let button = document.getElementById('importDataFileButton');
        button.classList.add('loading');
        button.disabled = true;

        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/data/importFile'),
            data: {
                'datasetId': datasetId,
                'path': path,
            },
            success: function (data) {
                OCA.Data.UI.notification('success', data.insert + ' records inserted, ' + data.update + ' records updated');
                button.classList.remove('loading');
                button.disabled = false;
                document.querySelector('#navigationDatasets [data-id="' + datasetId + '"]').click();
            }.bind(),
            error: function () {
                OCA.Data.UI.notification('error', 'Technical error. Please check the logs');
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    },

};
