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

        if (appsidebar.dataset.id === datasetId && !OCA.Analytics.isAdvanced) {
            OCA.Analytics.Sidebar.hideSidebar();
        } else {
            document.getElementById('sidebarTitle').innerText = navigationItem.dataset.name;
            OCA.Analytics.Sidebar.constructTabs(datasetType);

            if (!OCA.Analytics.isAdvanced) {
                if (appsidebar.dataset.id === '') {
                    $('#sidebarClose').on('click', OCA.Analytics.Sidebar.hideSidebar);
                    OC.Apps.showAppSidebar();
                }
            } else {
                OCA.Analytics.UI.hideElement('analytics-intro');
                OCA.Analytics.UI.showElement('analytics-content');
                OC.Apps.showAppSidebar();
            }
            appsidebar.dataset.id = datasetId;
            appsidebar.dataset.type = datasetType;

            document.getElementById('tabHeaderReport').classList.add('selected');
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
            id: 'tabHeaderReport',
            class: 'tabContainerReport',
            tabindex: '1',
            name: t('analytics', 'Report'),
            action: OCA.Analytics.Sidebar.Report.tabContainerReport,
        });

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderData',
            class: 'tabContainerData',
            tabindex: '2',
            name: t('analytics', 'Data'),
            action: OCA.Analytics.Sidebar.Data.tabContainerData,
        });

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderThreshold',
            class: 'tabContainerThreshold',
            tabindex: '3',
            name: t('analytics', 'Thresholds'),
            action: OCA.Analytics.Sidebar.Threshold.tabContainerThreshold,
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

OCA.Analytics.Sidebar.Report = {
    metadataChanged: false,

    tabContainerReport: function () {
        const reportId = document.getElementById('app-sidebar').dataset.id;
        OCA.Analytics.Sidebar.Report.metadataChanged = false;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderReport').classList.add('selected');
        OCA.Analytics.UI.showElement('tabContainerReport');
        document.getElementById('tabContainerReport').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/report/') + reportId,
            success: function (data) {
                let table;
                if (data !== false) {
                    // clone the DOM template
                    table = document.importNode(document.getElementById('templateReport').content, true);
                    table.id = 'sidebarReport';
                    document.getElementById('tabContainerReport').innerHTML = '';
                    document.getElementById('tabContainerReport').appendChild(table);

                    document.getElementById('sidebarReportDatasource').appendChild(OCA.Analytics.Datasource.buildDropdown());
                    OCA.Analytics.Sidebar.Report.buildGroupingDropdown();
                    OCA.Analytics.Sidebar.Report.buildDatasetDropdown();

                    document.getElementById('sidebarReportName').value = data['name'];
                    document.getElementById('sidebarReportSubheader').value = data['subheader'];
                    document.getElementById('sidebarReportParent').value = data['parent'];
                    document.getElementById('sidebarReportDatasource').value = data['type'];
                    document.getElementById('sidebarReportDatasource').addEventListener('change', OCA.Analytics.Sidebar.Report.handleDatasourceChange);
                    document.getElementById('sidebarReportDataset').value = data['dataset'];
                    document.getElementById('sidebarReportDataset').addEventListener('change', OCA.Analytics.Sidebar.Report.handleDatasetChange);
                    document.getElementById('sidebarReportChart').value = data['chart'];
                    document.getElementById('sidebarReportVisualization').value = data['visualization'];
                    // {"scales":{"yAxes":[{},{"display":true}]}} => {"scales":{"secondary":{"display":true}}}
                    if (data['chartoptions'] !== null) {
                        document.getElementById('sidebarReportChartOptions').value = data['chartoptions'].replace('{"yAxes":[{},{"display":true}]}', '{"secondary":{"display":true}}');
                    }
                    document.getElementById('sidebarReportDataOptions').value = data['dataoptions'];
                    document.getElementById('sidebarReportDimension1').value = data['dimension1'];
                    document.getElementById('sidebarReportDimension2').value = data['dimension2'];
                    document.getElementById('sidebarReportValue').value = data['value'];
                    document.getElementById('sidebarReportDeleteButton').addEventListener('click', OCA.Analytics.Sidebar.Report.handleDeleteButton);
                    document.getElementById('sidebarReportUpdateButton').addEventListener('click', OCA.Analytics.Sidebar.Report.handleUpdateButton);
                    document.getElementById('sidebarReportExportButton').addEventListener('click', OCA.Analytics.Sidebar.Report.handleExportButton);

                    document.getElementById('sidebarReportName').addEventListener('change', OCA.Analytics.Sidebar.Report.indicateMetadataChanged);
                    document.getElementById('sidebarReportParent').addEventListener('change', OCA.Analytics.Sidebar.Report.indicateMetadataChanged);

                    document.getElementById('sidebarReportNameHint').addEventListener('click', OCA.Analytics.Sidebar.Report.handleNameHint);
                    document.getElementById('sidebarReportSubheaderHint').addEventListener('click', OCA.Analytics.Sidebar.Report.handleNameHint);

                    // get all the options for a datasource
                    OCA.Analytics.Sidebar.Report.handleDatasourceChange();

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

                    if (OCA.Analytics.Navigation.newReportId === parseInt(data['id'])) {
                        OCA.Analytics.Sidebar.indicateImportantField('sidebarReportDatasource');
                        OCA.Analytics.Sidebar.indicateImportantField('sidebarReportName');
                        document.getElementById('sidebarReportDatasource').disabled = false;
                        document.getElementById('sidebarReportDataset').disabled = false;
                    }

                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No maintenance possible') + '</p></div>';
                    document.getElementById('tabContainerReport').innerHTML = table;
                }
            }
        });
    },

    indicateMetadataChanged: function () {
        OCA.Analytics.Sidebar.Report.metadataChanged = true;
    },

    handleDeleteButton: function (evt) {
        let id = evt.target.parentNode.dataset.id;
        if (id === undefined) id = document.getElementById('app-sidebar').dataset.id;

        OC.dialogs.confirm(
            t('analytics', 'Are you sure?') + ' ' + t('analytics', 'All data will be deleted!'),
            t('analytics', 'Delete'),
            function (e) {
                if (e === true) {
                    OCA.Analytics.Sidebar.Backend.deleteReport(id);
                    OCA.Analytics.UI.resetContentArea();
                    OCA.Analytics.Sidebar.hideSidebar();
                }
            },
            true
        );
    },

    handleUpdateButton: function () {
        OCA.Analytics.Sidebar.Backend.updateReport();
    },

    handleExportButton: function () {
        OCA.Analytics.Sidebar.Backend.exporteReport();
    },

    handleDatasourceChange: function () {
        const type = parseInt(document.getElementById('sidebarReportDatasource').value);
        document.getElementById('reportDatasourceSection').innerHTML = '';
        OCA.Analytics.Sidebar.Report.indicateMetadataChanged();

        if (type === OCA.Analytics.TYPE_INTERNAL_DB) {
            document.getElementById('reportDimensionSection').style.display = 'table';
            document.getElementById('reportDimensionSectionHeader').style.removeProperty('display');
            document.getElementById('reportDatasourceSection').style.display = 'none';
            document.getElementById('reportDatasourceSectionHeader').style.display = 'none';
            document.getElementById('reportVisualizationSection').style.display = 'table';
            document.getElementById('reportVisualizationSectionHeader').style.removeProperty('display');
            document.getElementById('sidebarReportDatasetRow').style.display = 'table-row';
        } else if (type === OCA.Analytics.TYPE_EMPTY_GROUP) {
            document.getElementById('reportDimensionSection').style.display = 'none';
            document.getElementById('reportDimensionSectionHeader').style.display = 'none';
            document.getElementById('reportDatasourceSection').style.display = 'none';
            document.getElementById('reportDatasourceSectionHeader').style.display = 'none';
            document.getElementById('reportVisualizationSection').style.display = 'none';
            document.getElementById('reportVisualizationSectionHeader').style.display = 'none';
            document.getElementById('sidebarReportDatasetRow').style.display = 'none';
        } else {
            document.getElementById('reportDimensionSection').style.display = 'none';
            document.getElementById('reportDimensionSectionHeader').style.display = 'none';
            document.getElementById('reportDatasourceSection').style.display = 'table';
            document.getElementById('reportDatasourceSectionHeader').style.removeProperty('display');
            document.getElementById('reportVisualizationSection').style.display = 'table';
            document.getElementById('reportVisualizationSectionHeader').style.removeProperty('display');
            document.getElementById('sidebarReportDatasetRow').style.display = 'none';

            document.getElementById('reportDatasourceSection').appendChild(OCA.Analytics.Datasource.buildOptionsForm(type));

            if (type === OCA.Analytics.TYPE_INTERNAL_FILE || type === OCA.Analytics.TYPE_EXCEL) {
                document.getElementById('link').addEventListener('click', OCA.Analytics.Sidebar.Report.handleFilepicker);
            }
        }
    },

    handleDatasetChange: function () {
        const dataset = parseInt(document.getElementById('sidebarReportDataset').value);
        const datasource = parseInt(document.getElementById('sidebarReportDatasource').value);

        if (dataset === 0) {
            document.getElementById('sidebarReportDimension1').disabled = false;
            document.getElementById('sidebarReportDimension2').disabled = false;
            document.getElementById('sidebarReportValue').disabled = false;
        } else {
            let dim1 = OCA.Analytics.datasets.find(x => parseInt(x.id) === dataset)['dimension1'];
            document.getElementById('sidebarReportDimension1').value = dim1;
            let dim2 = OCA.Analytics.datasets.find(x => parseInt(x.id) === dataset)['dimension2'];
            document.getElementById('sidebarReportDimension2').value = dim2;
            let value = OCA.Analytics.datasets.find(x => parseInt(x.id) === dataset)['value'];
            document.getElementById('sidebarReportValue').value = value;
            if (datasource === 2) {
                document.getElementById('sidebarReportValue').disabled = true;
                document.getElementById('sidebarReportDimension2').disabled = true;
                document.getElementById('sidebarReportDimension1').disabled = true;
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

    handleNameHint: function () {
        let text = t('analytics', 'Text variables can be used in title and subheader.<br>They are replaced when the report is executed.') +
            '<br><br>' +
            '%lastUpdateDate%<br>' +
            '%lastUpdateTime%<br>' +
            '%currentDate%<br>' +
            '%currentTime%<br>' +
            '%owner%';
        OCA.Analytics.Notification.dialog(t('analytics', 'Text variables'), text, 'info');
    },

    buildGroupingDropdown: function () {
        let tableParent = document.getElementById('sidebarReportParent');
        tableParent.innerHTML = '';
        let option = document.createElement('option');
        option.text = '';
        option.value = '0';
        tableParent.add(option);

        for (let dataset of OCA.Analytics.reports) {
            if (parseInt(dataset.type) === OCA.Analytics.TYPE_EMPTY_GROUP) {
                option = document.createElement('option');
                option.text = dataset.name;
                option.value = dataset.id;
                tableParent.add(option);
            }
        }
    },

    buildDatasetDropdown: function () {
        let tableParent = document.getElementById('sidebarReportDataset');
        tableParent.innerHTML = '';
        let option = document.createElement('option');
        option.text = t('analytics', 'New table');
        option.value = '0';
        tableParent.add(option);

        OCA.Analytics.Backend.getDatasetDefinitions();
        for (let dataset of OCA.Analytics.datasets) {
            option = document.createElement('option');
            option.text = dataset.name;
            option.value = dataset.id;
            tableParent.add(option);
        }
    },

    createReport: function (file = '') {
        //ToDo: create separate one for creation from file
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/report'),
            data: {
                'file': file,
            },
            success: function (data) {
                OCA.Analytics.Navigation.newReportId = data;
                OCA.Analytics.Navigation.init(data);
            }
        });
    },

};

OCA.Analytics.Sidebar.Data = {

    tabContainerData: function () {
        const reportId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderData').classList.add('selected');
        OCA.Analytics.UI.showElement('tabContainerData');
        document.getElementById('tabContainerData').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        if (parseInt(document.getElementById('app-sidebar').dataset.type) !== OCA.Analytics.TYPE_INTERNAL_DB) {
            let message = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'Data maintenance is not possible for this type of report') + '</p></div>';
            document.getElementById('tabContainerData').innerHTML = message;
            return;
        }

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/report/') + reportId,
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
                document.getElementById('DataApiDataset').innerText = data['dataset'];
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
        OCA.Analytics.UI.showElement('importDataClipboardText');
        OCA.Analytics.UI.showElement('importDataClipboardButtonGo');
        document.getElementById('importDataClipboardButtonGo').addEventListener('click', OCA.Analytics.Sidebar.Backend.importCsvData);
    },

    handleDataApiButton: function () {
        OC.dialogs.message(
            t('analytics', 'Use this endpoint to submit data via an API:')
            + '<br><br>'
            + OC.generateUrl('/apps/analytics/api/3.0/data/')
            + document.getElementById('DataApiDataset').innerText
            + '/add<br><br>'
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
        const reportId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderShare').classList.add('selected');
        OCA.Analytics.UI.showElement('tabContainerShare');
        document.getElementById('tabContainerShare').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        // clone the DOM template
        let template = document.getElementById('templateSidebarShare').content;
        template = document.importNode(template, true);
        template.getElementById('linkShareList').appendChild(OCA.Analytics.Sidebar.Share.buildShareLinkRow(0, 0, true));
        template.getElementById('shareInput').addEventListener('keyup', OCA.Analytics.Sidebar.Share.searchShareeAPI);

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/share/') + reportId,
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
            linkRow.getElementById('shareOpenDirect').href = OC.generateUrl('/apps/analytics/p/') + token;
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
        OCA.Analytics.Notification.notification('success', t('analytics', 'Link copied'));
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
        const reportId = document.getElementById('app-sidebar').dataset.id;
        let shareType = evt.target.dataset.shareType;
        let shareUser = evt.target.dataset.user;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/analytics/share'),
            data: {
                'reportId': reportId,
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

OCA.Analytics.Sidebar.Threshold = {

    tabContainerThreshold: function () {
        const reportId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderThreshold').classList.add('selected');
        OCA.Analytics.UI.showElement('tabContainerThreshold');
        document.getElementById('tabContainerThreshold').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        $.ajax({
            type: 'GET',
            url: OC.generateUrl('apps/analytics/report/') + reportId,
            success: function (data) {
                // clone the DOM template
                let table = document.importNode(document.getElementById('templateThreshold').content, true);
                table.id = 'tableThreshold';
                document.getElementById('tabContainerThreshold').innerHTML = '';
                document.getElementById('tabContainerThreshold').appendChild(table);
                document.getElementById('sidebarThresholdTextDimension1').innerText = data.dimension1 || t('analytics', 'Column 1');
                document.getElementById('sidebarThresholdTextValue').innerText = data.value || t('analytics', 'Column 3');
                document.getElementById('createThresholdButton').addEventListener('click', OCA.Analytics.Sidebar.Threshold.handleThresholdCreateButton);
                if (parseInt(data.type) !== OCA.Analytics.TYPE_INTERNAL_DB) {
                    document.getElementById('sidebarThresholdSeverity').remove(0);
                }

                $.ajax({
                    type: 'GET',
                    url: OC.generateUrl('apps/analytics/threshold/') + reportId,
                    success: function (data) {
                        if (data !== false) {
                            document.getElementById('sidebarThresholdList').innerHTML = '';
                            for (let threshold of data) {
                                const li = OCA.Analytics.Sidebar.Threshold.buildThresholdRow(threshold);
                                document.getElementById('sidebarThresholdList').appendChild(li);
                            }
                        }
                    }
                });
            }
        });
    },

    handleThresholdCreateButton: function () {
        OCA.Analytics.Sidebar.Threshold.createThreashold();
    },

    handleThresholdDeleteButton: function (evt) {
        const thresholdId = evt.target.dataset.id;
        OCA.Analytics.Sidebar.Threshold.deleteThreshold(thresholdId);
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
        tDelete.addEventListener('click', OCA.Analytics.Sidebar.Threshold.handleThresholdDeleteButton);

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

OCA.Analytics.Sidebar.Backend = {

    exporteReport: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        window.open(OC.generateUrl('apps/analytics/report/export/') + reportId, '_blank')
    },

    deleteReport: function (reportId) {
        document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';
        $.ajax({
            type: 'DELETE',
            url: OC.generateUrl('apps/analytics/report/') + reportId,
            success: function (data) {
                OCA.Analytics.Navigation.init();
                OCA.Analytics.Navigation.handleOverviewButton();
            }
        });
    },

    updateReport: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('sidebarReportUpdateButton');
        button.classList.add('loading');
        button.disabled = true;

        let option = {};
        let inputFields = document.querySelectorAll('#reportDatasourceSection input, #reportDatasourceSection select');
        for (let inputField of inputFields) {
            option[inputField.id] = inputField.value;
        }

        $.ajax({
            type: 'PUT',
            url: OC.generateUrl('apps/analytics/report/') + reportId,
            data: {
                'name': document.getElementById('sidebarReportName').value,
                'subheader': document.getElementById('sidebarReportSubheader').value,
                'parent': document.getElementById('sidebarReportParent').value,
                'type': document.getElementById('sidebarReportDatasource').value,
                'dataset': document.getElementById('sidebarReportDataset').value,
                'link': JSON.stringify(option),
                'visualization': document.getElementById('sidebarReportVisualization').value,
                'chart': document.getElementById('sidebarReportChart').value,
                'chartoptions': document.getElementById('sidebarReportChartOptions').value,
                'dataoptions': document.getElementById('sidebarReportDataOptions').value,
                'dimension1': document.getElementById('sidebarReportDimension1').value,
                'dimension2': document.getElementById('sidebarReportDimension2').value,
                'value': document.getElementById('sidebarReportValue').value
            },
            success: function () {
                button.classList.remove('loading');
                button.disabled = false;

                OCA.Analytics.Navigation.newReportId = 0;
                OCA.Analytics.Sidebar.resetImportantFields();
                if (OCA.Analytics.Sidebar.Report.metadataChanged === true) {
                    OCA.Analytics.Sidebar.Report.metadataChanged = false;
                    OCA.Analytics.Navigation.init(reportId);
                    OCA.Analytics.Backend.getDatasetDefinitions();
                } else {
                    if (!OCA.Analytics.isAdvanced) {
                        OCA.Analytics.currentReportData.options.chartoptions = '';
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    } else {
                        OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
                    }
                }
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
                    if (!OCA.Analytics.isAdvanced) {
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
                if (!OCA.Analytics.isAdvanced) {
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
                    if (!OCA.Analytics.isAdvanced) {
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
                    if (!OCA.Analytics.isAdvanced) {
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