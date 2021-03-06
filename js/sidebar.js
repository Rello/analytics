/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2021 Marcel Scherello
 */

'use strict';

/**
 * @namespace OCA.Analytics.Sidebar
 */
OCA.Analytics.Sidebar = {
    sidebar_tabs: {},

    showSidebar: function (evt) {
        let navigationItem = evt.target;
        if (navigationItem.dataset.id === undefined) navigationItem = evt.target.closest('div');
        const datasetId = navigationItem.dataset.id;
        const datasetType = navigationItem.dataset.type;
        let appsidebar = document.getElementById('app-sidebar');

        if (appsidebar.dataset.id === datasetId && document.getElementById('advanced').value === 'false') {
            OCA.Analytics.Sidebar.hideSidebar();
        } else {
            document.getElementById('sidebarTitle').innerText = navigationItem.dataset.name;
            OCA.Analytics.Sidebar.constructTabs(datasetType);
            document.getElementById('tabHeaderDataset').classList.add('selected');

            if (document.getElementById('advanced').value === 'false') {
                if (appsidebar.dataset.id === '') {
                    $('#sidebarClose').on('click', OCA.Analytics.Sidebar.hideSidebar);
                    OC.Apps.showAppSidebar();
                }
            } else {
                document.getElementById('analytics-intro').classList.add('hidden');
                document.getElementById('analytics-content').removeAttribute('hidden');
                OC.Apps.showAppSidebar();
            }
            appsidebar.dataset.id = datasetId;
            appsidebar.dataset.type = datasetType;
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

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderDataset',
            class: 'tabContainerDataset',
            tabindex: '1',
            name: t('analytics', 'Report'),
            action: OCA.Analytics.Sidebar.Dataset.tabContainerDataset,
        });

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderData',
            class: 'tabContainerData',
            tabindex: '4',
            name: t('analytics', 'Data'),
            action: OCA.Analytics.Sidebar.Data.tabContainerData,
        });

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderShare',
            class: 'tabContainerShare',
            tabindex: '5',
            name: t('analytics', 'Share'),
            action: OCA.Analytics.Sidebar.Share.tabContainerShare,
        });

        let items = _.map(OCA.Analytics.Sidebar.sidebar_tabs, function (item) {
            return item;
        });
        items.sort(OCA.Analytics.Sidebar.sortByName);

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
            tabs[i].classList.add('hidden');
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
OCA.Analytics.Sidebar.Dataset = {
    metadataChanged: false,

    tabContainerDataset: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;
        OCA.Analytics.Sidebar.Dataset.metadataChanged = false;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderDataset').classList.add('selected');
        document.getElementById('tabContainerDataset').classList.remove('hidden');
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

                    document.getElementById('sidebarDatasetDatasource').appendChild(OCA.Analytics.Datasource.buildDropdown());
                    OCA.Analytics.Sidebar.Dataset.buildGroupingDropdown();

                    document.getElementById('sidebarDatasetName').value = data['name'];
                    document.getElementById('sidebarDatasetSubheader').value = data['subheader'];
                    document.getElementById('sidebarDatasetParent').value = data['parent'];
                    document.getElementById('sidebarDatasetDatasource').value = data['type'];
                    document.getElementById('sidebarDatasetDatasource').addEventListener('change', OCA.Analytics.Sidebar.Dataset.handleDatasourceChange);
                    document.getElementById('sidebarDatasetChart').value = data['chart'];
                    document.getElementById('sidebarDatasetVisualization').value = data['visualization'];
                    document.getElementById('sidebarDatasetChartOptions').value = data['chartoptions'];
                    document.getElementById('sidebarDatasetDataOptions').value = data['dataoptions'];
                    document.getElementById('sidebarDatasetDimension1').value = data['dimension1'];
                    document.getElementById('sidebarDatasetDimension2').value = data['dimension2'];
                    document.getElementById('sidebarDatasetValue').value = data['value'];
                    document.getElementById('sidebarDatasetDeleteButton').addEventListener('click', OCA.Analytics.Sidebar.Dataset.handleDeleteButton);
                    document.getElementById('sidebarDatasetUpdateButton').addEventListener('click', OCA.Analytics.Sidebar.Dataset.handleUpdateButton);
                    document.getElementById('sidebarDatasetExportButton').addEventListener('click', OCA.Analytics.Sidebar.Dataset.handleExportButton);

                    document.getElementById('sidebarDatasetName').addEventListener('change', OCA.Analytics.Sidebar.Dataset.indicateMetadataChanged);
                    document.getElementById('sidebarDatasetParent').addEventListener('change', OCA.Analytics.Sidebar.Dataset.indicateMetadataChanged);

                    // get all the options for a datasource
                    OCA.Analytics.Sidebar.Dataset.handleDatasourceChange();

                    // set the options for a datasource
                    if (data['link'] && data['link'].substring(0, 1) === '{') { // New format as of 3.1.0
                        let options = JSON.parse(data['link']);
                        for (let option in options) {
                            document.getElementById(option) ? document.getElementById(option).value = options[option] : null;
                        }
                    } else if ((parseInt(data['type']) === OCA.Analytics.TYPE_GIT)) { // Old format before 3.1.0
                        document.getElementById('user').value = data['link'].split('/')[0];
                        document.getElementById('repository').value = data['link'].split('/')[1];
                    } else if ((parseInt(data['type']) === OCA.Analytics.TYPE_EXTERNAL_FILE)) { // Old format before 3.1.0
                        document.getElementById('link').value = data['link'];
                    } else if ((parseInt(data['type']) === OCA.Analytics.TYPE_INTERNAL_FILE)) { // Old format before 3.1.0
                        document.getElementById('link').value = data['link'];
                    }

                    if (OCA.Analytics.Navigation.newReportId === data['id']) {
                        OCA.Analytics.Sidebar.indicateImportantField('sidebarDatasetDatasource');
                        OCA.Analytics.Sidebar.indicateImportantField('sidebarDatasetName');
                    }

                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No maintenance possible') + '</p></div>';
                    document.getElementById('tabContainerDataset').innerHTML = table;
                }
            }
        });
    },

    indicateMetadataChanged: function () {
        OCA.Analytics.Sidebar.Dataset.metadataChanged = true;
    },


    handleDeleteButton: function (evt) {
        let id = evt.target.parentNode.dataset.id;
        if (id === undefined) id = document.getElementById('app-sidebar').dataset.id;

        OC.dialogs.confirm(
            t('analytics', 'Are you sure?') + ' ' + t('analytics', 'All data will be deleted!'),
            t('analytics', 'Delete report'),
            function (e) {
                if (e === true) {
                    OCA.Analytics.Sidebar.Backend.deleteDataset(id);
                    OCA.Analytics.UI.resetContent();
                    OCA.Analytics.Sidebar.hideSidebar();
                }
            },
            true
        );
    },

    handleUpdateButton: function () {
        OCA.Analytics.Sidebar.Backend.updateDataset();
    },

    handleExportButton: function () {
        OCA.Analytics.Sidebar.Backend.exporteDataset();
    },

    handleDatasourceChange: function () {
        const type = parseInt(document.getElementById('sidebarDatasetDatasource').value);
        document.getElementById('datasetDatasourceSection').innerHTML = '';
        //OCA.Analytics.Sidebar.Dataset.indicateMetadataChanged();

        if (type === OCA.Analytics.TYPE_INTERNAL_DB) {
            document.getElementById('datasetDimensionSection').style.display = 'table';
            document.getElementById('datasetDimensionSectionHeader').style.removeProperty('display');
            document.getElementById('datasetDatasourceSection').style.display = 'none';
            document.getElementById('datasetDatasourceSectionHeader').style.display = 'none';
            document.getElementById('datasetVisualizationSection').style.display = 'table';
            document.getElementById('datasetVisualizationSectionHeader').style.removeProperty('display');
        } else if (type === OCA.Analytics.TYPE_EMPTY_GROUP) {
            document.getElementById('datasetDimensionSection').style.display = 'none';
            document.getElementById('datasetDimensionSectionHeader').style.display = 'none';
            document.getElementById('datasetDatasourceSection').style.display = 'none';
            document.getElementById('datasetDatasourceSectionHeader').style.display = 'none';
            document.getElementById('datasetVisualizationSection').style.display = 'none';
            document.getElementById('datasetVisualizationSectionHeader').style.display = 'none';
        } else {
            document.getElementById('datasetDimensionSection').style.display = 'none';
            document.getElementById('datasetDimensionSectionHeader').style.display = 'none';
            document.getElementById('datasetDatasourceSection').style.display = 'table';
            document.getElementById('datasetDatasourceSectionHeader').style.removeProperty('display');
            document.getElementById('datasetVisualizationSection').style.display = 'table';
            document.getElementById('datasetVisualizationSectionHeader').style.removeProperty('display');

            document.getElementById('datasetDatasourceSection').appendChild(OCA.Analytics.Datasource.buildOptionsForm(type));

            if (type === OCA.Analytics.TYPE_INTERNAL_FILE || type === OCA.Analytics.TYPE_EXCEL) {
                document.getElementById('link').addEventListener('click', OCA.Analytics.Sidebar.Dataset.handleFilepicker);
            }
        }
    },

    handleFilepicker: function () {
        let type;
        if (document.getElementById('dataloadDetail') !== null) {
            let dataloadId = document.getElementById('dataloadDetail').dataset.id;
            type = OCA.Analytics.Advanced.Dataload.dataloadArray.find(x => parseInt(x.id) === parseInt(dataloadId));
        } else {
            type = parseInt(document.getElementById('app-sidebar').dataset.type);
        }

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

    buildGroupingDropdown: function () {
        let tableParent = document.getElementById('sidebarDatasetParent');
        tableParent.innerHTML = '';
        let option = document.createElement('option');
        option.text = '';
        option.value = '0';
        tableParent.add(option);

        for (let dataset of OCA.Analytics.datasets) {
            if (parseInt(dataset.type) === OCA.Analytics.TYPE_EMPTY_GROUP) {
                option = document.createElement('option');
                option.text = dataset.name;
                option.value = dataset.id;
                tableParent.add(option);
            }
        }
    },
};

OCA.Analytics.Sidebar.Data = {

    tabContainerData: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderData').classList.add('selected');
        document.getElementById('tabContainerData').classList.remove('hidden');
        document.getElementById('tabContainerData').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        if (parseInt(document.getElementById('app-sidebar').dataset.type) !== OCA.Analytics.TYPE_INTERNAL_DB) {
            let message = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'Data maintenance is not possible for this type of report') + '</p></div>';
            document.getElementById('tabContainerData').innerHTML = message;
            return;
        }

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
            success: function (data) {
                let table;
                // clone the DOM template
                table = document.importNode(document.getElementById('templateData').content, true);
                table.id = 'tableData';
                document.getElementById('tabContainerData').innerHTML = '';
                document.getElementById('tabContainerData').appendChild(table);
                document.getElementById('DataTextDimension1').innerText = data['dimension1'];
                document.getElementById('DataTextDimension2').innerText = data['dimension2'];
                document.getElementById('DataTextValue').innerText = data['value'];
                //document.getElementById('DataTextvalue').addEventListener('keydown', OCA.Analytics.Sidebar.Data.handleDataInputEnter);
                document.getElementById('updateDataButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataUpdateButton);
                document.getElementById('deleteDataButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataDeleteButton);
                document.getElementById('importDataFileButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataImportFileButton);
                document.getElementById('importDataClipboardButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataImportClipboardButton);
                document.getElementById('advancedButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataAdvancedButton);
                document.getElementById('apiLink').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataApiButton);

                document.getElementById('DataValue').addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        OCA.Analytics.Sidebar.Backend.updateData();
                        document.getElementById('DataDimension2').focus();
                        document.getElementById('DataDimension2').value = '';
                        document.getElementById('DataValue').value = '';
                    }
                });
            }
        });
    },

    handleDataUpdateButton: function () {
        OCA.Analytics.Sidebar.Backend.updateData();
    },

    handleDataDeleteButton: function () {
        OCA.Analytics.Sidebar.Backend.deleteDataSimulate();
    },

    handleDataImportFileButton: function () {
        const mimeparts = ['text/csv', 'text/plain'];
        OC.dialogs.filepicker(t('analytics', 'Select file'), OCA.Analytics.Sidebar.Backend.importFileData.bind(this), false, mimeparts, true, 1);
    },

    handleDataImportClipboardButton: function () {
        document.getElementById('importDataClipboardText').attributes.removeNamedItem('hidden');
        document.getElementById('importDataClipboardButtonGo').attributes.removeNamedItem('hidden');
        document.getElementById('importDataClipboardButtonGo').addEventListener('click', OCA.Analytics.Sidebar.Backend.importCsvData);
    },

    handleDataApiButton: function () {
        OC.dialogs.message(
            t('analytics', 'Use this endpoint to submit data via an API:')
            + '<br><br>'
            + OC.generateUrl('/apps/analytics/api/2.0/adddata/')
            + document.getElementById('app-sidebar').dataset.id
            + '<br><br>'
            + t('analytics', 'Detail instructions at:') + '<br>https://github.com/rello/analytics/wiki/API',
            t('analytics', 'REST API parameters'),
            'info',
            OC.dialogs.OK_BUTTON,
            function (e) {
            },
            true,
            true
        );
    },

    handleDataAdvancedButton: function () {
        window.location = OC.generateUrl('apps/analytics/a/') + '#/r/' + document.getElementById('app-sidebar').dataset.id;
    },

};

OCA.Analytics.Sidebar.Share = {

    tabContainerShare: function () {
        const datasetId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderShare').classList.add('selected');
        document.getElementById('tabContainerShare').classList.remove('hidden');
        document.getElementById('tabContainerShare').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        // clone the DOM template
        let template = document.getElementById('templateSidebarShare').content;
        template = document.importNode(template, true);
        template.getElementById('linkShareList').appendChild(OCA.Analytics.Sidebar.Share.buildShareLinkRow(0, 0, true));
        template.getElementById('shareInput').addEventListener('keyup', OCA.Analytics.Sidebar.Share.searchShareeAPI);

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/share/') + datasetId,
            success: function (data) {

                if (data !== false) {
                    for (let share of data) {
                        if (parseInt(share.type) === OCA.Analytics.SHARE_TYPE_LINK) {
                            let li = OCA.Analytics.Sidebar.Share.buildShareLinkRow(parseInt(share['id']), share['token'], false, (String(share['pass']) === "true"), parseInt(share['permissions']));
                            template.getElementById('linkShareList').appendChild(li);
                        } else {
                            if (!share['displayName']) {
                                share['displayName'] = share['uid_owner'];
                            }
                            let li = OCA.Analytics.Sidebar.Share.buildShareeRow(parseInt(share['id']), share['uid_owner'], share['displayName'], parseInt(share['type']), false, parseInt(share['permissions']));
                            template.getElementById('shareeList').appendChild(li);
                        }
                    }

                    document.getElementById('tabContainerShare').innerHTML = '';
                    document.getElementById('tabContainerShare').appendChild(template);
                } else {
                    const table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No shares found') + '</p></div>';
                    document.getElementById('tabContainerData').innerHTML = table;
                }

            }
        });

    },

    buildShareLinkRow: function (id, token, add = false, pw = false, permissions = OC.PERMISSION_READ) {

        // clone the DOM template
        let linkRow = document.getElementById('templateSidebarShareLinkRow').content;
        linkRow = document.importNode(linkRow, true);

        linkRow.getElementById('row').dataset.id = id;
        if (add) linkRow.getElementById('shareLinkTitle').innerText = t('analytics', 'Add share link');
        else linkRow.getElementById('shareLinkTitle').innerText = t('analytics', 'Share link');

        if (add) {
            linkRow.getElementById('sharingOptionsGroupMenu').remove();
            linkRow.getElementById('newLinkShare').addEventListener('click', OCA.Analytics.Sidebar.Share.createShare);
        } else {
            linkRow.getElementById('sharingOptionsGroupNew').remove();
            linkRow.getElementById('shareClipboardLink').value = OC.getProtocol() + '://' + OC.getHostName() + OC.generateUrl('/apps/analytics/p/') + token;
            linkRow.getElementById('shareClipboard').addEventListener('click', OCA.Analytics.Sidebar.Share.handleShareClipboard)
            linkRow.getElementById('moreIcon').addEventListener('click', OCA.Analytics.Sidebar.Share.showShareMenu);
            linkRow.getElementById('showPassword').addEventListener('click', OCA.Analytics.Sidebar.Share.showPassMenu);
            linkRow.getElementById('showPassword').nextElementSibling.htmlFor = 'showPassword' + id;
            linkRow.getElementById('showPassword').id = 'showPassword' + id;
            linkRow.getElementById('linkPassSubmit').addEventListener('click', OCA.Analytics.Sidebar.Share.updateSharePassword);
            linkRow.getElementById('linkPassSubmit').dataset.id = id;
            linkRow.getElementById('deleteShareIcon').addEventListener('click', OCA.Analytics.Sidebar.Share.removeShare);
            linkRow.getElementById('deleteShare').dataset.id = id;
            if (pw) {
                linkRow.getElementById('linkPassMenu').classList.remove('hidden');
                linkRow.getElementById('showPassword' + id).checked = true;
            }
            linkRow.getElementById('shareEditing').addEventListener('click', OCA.Analytics.Sidebar.Share.updateShareCanEdit);
            linkRow.getElementById('shareEditing').dataset.id = id;
            linkRow.getElementById('shareEditing').nextElementSibling.htmlFor = 'shareEditing' + id;
            linkRow.getElementById('shareEditing').id = 'shareEditing' + id;
            if (permissions === OC.PERMISSION_UPDATE) {
                linkRow.getElementById('shareEditing' + id).checked = true;
            }

        }
        return linkRow;
    },

    buildShareeRow: function (id, uid_owner, user_label, shareType = null, isSearch = false, permissions = OC.PERMISSION_READ) {

        // clone the DOM template
        let shareeRow = document.getElementById('templateSidebarShareShareeRow').content;
        shareeRow = document.importNode(shareeRow, true);

        shareeRow.getElementById('row').dataset.id = id;
        shareeRow.getElementById('avatar').innerText = uid_owner.charAt(0);
        shareeRow.getElementById('username').dataset.shareType = shareType;
        shareeRow.getElementById('username').dataset.user = uid_owner;
        shareeRow.getElementById('icon-more').addEventListener('click', OCA.Analytics.Sidebar.Share.showShareMenu);
        shareeRow.getElementById('deleteShareIcon').addEventListener('click', OCA.Analytics.Sidebar.Share.removeShare);
        shareeRow.getElementById('deleteShare').dataset.id = id;
        shareeRow.getElementById('shareEditing').addEventListener('click', OCA.Analytics.Sidebar.Share.updateShareCanEdit);
        shareeRow.getElementById('shareEditing').dataset.id = id;
        if (permissions === OC.PERMISSION_UPDATE) {
            shareeRow.getElementById('shareEditing').checked = true;
        }

        if (isSearch) {
            shareeRow.getElementById('username').addEventListener('click', OCA.Analytics.Sidebar.Share.createShare);
            shareeRow.getElementById('row').classList.add('shareSearchResultItem');
            shareeRow.getElementById('shareMenu').style.visibility = 'hidden';
        }
        if (shareType === OCA.Analytics.SHARE_TYPE_GROUP) {
            shareeRow.getElementById('icon').classList.add('icon-group');
            shareeRow.getElementById('username').innerText = user_label + ' (group)';
        } else if (shareType === OCA.Analytics.SHARE_TYPE_ROOM) {
            shareeRow.getElementById('icon').classList.add('icon-room');
            shareeRow.getElementById('username').innerText = user_label + ' (conversation)';
        } else if (shareType === OCA.Analytics.SHARE_TYPE_USER) {
            shareeRow.getElementById('username').innerText = user_label;
            let userIcon = document.createElement('img');
            userIcon.setAttribute('src', OC.generateUrl('/avatar/' + uid_owner + '/32'));
            shareeRow.getElementById('avatar').innerText = '';
            shareeRow.getElementById('avatar').appendChild(userIcon);
        }

        //shareType !== null ? li.appendChild(ShareIconGroup) : li.appendChild(ShareOptionsGroup);
        return shareeRow;
    },

    handleShareClipboard: function (evt) {
        let link = evt.target.nextElementSibling.value;
        evt.target.classList.replace('icon-clippy', 'icon-checkmark-color');
        let textArea = document.createElement('textArea');
        textArea.value = link;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
    },

    showShareMenu: function (evt) {
        const toggleState = evt.target.nextElementSibling.style.display;
        if (toggleState === 'none') evt.target.nextElementSibling.style.display = 'block';
        else evt.target.nextElementSibling.style.display = 'none';
    },

    showPassMenu: function (evt) {
        const toggleState = evt.target.checked;
        if (toggleState === true) evt.target.parentNode.parentNode.nextElementSibling.classList.remove('hidden');
        else evt.target.parentNode.parentNode.nextElementSibling.classList.add('hidden');
    },

    createShare: function (evt) {
        const datasetId = document.getElementById('app-sidebar').dataset.id;
        let shareType = evt.target.dataset.shareType;
        let shareUser = evt.target.dataset.user;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/share'),
            data: {
                'datasetId': datasetId,
                'type': shareType,
                'user': shareUser,
            },
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    removeShare: function (evt) {
        const shareId = evt.target.parentNode.dataset.id;
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/share/') + shareId,
            success: function (data) {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    updateSharePassword: function (evt) {
        const shareId = evt.target.dataset.id;
        const password = evt.target.previousElementSibling.value;
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/share/') + shareId,
            data: {
                'password': password
            },
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    updateShareCanEdit: function (evt) {
        const shareId = evt.target.dataset.id;
        const canEdit = evt.target.checked;
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/share/') + shareId,
            data: {
                'canEdit': canEdit
            },
            success: function () {
                document.querySelector('.tabHeader.selected').click();
            }
        });
    },

    searchShareeAPI: function () {
        let shareInput = document.getElementById('shareInput').value;
        document.getElementById('shareSearchResult').innerHTML = '';
        if (shareInput === '') {
            document.getElementById('shareSearchResult').style.display = 'none';
            return;
        }
        let URL = OC.linkToOCS('apps/files_sharing/api/v1/sharees').slice(0, -1);

        let params = 'format=json'
            + '&itemType=file'
            + '&search=' + shareInput
            + '&lookup=false&perPage=200'
            + '&shareType[]=0&shareType[]=1';
        //    + '&shareType[]=10';

        let xhr = new XMLHttpRequest();
        xhr.open('GET', URL + '?' + params, true);
        xhr.setRequestHeader('requesttoken', OC.requestToken);
        xhr.setRequestHeader('OCS-APIREQUEST', 'true');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                var jsondata = JSON.parse(xhr.response);
                if (jsondata['ocs']['meta']['status'] === 'ok') {
                    document.getElementById('shareSearchResult').style.display = '';
                    for (let user of jsondata['ocs']['data']['users']) {
                        let sharee = OCA.Analytics.Sidebar.Share.buildShareeRow(0, user['value']['shareWith'], user['label'], OCA.Analytics.SHARE_TYPE_USER, true);
                        document.getElementById('shareSearchResult').appendChild(sharee);
                    }
                    for (let exactUser of jsondata['ocs']['data']['exact']['users']) {
                        let sharee = OCA.Analytics.Sidebar.Share.buildShareeRow(0, exactUser['value']['shareWith'], exactUser['label'], OCA.Analytics.SHARE_TYPE_USER, true);
                        document.getElementById('shareSearchResult').appendChild(sharee);
                    }
                    for (let group of jsondata['ocs']['data']['groups']) {
                        let sharee = OCA.Analytics.Sidebar.Share.buildShareeRow(0, group['value']['shareWith'], group['label'], OCA.Analytics.SHARE_TYPE_GROUP, true);
                        document.getElementById('shareSearchResult').appendChild(sharee);
                    }
                    for (let exactGroup of jsondata['ocs']['data']['exact']['groups']) {
                        let sharee = OCA.Analytics.Sidebar.Share.buildShareeRow(0, exactGroup['value']['shareWith'], exactGroup['label'], OCA.Analytics.SHARE_TYPE_GROUP, true);
                        document.getElementById('shareSearchResult').appendChild(sharee);
                    }
                    for (let room of jsondata['ocs']['data']['rooms']) {
                        let sharee = OCA.Analytics.Sidebar.Share.buildShareeRow(0, room['value']['shareWith'], room['label'], OCA.Analytics.SHARE_TYPE_ROOM, true);
                        document.getElementById('shareSearchResult').appendChild(sharee);
                    }
                } else {
                }
            }
        };

        xhr.send();

    },
};

OCA.Analytics.Sidebar.Backend = {

    exporteDataset: function () {
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        window.open(OC.generateUrl('apps/analytics/dataset/export/') + datasetId, '_blank')
    },

    deleteDataset: function (datasetId) {
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
            success: function (data) {
                OCA.Analytics.Navigation.init();
            }
        });
    },

    updateDataset: function () {
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);

        let option = {};
        let inputFields = document.querySelectorAll('#datasetDatasourceSection input, #datasetDatasourceSection select');
        for (let inputField of inputFields) {
            option[inputField.id] = inputField.value;
        }
        option = JSON.stringify(option);

        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/dataset/') + datasetId,
            data: {
                'name': document.getElementById('sidebarDatasetName').value,
                'subheader': document.getElementById('sidebarDatasetSubheader').value,
                'parent': document.getElementById('sidebarDatasetParent').value,
                'type': document.getElementById('sidebarDatasetDatasource').value,
                'link': option,
                'visualization': document.getElementById('sidebarDatasetVisualization').value,
                'chart': document.getElementById('sidebarDatasetChart').value,
                'chartoptions': document.getElementById('sidebarDatasetChartOptions').value,
                'dataoptions': document.getElementById('sidebarDatasetDataOptions').value,
                'dimension1': document.getElementById('sidebarDatasetDimension1').value,
                'dimension2': document.getElementById('sidebarDatasetDimension2').value,
                'value': document.getElementById('sidebarDatasetValue').value
            },
            success: function () {
                OCA.Analytics.Navigation.newReportId = 0;
                OCA.Analytics.Sidebar.resetImportantFields();
                if (OCA.Analytics.Sidebar.Dataset.metadataChanged === true) {
                    OCA.Analytics.Sidebar.Dataset.metadataChanged = false;
                    OCA.Analytics.Navigation.init(datasetId);
                } else {
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContent();
                        OCA.Analytics.Backend.getData();
                    } else {
                        OCA.Analytics.UI.notification('success', t('analytics', 'Saved'));
                    }
                }
            }
        });
    },

    updateData: function () {
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('updateDataButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/data/') + datasetId,
            data: {
                'dimension1': document.getElementById('DataDimension1').value,
                'dimension2': document.getElementById('DataDimension2').value,
                'value': document.getElementById('DataValue').value,
            },
            success: function (data) {
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.UI.notification('success', data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContent();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.UI.notification('error', data.error);
                }
            }
        });
    },

    deleteDataSimulate: function () {
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('deleteDataButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/data/deleteDataSimulate'),
            data: {
                'datasetId': datasetId,
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
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('deleteDataButton');
        button.classList.add('loading');
        button.disabled = true;
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/data/') + datasetId,
            data: {
                'dimension1': document.getElementById('DataDimension1').value,
                'dimension2': document.getElementById('DataDimension2').value,
            },
            success: function (data) {
                button.classList.remove('loading');
                button.disabled = false;
                if (document.getElementById('advanced').value === 'false') {
                    OCA.Analytics.UI.resetContent();
                    OCA.Analytics.Backend.getData();
                }
            }
        });
    },

    importCsvData: function () {
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('importDataClipboardButton');
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
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.UI.notification('success', data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContent();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.UI.notification('error', data.error);
                }
            },
            error: function () {
                OCA.Analytics.UI.notification('error', t('analytics', 'Technical error. Please check the logs'));
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    },

    importFileData: function (path) {
        const datasetId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('importDataFileButton');
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
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.UI.notification('success', data.insert + t('analytics', ' records inserted, ') + data.update + t('analytics', ' records updated'));
                    if (document.getElementById('advanced').value === 'false') {
                        OCA.Analytics.UI.resetContent();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.UI.notification('error', data.error);
                }
            },
            error: function () {
                OCA.Analytics.UI.notification('error', t('analytics', 'Technical error. Please check the logs'));
                button.classList.remove('loading');
                button.disabled = false;
            }
        });
    },

    testButton: function (thresholdId) {

        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/load'),
            data: {
                'dataloadId': 1,
            },
            success: function (data) {
            }
        });
    },
};