/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
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
            OCA.Analytics.Sidebar.close();
        } else {
            document.getElementById('sidebarTitle').innerText = navigationItem.dataset.name;
            OCA.Analytics.Sidebar.constructTabs(parseInt(datasetType));

            if (!OCA.Analytics.isAdvanced) {
                if (appsidebar.dataset.id === '') {
                    $('#sidebarClose').on('click', OCA.Analytics.Sidebar.close);
                    // OC.Apps not working anymore
                    appsidebar.classList.remove('disappear');
                }
            } else {
                OCA.Analytics.Visualization.hideElement('analytics-intro');
                OCA.Analytics.Visualization.showElement('analytics-content');
                appsidebar.classList.remove('disappear');
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

    constructTabs: function (datasetType) {

        document.querySelector('.tabHeaders').innerHTML = '';
        document.querySelector('.tabsContainer').innerHTML = '';

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderReport',
            class: 'tabContainerReport',
            tabindex: '1',
            name: t('analytics', 'Report'),
            action: OCA.Analytics.Sidebar.Report.tabContainerReport,
            validTypes: [],
            excludeTypes: [],
        });

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderData',
            class: 'tabContainerData',
            tabindex: '2',
            name: t('analytics', 'Data'),
            action: OCA.Analytics.Sidebar.Data.tabContainerData,
            validTypes: [OCA.Analytics.TYPE_INTERNAL_DB],
            excludeTypes: [],
        });

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderThreshold',
            class: 'tabContainerThreshold',
            tabindex: '3',
            name: t('analytics', 'Thresholds'),
            action: OCA.Analytics.Sidebar.Threshold.tabContainerThreshold,
            validTypes: [],
            excludeTypes: [OCA.Analytics.TYPE_GROUP],
        });

        OCA.Analytics.Sidebar.registerSidebarTab({
            id: 'tabHeaderShare',
            class: 'tabContainerShare',
            tabindex: '5',
            // TRANSLATORS Noun; headline in sidebar
            name: t('analytics', 'Share'),
            action: OCA.Analytics.Sidebar.Share.tabContainerShare,
            validTypes: [],
            excludeTypes: [OCA.Analytics.TYPE_SHARED],
        });

        let items = _.map(OCA.Analytics.Sidebar.sidebar_tabs, function (item) {
            return item;
        });
        items.sort(OCA.Analytics.Sidebar.sortByName);

        for (let tab in items) {

            // if tab can not be used for certain report types
            if (items[tab].excludeTypes.length !== 0 && items[tab].excludeTypes.includes(datasetType)) {
                continue;
            }
            // if tab can only be used for certain report types
            if (items[tab].validTypes.length !== 0 && !items[tab].validTypes.includes(datasetType)) {
                continue;
            }

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

    close: function () {
        document.getElementById('app-sidebar').dataset.id = '';
        document.getElementById('app-sidebar').classList.add('disappear');
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

    showHideSidebarSection: function (evt) {
        let header = evt.target.parentElement;
        let clickedHeaderClosed = header.classList.contains('sidebarHeaderClosed');
        let section = header.nextElementSibling;
        if (clickedHeaderClosed) {
            section.style.display = 'table';
            header.classList.remove('sidebarHeaderClosed');
            header.classList.add('sidebarHeaderOpened');
        } else {
            section.style.display = 'none';
            header.classList.remove('sidebarHeaderOpened');
            header.classList.add('sidebarHeaderClosed');
        }
    },

    assignSectionHeaderClickEvents: function () {
        let elements = document.querySelectorAll('[id$="HeaderH3"]');
        for (let i = 0; i < elements.length; i++) {
            elements[i].addEventListener('click', OCA.Analytics.Sidebar.showHideSidebarSection);
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
        OCA.Analytics.Visualization.showElement('tabContainerReport');
        document.getElementById('tabContainerReport').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        let requestUrl = OC.generateUrl('apps/analytics/report/') + reportId;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(async data => {
                let table;
                if (data !== false) {
                    data['type'] = parseInt(data['type']);
                    // clone the DOM template
                    table = document.importNode(document.getElementById('templateReport').content, true);
                    table.id = 'sidebarReport';
                    document.getElementById('tabContainerReport').innerHTML = '';
                    document.getElementById('tabContainerReport').appendChild(table);

                    // create the drop downs for Data source, grouping and possible data set
                    // need to do an await here, because the datasourceOptions is important for subsequent functions
                    await OCA.Analytics.Datasource.buildDropdown('sidebarReportDatasource');
                    OCA.Analytics.Sidebar.Report.buildGroupingDropdown('sidebarReportParent');
                    OCA.Analytics.Sidebar.Report.buildDatasetDropdown('sidebarReportDataset');

                    OCA.Analytics.Sidebar.Report.fillReportMetadataFields(data);
                    OCA.Analytics.Sidebar.Report.assignEventListeners();

                    // build the sections depending on report type
                    OCA.Analytics.Sidebar.Report.buildSections(data['type']);
                    OCA.Analytics.Sidebar.assignSectionHeaderClickEvents();

                    if (data['type'] === OCA.Analytics.TYPE_GROUP) {
                        document.getElementById('sidebarReportExportButton').style.display = 'none';
                        document.getElementById('sidebarReportNameHint').style.display = 'none';
                    } else if (data['type'] === OCA.Analytics.TYPE_INTERNAL_DB) {
                        OCA.Analytics.Sidebar.Report.fillDatasetRelatedFields();
                    } else {
                        let optionForm = OCA.Analytics.Datasource.buildDatasourceRelatedForm(data['type']);
                        document.getElementById('reportDatasourceSection').appendChild(optionForm);
                        OCA.Analytics.Sidebar.Report.fillDatasourceRelatedFields(data);
                    }

                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No changes possible') + '</p></div>';
                    document.getElementById('tabContainerReport').innerHTML = table;
                }
            });
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

    fillReportMetadataFields: function (data) {
        document.getElementById('sidebarReportName').value = data['name'];
        document.getElementById('sidebarReportSubheader').value = data['subheader'];
        document.getElementById('sidebarReportParent').value = data['parent'];
        document.getElementById('sidebarReportDatasource').value = data['type'];
        document.getElementById('sidebarReportDatasource').dataset.typeId = data['type']; // workaround to store the type in case the dropdown is not yet filled
        document.getElementById('sidebarReportDataset').value = data['dataset'];
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
    },

    fillDatasourceRelatedFields: function (data) {
        // set the options for a datasource
        if (data['link'] && data['link'].substring(0, 1) === '{') { // New format as of 3.1.0
            let options = JSON.parse(data['link']);
            for (let option in options) {
                document.getElementById(option) ? document.getElementById(option).value = OCA.Analytics.Sidebar.Report.decodeEscapedHtml(options[option]) : null;
                if (document.getElementById(option).type === 'checkbox') {
                    document.getElementById(option).checked = true;
                }
            }
        } else if ((parseInt(data['type']) === OCA.Analytics.TYPE_GIT)) { // Old format before 3.1.0
            document.getElementById('user').value = data['link'].split('/')[0];
            document.getElementById('repository').value = data['link'].split('/')[1];
        } else if ((parseInt(data['type']) === OCA.Analytics.TYPE_EXTERNAL_FILE)) { // Old format before 3.1.0
            document.getElementById('link').value = data['link'];
        } else if ((parseInt(data['type']) === OCA.Analytics.TYPE_INTERNAL_FILE)) { // Old format before 3.1.0
            document.getElementById('link').value = data['link'];
        }
    },

    assignEventListeners: function () {
        document.getElementById('sidebarReportDeleteButton').addEventListener('click', OCA.Analytics.Sidebar.Report.handleDeleteButton);
        document.getElementById('sidebarReportUpdateButton').addEventListener('click', OCA.Analytics.Sidebar.Report.handleUpdateButton);
        document.getElementById('sidebarReportExportButton').addEventListener('click', OCA.Analytics.Sidebar.Report.handleExportButton);
        document.getElementById('sidebarReportName').addEventListener('change', OCA.Analytics.Sidebar.Report.indicatorMetadataWasChanged);
        document.getElementById('sidebarReportParent').addEventListener('change', OCA.Analytics.Sidebar.Report.indicatorMetadataWasChanged);
        document.getElementById('sidebarReportNameHint').addEventListener('click', OCA.Analytics.Sidebar.Report.handleNameHint);
        document.getElementById('sidebarReportSubheaderHint').addEventListener('click', OCA.Analytics.Sidebar.Report.handleNameHint);
        document.getElementById('sidebarReportGroupHint').addEventListener('click', OCA.Analytics.Sidebar.Report.handleGroupHint);
        document.getElementById('sidebarReportDimensionHint').addEventListener('click', OCA.Analytics.Sidebar.Report.handleDimensionHint);
    },

    indicatorMetadataWasChanged: function () {
        OCA.Analytics.Sidebar.Report.metadataChanged = true;
    },

    handleDeleteButton: function (evt) {
        let id = evt.target.parentNode.dataset.id;
        if (id === undefined) id = evt.target.dataset.id;
        if (id === undefined) id = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Notification.confirm(
            t('analytics', 'Delete'),
            t('analytics', 'Are you sure?') + ' ' + t('analytics', 'All data will be deleted!'),
            function () {
                OCA.Analytics.Sidebar.Report.delete(id);
                OCA.Analytics.UI.resetContentArea();
                OCA.Analytics.Sidebar.close();
                OCA.Analytics.Notification.dialogClose();
            }
        );
    },

    handleUpdateButton: function () {
        OCA.Analytics.Sidebar.Report.update();
    },

    handleExportButton: function () {
        OCA.Analytics.Sidebar.Report.export();
    },

    buildSections: function (datasourceType) {
        document.getElementById('reportDatasourceSection').innerHTML = '';
        if (datasourceType === OCA.Analytics.TYPE_INTERNAL_DB) {
            document.getElementById('reportDimensionSectionHeader').style.removeProperty('display');
            document.getElementById('reportDatasourceSectionHeader').style.display = 'none';
            document.getElementById('reportVisualizationSectionHeader').style.removeProperty('display');
            document.getElementById('sidebarReportDatasourceRow').style.display = 'none';
            document.getElementById('sidebarReportDatasetRow').style.display = 'table-row';
            document.getElementById('sidebarReportSubheaderRow').style.display = 'table-row';
            document.getElementById('sidebarReportParentRow').style.display = 'table-row';
        } else if (datasourceType === OCA.Analytics.TYPE_GROUP) {
            document.getElementById('reportDimensionSectionHeader').style.display = 'none';
            document.getElementById('reportDatasourceSectionHeader').style.display = 'none';
            document.getElementById('reportVisualizationSectionHeader').style.display = 'none';
            document.getElementById('sidebarReportDatasourceRow').style.display = 'none';
            document.getElementById('sidebarReportDatasetRow').style.display = 'none';
            document.getElementById('sidebarReportSubheaderRow').style.display = 'none';
            document.getElementById('sidebarReportParentRow').style.display = 'none';
        } else {
            document.getElementById('reportDimensionSectionHeader').style.display = 'none';
            document.getElementById('reportDatasourceSectionHeader').style.removeProperty('display');
            document.getElementById('reportVisualizationSectionHeader').style.removeProperty('display');
            document.getElementById('sidebarReportDatasourceRow').style.display = 'table-row';
            document.getElementById('sidebarReportDatasetRow').style.display = 'none';
            document.getElementById('sidebarReportSubheaderRow').style.display = 'table-row';
            document.getElementById('sidebarReportParentRow').style.display = 'table-row';
        }
    },

    handleDatasourceChangeWizard: function () {
        const type = parseInt(document.getElementById('wizardNewDatasource').value);
        document.getElementById('wizardNewTypeOptionsRow').style.display = 'table-row';
        document.getElementById('wizardNewDatasourceSection').innerHTML = '';
        document.getElementById('wizardNewDatasourceSection').appendChild(OCA.Analytics.Datasource.buildDatasourceRelatedForm(type));
    },

    fillDatasetRelatedFields: function () {
        const dataset = parseInt(document.getElementById('sidebarReportDataset').value);
        const datasource = parseInt(document.getElementById('sidebarReportDatasource').value);

        if (dataset === 0) {
            document.getElementById('sidebarReportDimension1').disabled = false;
            document.getElementById('sidebarReportDimension2').disabled = false;
            document.getElementById('sidebarReportValue').disabled = false;
        } else if (datasource === OCA.Analytics.TYPE_INTERNAL_DB) {
            let dim1 = OCA.Analytics.datasets.find(x => parseInt(x.id) === dataset)['dimension1'];
            document.getElementById('sidebarReportDimension1').value = dim1;
            let dim2 = OCA.Analytics.datasets.find(x => parseInt(x.id) === dataset)['dimension2'];
            document.getElementById('sidebarReportDimension2').value = dim2;
            let value = OCA.Analytics.datasets.find(x => parseInt(x.id) === dataset)['value'];
            document.getElementById('sidebarReportValue').value = value;
            document.getElementById('sidebarReportValue').disabled = true;
            document.getElementById('sidebarReportDimension2').disabled = true;
            document.getElementById('sidebarReportDimension1').disabled = true;
        }
    },

    handleNameHint: function () {
        let header = t('analytics', 'Text variables');
        let text = '%lastUpdateDate%<br>' +
            '%lastUpdateTime%<br>' +
            '%currentDate%<br>' +
            '%currentTime%<br>' +
            '%now% (timestamp)<br>' +
            '%owner%';
        let guidance = t('analytics', 'Text variables can be used in the name or subheader.<br>They are replaced when the report is executed.');
        OCA.Analytics.Notification.info(header, text, guidance);
    },

    handleGroupHint: function () {
        let text = t('analytics', 'Reports can be grouped into a folder structure.') +
            '<br>' +
            t('analytics', 'Do you want to create a new group folder?');
        OCA.Analytics.Notification.confirm(
            t('analytics', 'Report group'),
            text,
            function () {
                OCA.Analytics.Sidebar.Report.createGroup();
                OCA.Analytics.Notification.dialogClose();
            }
        );
    },

    handleDimensionHint: function () {
        let text = t('analytics', 'Column descriptions are derived from the dataset and cannot be changed on a report level') +
            '<br><br>' +
            t('analytics', 'Datasets can be changed in a separate maintenance') +
            '<br>' +
            t('analytics', 'Switch to the dataset maintenance from here?');
        const datasetId = parseInt(document.getElementById('sidebarReportDataset').value);
        OCA.Analytics.Notification.confirm(
            t('analytics', 'Dataset'),
            text,
            function () {
                window.location = OC.generateUrl('apps/analytics/a/') + '#/r/' + datasetId;
            }
        );
    },

    buildGroupingDropdown: function (element) {
        let tableParent = document.getElementById(element);
        tableParent.innerHTML = '';
        let option = document.createElement('option');
        option.text = '';
        option.value = '0';
        tableParent.appendChild(option);

        for (let dataset of OCA.Analytics.reports) {
            if (parseInt(dataset.type) === OCA.Analytics.TYPE_GROUP) {
                option = document.createElement('option');
                option.text = dataset.name;
                option.value = dataset.id;
                tableParent.appendChild(option);
            }
        }
    },

    buildDatasetDropdown: function (element, wizard) {
        let tableParent = document.getElementById(element);
        tableParent.innerHTML = '';
        let option = document.createElement('option');
        option.innerText = t('analytics', 'Please select');
        tableParent.add(option);

        OCA.Analytics.Backend.getDatasetDefinitions();
        for (let dataset of OCA.Analytics.datasets) {
            option = document.createElement('option');
            option.text = dataset.name;
            option.value = dataset.id;
            tableParent.add(option);
        }
    },

    createGroup: function (addReport = null) {
        let requestUrl = OC.generateUrl('apps/analytics/report');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                name: t('analytics', 'New'),
                parent: 0,
                type: OCA.Analytics.TYPE_GROUP,
                dataset: 0,
                addReport: addReport,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init(data);
            });
    },

    create: function () {
        let error = '';
        let name = document.getElementById('wizardNewName').value;
        let subheader = document.getElementById('wizardNewSubheader').value;
        let grouping = document.getElementById('wizardNewGrouping').value;
        let type = document.getElementById('wizardNewDatasource').value;
        let dataset = document.getElementById('wizardNewDataset').value;
        let dimension1 = document.getElementById('wizardNewDimension1').value;
        let dimension2 = document.getElementById('wizardNewDimension2').value;
        let value = document.getElementById('wizardNewValue').value;
        let visualization = 'table';
        let chart = document.querySelector('input[name="chart"]:checked').value;

        let option = {};
        let inputFields = document.querySelectorAll('#wizardNewDatasourceSection input, #wizardNewDatasourceSection select');
        for (let inputField of inputFields) {
            option[inputField.id] = inputField.value;
        }
        let link = JSON.stringify(option);

        if (document.getElementById('wizardNewTypeStored').classList.contains('primary')) {
            type = OCA.Analytics.TYPE_INTERNAL_DB;
        }
        if (document.getElementById('wizardNewTypeStoredNew').classList.contains('primary')) {
            dataset = 0;
        }

        if (!document.getElementById('chartNone').checked && !document.getElementById('chartTableNone').checked) {
            // both are not "none"
            visualization = 'ct';
        } else if (!document.getElementById('chartNone').checked) {
            // both are not "none"
            visualization = 'chart';
        }

        if (name === '') {
            error = t('analytics', 'The report name is missing');
        } else if (type === '' || (type === 2 && dataset === '')) {
            error = t('analytics', 'The report type selection is missing');
        } else if (document.getElementById('chartNone').checked && document.getElementById('chartTableNone').checked) {
            error = t('analytics', 'At least one visualization selection is required');
        }

        if (error !== '') {
            OCA.Analytics.Notification.notification('error', error);
            return;
        }

        let requestUrl = OC.generateUrl('apps/analytics/report');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                name: name,
                subheader: subheader,
                parent: grouping,
                type: type,
                dataset: dataset,
                link: link,
                visualization: visualization,
                chart: chart,
                dimension1: dimension1,
                dimension2: dimension2,
                value: value
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Wizard.close();
                OCA.Analytics.Navigation.init();
            });
    },

    createFromDataFile: function (file = '') {
        let requestUrl = OC.generateUrl('apps/analytics/report/file');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                file: file,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init(data);
            });
    },

    update: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('sidebarReportUpdateButton');
        button.classList.add('loading');
        button.disabled = true;

        let option = {};
        let inputFields = document.querySelectorAll('#reportDatasourceSection input, #reportDatasourceSection select, #reportDatasourceSection textarea');
        for (let inputField of inputFields) {
            option[inputField.id] = inputField.value;
        }

        let requestUrl = OC.generateUrl('apps/analytics/report/') + reportId;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                name: document.getElementById('sidebarReportName').value,
                subheader: document.getElementById('sidebarReportSubheader').value,
                parent: document.getElementById('sidebarReportParent').value,
                link: JSON.stringify(option),
                visualization: document.getElementById('sidebarReportVisualization').value,
                chart: document.getElementById('sidebarReportChart').value,
                chartoptions: document.getElementById('sidebarReportChartOptions').value,
                dataoptions: document.getElementById('sidebarReportDataOptions').value,
                dimension1: document.getElementById('sidebarReportDimension1').value,
                dimension2: document.getElementById('sidebarReportDimension2').value,
                value: document.getElementById('sidebarReportValue').value
            })
        })
            .then(response => response.json())
            .then(data => {
                button.classList.remove('loading');
                button.disabled = false;

                if (OCA.Analytics.Sidebar.Report.metadataChanged === true) {
                    OCA.Analytics.Sidebar.Report.metadataChanged = false;
                    OCA.Analytics.Navigation.init(reportId);
                    OCA.Analytics.Backend.getDatasetDefinitions();
                } else {
                    if (!OCA.Analytics.isAdvanced) {
                        // todo check why this was added
                        // OCA.Analytics.currentReportData.options.chartoptions = '';
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    } else {
                        OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
                    }
                }
            });
    },

    delete: function (reportId) {
        let requestUrl = OC.generateUrl('apps/analytics/report/') + reportId;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                if (data !== 'true') {
                    OCA.Analytics.Notification.confirm(
                        t('analytics', 'Dataset'),
                        t('analytics', 'This was the last report on the dataset. Do you want to delete the dataset including all data?'),
                        function () {
                            OCA.Analytics.Sidebar.Report.deleteDatasetAlso(data);
                            OCA.Analytics.Notification.dialogClose();
                        },
                        true
                    );
                }
                document.getElementById('navigationDatasets').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';
                OCA.Analytics.Navigation.init();
                OCA.Analytics.Navigation.handleOverviewButton();
            })
            .catch(error => {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Request could not be processed'))
            });
    },

    deleteDatasetAlso: function (data) {
        let requestUrl = OC.generateUrl('apps/analytics/dataset/') + data;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.getDatasets();
            });
    },

    export: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        window.open(OC.generateUrl('apps/analytics/report/export/') + reportId, '_blank')
    },

    import: function (path, raw) {
        if (typeof raw === 'number') raw = null; // file picker is returning some INT which is not helpful in the service
        let requestUrl = OC.generateUrl('apps/analytics/report/import/');
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');

        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                path: path,
                raw: raw
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init();
            });
    },

    wizard: function () {
        document.getElementById('wizardNewCreate').addEventListener('click', OCA.Analytics.Sidebar.Report.create);
        document.getElementById('wizardNewCancel').addEventListener('click', OCA.Analytics.Wizard.cancel);

        document.getElementById('wizardNewTypeRealtime').addEventListener('click', function () {
            document.getElementById('wizardNewTypeDatasourceRow').style.display = 'table-row';
            document.getElementById('wizardNewTypeDatasetRow').style.display = 'none';
            document.getElementById('wizardNewTypeStoredRow').style.display = 'none';
            document.getElementById('wizardNewTypeRealtime').classList.add('primary');
            document.getElementById('wizardNewTypeStored').classList.remove('primary');
        });
        document.getElementById('wizardNewTypeStored').addEventListener('click', function () {
            document.getElementById('wizardNewTypeDatasourceRow').style.display = 'none';
            document.getElementById('wizardNewTypeOptionsRow').style.display = 'none';
            document.getElementById('wizardNewTypeStoredRow').style.display = 'table-row';
            document.getElementById('wizardNewTypeDatasetRow').style.display = 'none';
            document.getElementById('wizardNewTypeStored').classList.add('primary');
            document.getElementById('wizardNewTypeRealtime').classList.remove('primary');
        })

        document.getElementById('wizardNewTypeStoredNew').addEventListener('click', function () {
            document.getElementById('wizardNewTypeDatasetRow').style.display = 'none';
            document.getElementById('wizardNewTypeDimensionRow').style.display = 'table-row';
            document.getElementById('wizardNewTypeStoredNew').classList.add('primary');
            document.getElementById('wizardNewTypeStoredOld').classList.remove('primary');
        })

        document.getElementById('wizardNewTypeStoredOld').addEventListener('click', function () {
            document.getElementById('wizardNewTypeDatasetRow').style.display = 'table-row';
            document.getElementById('wizardNewTypeDimensionRow').style.display = 'none';
            document.getElementById('wizardNewTypeStoredNew').classList.remove('primary');
            document.getElementById('wizardNewTypeStoredOld').classList.add('primary');
        })

        OCA.Analytics.Datasource.buildDropdown('wizardNewDatasource');
        document.getElementById('wizardNewDatasource').addEventListener('change', OCA.Analytics.Sidebar.Report.handleDatasourceChangeWizard);

        OCA.Analytics.Sidebar.Report.buildGroupingDropdown('wizardNewGrouping');
        OCA.Analytics.Sidebar.Report.buildDatasetDropdown('wizardNewDataset', true);
    },

};

OCA.Analytics.Sidebar.Data = {

    tabContainerData: function () {
        const reportId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderData').classList.add('selected');
        OCA.Analytics.Visualization.showElement('tabContainerData');
        document.getElementById('tabContainerData').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        let type = 'report';
        if (OCA.Analytics.isAdvanced) {
            type = 'dataset';
        }

        let requestUrl = OC.generateUrl('apps/analytics/' + type + '/') + reportId;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        })
            .then(response => response.json())
            .then(data => {
                let table;
                if (data !== false) {
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
                    document.getElementById('deleteDataButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataDeletionButton);
                    document.getElementById('importDataFileButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataImportFileButton);
                    document.getElementById('importDataClipboardButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataImportClipboardButton);
                    document.getElementById('advancedButton').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataAdvancedButton);
                    document.getElementById('apiLink').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDataApiButton);

                    document.getElementById('sidebarDataDimension1Hint').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDimensionHint);
                    document.getElementById('sidebarDataDimension2Hint').addEventListener('click', OCA.Analytics.Sidebar.Data.handleDimensionHint);
                    document.getElementById('DataDimension1').addEventListener('click', OCA.Analytics.UI.showDropDownList);
                    document.getElementById('DataDimension2').addEventListener('click', OCA.Analytics.UI.showDropDownList);

                    OCA.Analytics.Sidebar.assignSectionHeaderClickEvents();

                    document.getElementById('DataValue').addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            OCA.Analytics.Sidebar.Backend.updateData();
                            document.getElementById('DataDimension2').focus();
                            document.getElementById('DataDimension2').value = '';
                            document.getElementById('DataValue').value = '';
                        }
                    });
                } else {
                    table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No changes possible') + '</p></div>';
                    document.getElementById('tabContainerData').innerHTML = table;
                }
            });
    },

    handleDimensionHint: function () {
        let header = t('analytics', 'Text variables');
        let text = '%currentDate%<br>' +
            '%currentTime%<br>' +
            '%now% (timestamp)';
        let guidance = t('analytics', 'Text variables can be used in the dimensions.<br>They are replaced when the data is added.');
        OCA.Analytics.Notification.info(header, text, guidance);
    },

    handleDataUpdateButton: function () {
        OCA.Analytics.Sidebar.Backend.updateData();
    },

    handleDataDeletionButton: function () {
        OCA.Analytics.Sidebar.Backend.deleteDataSimulate();
    },

    handleDataImportFileButton: function () {
        const mimeparts = ['text/csv', 'text/plain'];
        OC.dialogs.filepicker(t('analytics', 'Select file'), OCA.Analytics.Sidebar.Backend.importFileData.bind(this), false, mimeparts, true, 1);
    },

    handleDataImportClipboardButton: function () {
        OCA.Analytics.Visualization.showElement('importDataClipboardText');
        OCA.Analytics.Visualization.showElement('importDataClipboardButtonGo');
        document.getElementById('importDataClipboardButtonGo').addEventListener('click', OCA.Analytics.Sidebar.Backend.importCsvData);
    },

    handleDataApiButton: function () {
        let header = t('analytics', 'REST API parameters');
        let text = OC.generateUrl('/apps/analytics/api/3.0/data/')
            + document.getElementById('DataApiDataset').innerText
            + '/add<br><br>'
            + '<a href="https://github.com/rello/analytics/wiki/API" target="_blank">'
            + t('analytics', 'More Information …')
            + '</a>';
        let guidance = t('analytics', 'Use this endpoint to submit data via an API:');
        OCA.Analytics.Notification.info(header, text, guidance);
    },

    handleDataAdvancedButton: function () {
        window.location = OC.generateUrl('apps/analytics/a/') + '#/r/' + document.getElementById('DataApiDataset').innerText;
    },

};

OCA.Analytics.Sidebar.Share = {

    tabContainerShare: function () {
        const reportId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderShare').classList.add('selected');
        OCA.Analytics.Visualization.showElement('tabContainerShare');
        document.getElementById('tabContainerShare').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        // clone the DOM template
        let template = document.getElementById('templateSidebarShare').content;
        template = document.importNode(template, true);
        template.getElementById('linkShareList').appendChild(OCA.Analytics.Sidebar.Share.buildShareLinkRow(0, 0, true));
        template.getElementById('shareInput').addEventListener('keyup', OCA.Analytics.Sidebar.Share.searchShareeAPI);

        let requestUrl = OC.generateUrl('apps/analytics/share/') + reportId;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                if (data !== false) {
                    for (let share of data) {
                        if (parseInt(share.type) === OCA.Analytics.SHARE_TYPE_LINK) {
                            let li = OCA.Analytics.Sidebar.Share.buildShareLinkRow(parseInt(share['id']), share['token'], false, (String(share['pass']) === "true"), parseInt(share['permissions']), share['domain']);
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
                    let table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No changes possible') + '</p></div>';
                    document.getElementById('tabContainerShare').innerHTML = table;
                }
            });
    },

    buildShareLinkRow: function (id, token, add = false, pw = false, permissions = OC.PERMISSION_READ, domain = false) {

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
            linkRow.getElementById('shareClipboardLink').value = OC.getProtocol() + '://' + OC.getHostName() + (OC.getPort() !== '' ? ':' + OC.getPort() : '') + OC.generateUrl('/apps/analytics/p/') + token;
            linkRow.getElementById('shareClipboard').addEventListener('click', OCA.Analytics.Sidebar.Share.handleShareClipboard)
            linkRow.getElementById('moreIcon').addEventListener('click', OCA.Analytics.Sidebar.Share.showShareMenu);
            linkRow.getElementById('showPassword').addEventListener('click', OCA.Analytics.Sidebar.Share.showPassMenu);
            linkRow.getElementById('showPassword').nextElementSibling.htmlFor = 'showPassword' + id;
            linkRow.getElementById('showPassword').id = 'showPassword' + id;

            linkRow.getElementById('shareChart').addEventListener('click', OCA.Analytics.Sidebar.Share.showChartMenu);
            linkRow.getElementById('shareChart').nextElementSibling.htmlFor = 'shareChart' + id;
            linkRow.getElementById('shareChart').id = 'shareChart' + id;
            linkRow.getElementById('shareChartDomain').id = 'shareChartDomain' + id;
            linkRow.getElementById('shareChartLink').value = OC.getProtocol() + '://' + OC.getHostName() + OC.generateUrl('/apps/analytics/pm/') + token;
            linkRow.getElementById('shareChartClipboard').addEventListener('click', OCA.Analytics.Sidebar.Share.handleShareChartClipboard)

            linkRow.getElementById('linkPassSubmit').addEventListener('click', OCA.Analytics.Sidebar.Share.updateSharePassword);
            linkRow.getElementById('linkPassSubmit').dataset.id = id;
            linkRow.getElementById('shareChartSubmit').addEventListener('click', OCA.Analytics.Sidebar.Share.updateShareDomain);
            linkRow.getElementById('shareChartSubmit').dataset.id = id;
            linkRow.getElementById('deleteShareIcon').addEventListener('click', OCA.Analytics.Sidebar.Share.removeShare);
            linkRow.getElementById('deleteShare').dataset.id = id;
            if (pw) {
                linkRow.getElementById('linkPassMenu').classList.remove('hidden');
                linkRow.getElementById('showPassword' + id).checked = true;
            }
            if (domain) {
                linkRow.getElementById('shareChart' + id).click();
                linkRow.getElementById('shareChartDomain' + id).value = domain;
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
        shareeRow.getElementById('deleteShare').addEventListener('click', OCA.Analytics.Sidebar.Share.removeShare);
        shareeRow.getElementById('deleteShare').dataset.id = id;
        shareeRow.getElementById('shareEditing').addEventListener('click', OCA.Analytics.Sidebar.Share.updateShareCanEdit);
        shareeRow.getElementById('shareEditing').dataset.id = id;
        shareeRow.getElementById('shareEditing').nextElementSibling.htmlFor = 'shareEditing' + id;
        shareeRow.getElementById('shareEditing').id = 'shareEditing' + id;
        if (permissions === OC.PERMISSION_UPDATE) {
            shareeRow.getElementById('shareEditing' + id).checked = true;
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

    handleShareChartClipboard: function (evt) {
        let link = evt.target.nextElementSibling.value;
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

    showChartMenu: function (evt) {
        const toggleState = evt.target.checked;
        if (toggleState === true) {
            evt.target.parentNode.parentNode.nextElementSibling.classList.remove('hidden');
            evt.target.parentNode.parentNode.nextElementSibling.nextElementSibling.classList.remove('hidden');
            evt.target.parentNode.parentNode.nextElementSibling.nextElementSibling.nextElementSibling.classList.remove('hidden');
        } else {
            evt.target.parentNode.parentNode.nextElementSibling.classList.add('hidden');
            evt.target.parentNode.parentNode.nextElementSibling.nextElementSibling.classList.add('hidden');
            evt.target.parentNode.parentNode.nextElementSibling.nextElementSibling.nextElementSibling.classList.add('hidden');
        }
    },

    createShare: function (evt) {
        const reportId = document.getElementById('app-sidebar').dataset.id;
        let shareType = evt.target.dataset.shareType;
        let shareUser = evt.target.dataset.user;

        let requestUrl = OC.generateUrl('apps/analytics/share');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                reportId: reportId,
                type: shareType,
                user: shareUser,
            })
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
    },

    removeShare: function (evt) {
        const shareId = evt.target.parentNode.dataset.id;
        let requestUrl = OC.generateUrl('apps/analytics/share/') + shareId;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
    },

    updateSharePassword: function (evt) {
        const shareId = evt.target.dataset.id;
        const password = evt.target.previousElementSibling.value;

        let requestUrl = OC.generateUrl('apps/analytics/share/') + shareId;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                password: password
            })
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
    },

    updateShareDomain: function (evt) {
        const shareId = evt.target.dataset.id;
        const domain = evt.target.previousElementSibling.value;

        let requestUrl = OC.generateUrl('apps/analytics/share/') + shareId;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                domain: domain
            })
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
    },

    updateShareCanEdit: function (evt) {
        const shareId = evt.target.dataset.id;
        const canEdit = evt.target.checked;

        let requestUrl = OC.generateUrl('apps/analytics/share/') + shareId;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                canEdit: canEdit
            })
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
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

        let requestUrl = URL + '?' + params;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                let jsondata = data;
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
            });
    },
};

OCA.Analytics.Sidebar.Threshold = {

    tabContainerThreshold: function () {
        const reportId = document.getElementById('app-sidebar').dataset.id;

        OCA.Analytics.Sidebar.resetView();
        document.getElementById('tabHeaderThreshold').classList.add('selected');
        OCA.Analytics.Visualization.showElement('tabContainerThreshold');
        document.getElementById('tabContainerThreshold').innerHTML = '<div style="text-align:center; padding-top:100px" class="get-metadata icon-loading"></div>';

        // get the report metadata to put correct labels to the input boxes
        let requestUrl = OC.generateUrl('apps/analytics/report/') + reportId;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                // clone the DOM template
                let table = document.importNode(document.getElementById('templateThreshold').content, true);
                table.id = 'tableThreshold';
                document.getElementById('tabContainerThreshold').innerHTML = '';
                document.getElementById('tabContainerThreshold').appendChild(table);
                document.getElementById('sidebarThresholdTextDimension1').innerText = data.dimension1 || t('analytics', 'Column') + ' 1';
                document.getElementById('sidebarThresholdTextValue').innerText = data.value || t('analytics', 'Value');
                document.getElementById('sidebarThresholdCreateButton').addEventListener('click', OCA.Analytics.Sidebar.Threshold.handleThresholdCreateButton);
                document.getElementById('sidebarThresholdCreateNewButton').addEventListener('click', OCA.Analytics.Sidebar.Threshold.handleThresholdCreateNewButton);

                document.getElementById('sidebarThresholdHint').addEventListener('click', OCA.Analytics.Sidebar.Threshold.handleThresholdHint);
                document.getElementById('sidebarThresholdDimension1').addEventListener('click', OCA.Analytics.UI.showDropDownList);

                if (parseInt(data.type) !== OCA.Analytics.TYPE_INTERNAL_DB) {
                    document.getElementById('sidebarThresholdSeverity').remove(0);
                    document.getElementById('sidebarThresholdCreateNewHeader').hidden = true;
                    document.getElementById('sidebarThresholdCreateNewButton').hidden = true;
                }

                OCA.Analytics.Sidebar.Threshold.getThreholdList(reportId);
            });


    },

    getThreholdList: function (reportId) {
        let requestUrl = OC.generateUrl('apps/analytics/threshold/') + reportId;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                if (data !== false) {
                    document.getElementById('sidebarThresholdList').innerHTML = '';
                    for (let threshold of data) {
                        const li = OCA.Analytics.Sidebar.Threshold.buildThresholdRow(threshold);
                        document.getElementById('sidebarThresholdList').appendChild(li);
                    }
                }
            });
    },

    handleThresholdHint: function () {
        let guidance = t('analytics', 'Text variables can be used to evaluate a date value.<br>For example %today% can be used to highlight the data of today.<br>Operator and value are not relevant in this case.');
        let text = '%today%<br>' +
            '%next day%<br>' +
            '%next 2 days% (in 2 days)';
        OCA.Analytics.Notification.info(t('analytics', 'Text variables'), text, guidance);
    },

    handleThresholdCreateButton: function () {
        OCA.Analytics.Sidebar.Threshold.createThreashold();
    },

    handleThresholdCreateNewButton: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        let requestUrl = OC.generateUrl('apps/analytics/threshold');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                reportId: reportId,
                dimension1: 0,
                option: 'new',
                value: 0,
                severity: 1,
            })
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
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

        if (data.option === 'new') { // adjust the text for the "new records" option
            data.value = '';
            data.dimension1 = t('analytics', 'new record');
            data.option = '';
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
        text.innerText = data.dimension1 + ' ' + data.option + ' ' + parseFloat(data.value).toLocaleString();

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

        if (document.getElementById('sidebarThresholdDimension1').value === '' ||
            document.getElementById('sidebarThresholdValue').value === '') {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Missing data'));
            return;
        }

        if (isNaN(document.getElementById('sidebarThresholdValue').value.replace(',', '.'))) {
            OCA.Analytics.Notification.notification('error', t('analytics', '3rd field must be a valid number'));
            return;
        }

        let requestUrl = OC.generateUrl('apps/analytics/threshold');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                reportId: reportId,
                dimension1: document.getElementById('sidebarThresholdDimension1').value,
                option: document.getElementById('sidebarThresholdOption').value,
                value: document.getElementById('sidebarThresholdValue').value,
                severity: document.getElementById('sidebarThresholdSeverity').value,
            })
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
    },

    deleteThreshold: function (thresholdId) {
        let requestUrl = OC.generateUrl('apps/analytics/threshold/') + thresholdId;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                document.querySelector('.tabHeader.selected').click();
            });
    },
};

OCA.Analytics.Sidebar.Backend = {

    updateData: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('updateDataButton');
        button.classList.add('loading');
        button.disabled = true;

        let requestUrl = OC.generateUrl('apps/analytics/data/') + reportId;
        fetch(requestUrl, {
            method: 'PUT',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                dimension1: document.getElementById('DataDimension1').value,
                dimension2: document.getElementById('DataDimension2').value,
                value: document.getElementById('DataValue').value,
                isDataset: OCA.Analytics.isAdvanced,
            })
        })
            .then(response => response.json())
            .then(data => {
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.Notification.notification('success', data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' ' + t('analytics', 'records updated'));
                    if (!OCA.Analytics.isAdvanced) {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            });
    },

    deleteDataSimulate: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('deleteDataButton');
        //button.classList.add('loading');
        //button.disabled = true;

        let requestUrl = OC.generateUrl('apps/analytics/data/deleteDataSimulate');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                reportId: reportId,
                dimension1: document.getElementById('DataDimension1').value,
                dimension2: document.getElementById('DataDimension2').value,
                isDataset: OCA.Analytics.isAdvanced,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Notification.confirm(
                    t('analytics', 'Delete data'),
                    t('analytics', 'Are you sure?') + '<br>' + t('analytics', 'Records to be deleted: ') + data.delete.count,
                    function (e) {
                        OCA.Analytics.Sidebar.Backend.deleteData();
                        OCA.Analytics.Notification.dialogClose();
                    }
                );
            });
    },

    deleteData: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('deleteDataButton');
        button.classList.add('loading');
        button.disabled = true;

        let requestUrl = OC.generateUrl('apps/analytics/data/') + reportId;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                dimension1: document.getElementById('DataDimension1').value,
                dimension2: document.getElementById('DataDimension2').value,
                isDataset: OCA.Analytics.isAdvanced,
            })
        })
            .then(response => response.json())
            .then(data => {
                button.classList.remove('loading');
                button.disabled = false;
                if (!OCA.Analytics.isAdvanced) {
                    OCA.Analytics.UI.resetContentArea();
                    OCA.Analytics.Backend.getData();
                }
            });
    },

    importCsvData: function () {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('importDataClipboardButton');
        button.classList.add('loading');
        button.disabled = true;

        let requestUrl = OC.generateUrl('apps/analytics/data/importCSV');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                reportId: reportId,
                import: document.getElementById('importDataClipboardText').value,
                isDataset: OCA.Analytics.isAdvanced,
            })
        })
            .then(response => response.json())
            .then(data => {
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.Notification.notification('success', data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' ' + t('analytics', 'records updated'));
                    if (!OCA.Analytics.isAdvanced) {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            })
            .catch(error => {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Technical error. Please check the logs.'));
                button.classList.remove('loading');
                button.disabled = false;
            });
    },

    importFileData: function (path) {
        const reportId = parseInt(document.getElementById('app-sidebar').dataset.id);
        const button = document.getElementById('importDataFileButton');
        button.classList.add('loading');
        button.disabled = true;

        let requestUrl = OC.generateUrl('apps/analytics/data/importFile');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                reportId: reportId,
                path: path,
                isDataset: OCA.Analytics.isAdvanced,
            })
        })
            .then(response => response.json())
            .then(data => {
                button.classList.remove('loading');
                button.disabled = false;
                if (data.error === 0) {
                    OCA.Analytics.Notification.notification('success', data.insert + ' ' + t('analytics', 'records inserted') + ', ' + data.update + ' ' + t('analytics', 'records updated'));
                    if (!OCA.Analytics.isAdvanced) {
                        OCA.Analytics.UI.resetContentArea();
                        OCA.Analytics.Backend.getData();
                    }
                } else {
                    OCA.Analytics.Notification.notification('error', data.error);
                }
            })
            .catch(error => {
                OCA.Analytics.Notification.notification('error', t('analytics', 'Technical error. Please check the logs.'));
                button.classList.remove('loading');
                button.disabled = false;
            });
    },
};
