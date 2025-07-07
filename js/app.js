/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: OCP */
/** global: OC */
/** global: t */
/** global: table */
/** global: Chart */
/** global: cloner */
/** global: _ */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
    OCA.Analytics.initialDocumentTitle = document.title;
    OCA.Analytics.Visualization.hideElement('analytics-warning');
    OCA.Analytics.Visualization.showElement('analytics-intro');

    OCA.Analytics.translationAvailable = OCA.Analytics.Core.getInitialState('translationAvailable');

    OCA.Analytics.Core.init();
});

OCA = OCA || {};

OCA.Analytics = OCA.Analytics || {};
Object.assign(OCA.Analytics, {
    TYPE_GROUP: 0,
    TYPE_INTERNAL_FILE: 1,
    TYPE_INTERNAL_DB: 2,
    TYPE_GIT: 3,
    TYPE_EXTERNAL_FILE: 4,
    TYPE_EXTERNAL_REGEX: 5,
    TYPE_SPREADSHEET: 7,
    TYPE_SHARED: 99,
    SHARE_TYPE_USER: 0,
    SHARE_TYPE_GROUP: 1,
    SHARE_TYPE_LINK: 3,
    SHARE_TYPE_ROOM: 10,
    SHARE_PERMISSION_UPDATE: 2,

    PANORAMA_CONTENT_TYPE_REPORT: 0,
    PANORAMA_CONTENT_TYPE_TEXT: 1,
    PANORAMA_CONTENT_TYPE_PICTURE: 2,

    initialDocumentTitle: null,
    isReport: true,
    isDataset: false,
    isPanorama: false,

    chartObject: null,
    tableObject: [],
    datasources: [],
    datasets: [],
    reports: [],
    stories: [],
    handlers: {},

    currentReportData: {},
    currentPanorama: {},
    currentDataset: null,
    currentDatasetType: null,
    currentPage: 0,
    currentContentType: null,

    unsavedChanges: false,
    editMode: false,
    refreshTimer: null,
    currentXhrRequest: null,
    translationAvailable: false,
    isNewObject: false,

    // flexible mapping depending on type required by the used chart library
    // Add in all js files!
    chartTypeMapping: {
        'datetime': 'line',
        'column': 'bar',
        'columnSt': 'bar', // map stacked type also to base type; needed in filter
        'columnSt100': 'bar', // map stacked type also to base type; needed in filter
        'area': 'line',
        'line': 'line',
        'doughnut': 'doughnut',
        'funnel': 'funnel'
    },

    /**
     * Build common request headers for backend calls
     */
    headers: function () {
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');
        return headers;
    },

    registerHandler: function (context, type, handlerFunction) {
        if (!OCA.Analytics.handlers[context]) {
            OCA.Analytics.handlers[context] = {};
        }
        OCA.Analytics.handlers[context][type] = handlerFunction;
    },
});

OCA.Analytics.Core = OCA.Analytics.Core || {};
Object.assign(OCA.Analytics.Core = {
    /**
     * Initialize navigation and register UI handlers
     */
    init: function () {
        // shared report?
        if (document.getElementById('sharingToken').value !== '') {
            document.getElementById('byAnalytics').classList.toggle('analyticsFullscreen');
            OCA.Analytics.Report.Backend.getData();
            return;
        }

        // URL semantic is analytics/*type*/id
        // match[1] = type
        // match[2] = id
        let regex = /\/analytics\/([a-zA-Z0-9]+)\/(\d+)/;
        let match = window.location.href.match(regex);

        if (match) {
            OCA.Analytics.Navigation.init(match);
        } else {
            OCA.Analytics.Navigation.init();
            // Dashboard has to be loaded from the navigation as it depends on the report index
        }

        OCA.Analytics.Report.reportOptionsEventlisteners();
        OCA.Analytics.Panorama.reportOptionsEventlisteners();
        document.getElementById("infoBoxReport").addEventListener('click', OCA.Analytics.Navigation.handleNewButton);
        document.getElementById("infoBoxIntro").addEventListener('click', OCA.Analytics.Wizard.showFirstStart);
        document.getElementById("infoBoxWiki").addEventListener('click', OCA.Analytics.Core.openWiki);
        document.getElementById('fullscreenToggle').addEventListener('click', OCA.Analytics.Visualization.toggleFullscreen);
        document.getElementById('saveIcon').addEventListener('click', OCA.Analytics.Filter.handleSaveButton);

        document.getElementById('optionsMenuIcon').addEventListener('click', OCA.Analytics.Core.toggleOptionsMenu);
    },

    toggleOptionsMenu: function () {
        if (OCA.Analytics.currentContentType === 'panorama') {
            document.getElementById('optionsMenuMainPanorama').style.removeProperty('display');
            document.getElementById('optionsMenuMainReport').style.setProperty('display', 'none', 'important');
        } else if (OCA.Analytics.currentContentType === 'report') {
            document.getElementById('optionsMenuMainPanorama').style.setProperty('display', 'none', 'important');
            document.getElementById('optionsMenuMainReport').style.removeProperty('display');
        }
        document.getElementById('optionsMenu').classList.toggle('open');
        document.getElementById('optionsMenuSubAnalysis').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuSubRefresh').style.setProperty('display', 'none', 'important');
        document.getElementById('optionsMenuSubTranslate').style.setProperty('display', 'none', 'important');
    },

    /**
     * Return unique values of a given column
     */
    getDistinctValues: function (array, index) {
        let unique = [];
        let distinct = [];
        if (array === undefined) {
            return distinct;
        }
        for (let i = 0; i < array.length; i++) {
            if (!unique[array[i][index]]) {
                distinct.push(array[i][index]);
                unique[array[i][index]] = 1;
            }
        }
        return distinct;
    },

    /**
     * Read a value from the initial state injected by PHP
     */
    getInitialState: function (key) {
        const app = 'analytics';
        const elem = document.querySelector('#initial-state-' + app + '-' + key);
        if (elem === null) {
            return false;
        }
        return JSON.parse(atob(elem.value))
    },

    /**
     * Open project wiki in a new browser tab
     */
    openWiki: function () {
        window.open('https://github.com/rello/analytics/wiki', '_blank');
    }
});

OCA.Analytics.Translation = OCA.Analytics.Translation || {};
Object.assign(OCA.Analytics.Translation = {
    /**
     * Send current report text to translation service
     */
    translate: function () {
        let name = OCA.Analytics.currentReportData.options.name;
        let subheader = OCA.Analytics.currentReportData.options.subheader;
        let header = OCA.Analytics.currentReportData.header;
        let dimensions = JSON.stringify(OCA.Analytics.currentReportData.dimensions);
        let text = name + '**' + subheader + '**' + header + '**' + dimensions;

        let targetLanguage = document.getElementById('translateLanguage').value;
        targetLanguage = targetLanguage === 'EN' ? 'EN-US' : targetLanguage;

        let requestUrl = OC.generateUrl('ocs/v2.php/translation/translate');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                fromLanguage: null,
                text: text,
                toLanguage: targetLanguage
            })
        })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 400) {
                        OCA.Analytics.Notification.notification('error', t('analytics', 'Translation error. Possibly wrong ISO code?'));
                        return Promise.reject('400 Error');
                    }
                }
                return response.text();
            })
            .then(data => {
                let parser = new DOMParser();
                let xmlDoc = parser.parseFromString(data, "text/xml");

                let text = xmlDoc.getElementsByTagName("text")[0].childNodes[0].nodeValue;
                text = text.split('**');
                let from = xmlDoc.getElementsByTagName("from")[0].childNodes[0].nodeValue;

                OCA.Analytics.currentReportData.options.name = text[0];
                OCA.Analytics.currentReportData.options.subheader = text[1];
                OCA.Analytics.currentReportData.header = text[2].split(',');
                OCA.Analytics.currentReportData.dimensions = JSON.parse(text[3]);
                OCA.Analytics.Report.resetContentArea();
                OCA.Analytics.Report.buildReport();
            })
            .catch(error => {
                console.log('There has been a problem with your fetch operation: ', error);
            });

    },

    /**
     * Populate translation language selector
     */
    languages: function () {
        const elem = document.querySelector('#initial-state-analytics-translationLanguages');
        let translateLanguage = document.getElementById('translateLanguage');
        translateLanguage.innerHTML = '';

        let option = document.createElement('option');
        option.text = t('analytics', 'Choose language');
        option.value = '';
        translateLanguage.appendChild(option);

        const set = new Set();
        for (const item of JSON.parse(atob(elem.value))) {
            if (!set.has(item.from)) {
                set.add(item.from)
                let option = document.createElement('option');
                option.text = item.fromLabel;
                option.value = item.from;
                translateLanguage.appendChild(option);
            }
        }
    }
});

OCA.Analytics.Datasource = OCA.Analytics.Datasource || {};
Object.assign(OCA.Analytics.Datasource = {
    /**
     * Load data source list and fill dropdown element
     */
    buildDropdown: async function (target, filter) {
        let optionsInit = document.createDocumentFragment();
        let optionInit = document.createElement('option');
        optionInit.value = '';
        optionInit.innerText = t('analytics', 'Loading');
        optionsInit.appendChild(optionInit);
        document.getElementById(target).innerHTML = '';
        document.getElementById(target).appendChild(optionsInit);

        let filterDatasource = '';
        if (filter) {
            filterDatasource = '/' + filter;
        }
        // need to offer an await here, because the data source options are important for subsequent functions in the sidebar
        let requestUrl = OC.generateUrl('apps/analytics/datasource' + filterDatasource);
        let response = await fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers()
        });
        let data = await response.json();

        OCA.Analytics.datasources = data;
        let options = document.createDocumentFragment();
        let option = document.createElement('option');
        option.value = '';
        option.innerText = t('analytics', 'Please select');
        options.appendChild(option);

        let sortedOptions = OCA.Analytics.Datasource.sortOptions(data['datasources']);
        sortedOptions.forEach((entry) => {
            let value = entry[1];
            option = document.createElement('option');
            option.value = entry[0];
            option.innerText = value;
            options.appendChild(option);
        });
        document.getElementById(target).innerHTML = '';
        document.getElementById(target).appendChild(options);
        if (document.getElementById(target).dataset.typeId) {
            // in case the value was set in the sidebar before the dropdown was ready
            document.getElementById(target).value = document.getElementById(target).dataset.typeId;
        }
    },

    /**
     * Sort object keys alphabetically by value
     */
    sortOptions: function (obj) {
        let sortable = [];
        for (let key in obj)
            if (obj.hasOwnProperty(key))
                sortable.push([key, obj[key]]);
        sortable.sort(function (a, b) {
            let x = a[1].toLowerCase(),
                y = b[1].toLowerCase();
            return x < y ? -1 : x > y ? 1 : 0;
        });
        return sortable;
    },

    /**
     * Generate configuration form for the selected data source
     */
    buildDatasourceRelatedForm: function (datasource) {
        let template = OCA.Analytics.datasources.options[datasource];
        let form = document.createElement('div');
        let insideSection = false;
        form.id = 'dataSourceOptions';

        if (typeof (template) === 'undefined') {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Data source not available anymore'));
            return form;
        }

        // create a hidden dummy for the data source type
        form.appendChild(OCA.Analytics.Datasource.buildOptionHidden('dataSourceType', datasource));

        for (let templateOption of template) {
            // loop all options of the data source template and create the input form

            // if it is a section, we don´t need the usual labels
            if (templateOption.type === 'section') {
                let tableRow = OCA.Analytics.Datasource.buildOptionsSection(templateOption);
                form.appendChild(tableRow);
                insideSection = true;
                continue;
            }

            // create the label column
            let tableRow = document.createElement('div');
            tableRow.style.display = insideSection === false ? 'table-row' : 'none';
            let label = document.createElement('div');
            label.style.display = 'table-cell';
            label.style.width = '100%';
            label.innerText = templateOption.name;
            // create the info icon column
            let infoColumn = document.createElement('div');
            infoColumn.style.display = 'table-cell';
            infoColumn.style.minWidth = '20px';

            //create the input fields
            let input = OCA.Analytics.Datasource.buildOptionsInput(templateOption);
            input.style.display = 'table-cell';
            if (templateOption.type) {
                if (templateOption.type === 'tf') {
                    input = OCA.Analytics.Datasource.buildOptionsSelect(templateOption);
                } else if (templateOption.type === 'filePicker') {
                    input.addEventListener('click', OCA.Analytics.Datasource.handleFilepicker);
                } else if (templateOption.type === 'columnPicker') {
                    input.addEventListener('click', OCA.Analytics.Datasource.handleColumnPicker);
                }
            }
            form.appendChild(tableRow);
            tableRow.appendChild(label);
            tableRow.appendChild(input);
            tableRow.appendChild(infoColumn);
        }
        return form;
    },

    /**
     * Create hidden form field used for options
     */
    buildOptionHidden: function (id, value) {
        let dataSourceType = document.createElement('input');
        dataSourceType.hidden = true;
        dataSourceType.id = id;
        dataSourceType.innerText = value;
        return dataSourceType;
    },

    /**
     * Create input element based on template definition
     */
    buildOptionsInput: function (templateOption) {
        let type = templateOption.type && templateOption.type === 'longtext' ? 'textarea' : 'input';
        let input = document.createElement(type);
        input.style.display = 'inline-flex';
        input.classList.add('sidebarInput');
        input.placeholder = templateOption.placeholder;
        input.id = templateOption.id;
        input.dataset.type = templateOption.type;
        if (templateOption.type && templateOption.type === 'number') {
            input.type = 'number';
            input.min = '1';
        }
        if (templateOption.type) {
            input.autocomplete = 'off';
        }
        return input;
    },

    /**
     * Create collapsible section header
     */
    buildOptionsSection: function (templateOption) {
        let tableRow = document.createElement('div');
        tableRow.classList.add('sidebarHeaderClosed');
        let label = document.createElement('a');
        label.style.display = 'table-cell';
        label.style.width = '100%';
        label.innerText = templateOption.name;
        label.id = 'optionSectionHeader';
        label.addEventListener('click', OCA.Analytics.Datasource.showHiddenOptions);
        tableRow.appendChild(label);
        return tableRow;
    },

    /**
     * Create checkbox with editable label indicator
     */
    buildOptionsCheckboxIndicator: function (templateOption) {
        let input = document.createElement('input');
        input.type = 'checkbox'
        input.disabled = true;
        input.style.display = 'inline-flex';
        //input.classList.add('sidebarInput');
        input.id = templateOption.id;
        input.dataset.type = templateOption.type;

        let edit = document.createElement('span');
        edit.style.display = 'inline-flex';
        edit.classList.add('icon', 'icon-rename');
        edit.style.minHeight = '36px';

        let div = document.createElement('div');
        div.style.display = 'table-cell';
        div.appendChild(input);
        div.appendChild(edit);
        return div;
    },

    /**
     * Create select box from placeholder string
     */
    buildOptionsSelect: function (templateOption) {
        let input = document.createElement('select');
        let text, value;
        input.style.display = 'inline-flex';
        input.classList.add('sidebarInput');
        input.id = templateOption.id;
        input.dataset.type = templateOption.type;

        // if options are split with "-", they are considered as value/key pairs
        let selectOptions = templateOption.placeholder.split("/")
        for (let selectOption of selectOptions) {
            let index = selectOption.indexOf('-');
            let keyValue = [selectOption.substring(0, index), selectOption.substring(index + 1)];
            value = selectOption;
            text = selectOption;
            if (keyValue.length >> 1) {
                value = keyValue[0];
                text = keyValue[1];
            }
            let option = document.createElement('option');
            option.value = value;
            option.innerText = text;
            input.appendChild(option);
        }
        return input;
    },

    /**
     * Reveal additional options in the sidebar
     */
    showHiddenOptions: function () {
        const dataSourceOptionsDiv = document.getElementById("dataSourceOptions");
        const divElements = dataSourceOptionsDiv.children;

        for (let i = 0; i < divElements.length; i++) {
            if (divElements[i].tagName.toLowerCase() === "div") {
                divElements[i].style.display = "table-row";
            }
        }
        document.getElementById('optionSectionHeader').parentElement.remove();
    },

    /**
     * Request data preview and show column picker dialog
     */
    handleColumnPicker: function () {
        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Column Picker'),
            OCA.Analytics.Datasource.processColumnPickerDialog
        );

        // Get the values from all input fields but not the column picker
        // they are used to get the data from the data source
        let option = {};
        let inputFields = document.querySelectorAll('#dataSourceOptions input, #dataSourceOptions select');
        for (let inputField of inputFields) {
            if (inputField.dataset.type !== 'columnPicker') option[inputField.id] = inputField.value;
        }

        let requestUrl = OC.generateUrl('apps/analytics/data');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                type: parseInt(document.getElementById('dataSourceType').innerText),
                options: JSON.stringify(option),
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                OCA.Analytics.Datasource.createColumnPickerContent(data);
            })
            .catch(error => {
                // stop if the file link is missing
                OCA.Analytics.Notification.notification('error', t('analytics', 'Parameter missing'));
                OCA.Analytics.Notification.dialogClose();
            });
    },

    /**
     * Render the column picker list from provided data
     */
    createColumnPickerContent: function (data) {
        // Array of items
        const items = data.data[0].map((value, index) => {
            return {
                id: index + 1,
                name: data.header[index],
                text: value,
                checked: true
            };
        });

        let selectionArray = document.querySelector('[data-type="columnPicker"]').value.split(',').map(str => parseInt(str));

        // sort the items and put selected ones in front
        items.sort((a, b) => {
            const indexA = selectionArray.indexOf(a.id);
            const indexB = selectionArray.indexOf(b.id);
            if (indexA < 0) return indexB >= 0 ? 1 : 0;
            if (indexB < 0) return -1;
            return indexA - indexB;
        });

        // selected ones should get the checkbox true
        items.forEach((item) => {
            item.checked = selectionArray.includes(item.id);
        });

        // create the list element
        const list = document.createElement("ul");
        list.id = 'sortable-list';
        list.style.display = 'inline-block';
        list.style.listStyle = 'none';
        list.style.margin = '0';
        list.style.padding = '0';
        list.style.width = "400px"
        items.forEach((item) => {
            list.appendChild(OCA.Analytics.Datasource.buildColumnPickerRow(item));
        });

        // create the button below to add a custom column
        const button = document.createElement('div');
        button.innerText = t('analytics', 'Add custom column');
        button.style.backgroundPosition = '5px center';
        button.style.paddingLeft = '25px';
        button.classList.add('icon-add', 'sidebarPointer');
        button.id = 'addColumnButton';
        button.addEventListener('click', OCA.Analytics.Datasource.addFixedColumn);

        // create span for reference to the old values
        const hint = document.createElement('span');
        hint.style.paddingLeft = '25px';
        hint.classList.add('userGuidance');
        hint.id = 'addColumnHint'
        hint.innerText

        const content = document.createDocumentFragment();
        content.appendChild(list);
        content.appendChild(document.createElement('br'));
        content.appendChild(document.createElement('br'));
        content.appendChild(button);
        content.appendChild(document.createElement('br'));
        content.appendChild(hint);

        OCA.Analytics.Notification.htmlDialogUpdate(
            content,
            t('analytics', 'Select the required columns.<br>Rearrange the sequence with drag & drop.<br>Remove all selections to reset.<br>Add custom columns including text variables for dates. See the {linkstart}Wiki{linkend}.')
                .replace('{linkstart}', '<a href="https://github.com/Rello/analytics/wiki/Filter,-chart-options-&-drilldown#text-variables" target="_blank">')
                .replace('{linkend}', '</a>')
        );
    },

    /**
     * Append a new custom column to the picker
     */
    addFixedColumn: function () {
        const currentColumns = document.querySelector('input[data-type="columnPicker"]').value;
        const preText = t('analytics', 'Previous Values:');
        document.getElementById('addColumnHint').innerText = preText + ' ' + currentColumns;

        const sortableList = document.querySelector("#analyticsDialogContent > #sortable-list");
        const item = {
            id: 'fixedColumn',
            name: t('analytics', 'Enter the fixed value:'),
            text: 'new',
            checked: true,
            contenteditable: true
        }
        sortableList.appendChild(OCA.Analytics.Datasource.buildColumnPickerRow(item));
    },

    /**
     * Build a draggable list row for the column picker
     */
    buildColumnPickerRow: function (item) {
        const li = document.createElement("li");
        li.style.display = 'flex';
        li.style.alignItems = 'center';
        li.style.margin = '5px';
        li.style.backgroundColor = 'var(--color-background-hover)';
        li.draggable = true;
        li.addEventListener("dragstart", OCA.Analytics.Notification.handleDragStart);
        li.addEventListener("dragover", OCA.Analytics.Notification.handleDragOver);
        li.addEventListener("drop", OCA.Analytics.Notification.handleDrop);

        const gripLines = document.createElement('div')
        gripLines.classList.add('icon-analytics-gripLines', 'sidebarPointer');

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.id = item.id;
        checkbox.checked = item.checked;

        const span = document.createElement("span");
        span.textContent = item.name;
        span.style.marginLeft = '10px';
        const spanContent = document.createElement("span");
        spanContent.textContent = item.text;
        spanContent.style.marginLeft = '10px';
        spanContent.style.color = 'var(--color-text-maxcontrast)';
        item.contenteditable === true ? spanContent.contentEditable = 'true' : false;
        li.appendChild(gripLines);
        li.appendChild(checkbox);
        li.appendChild(span);
        li.appendChild(spanContent);
        return li;
    },

    /**
     * Collect chosen columns from the picker dialog
     */
    processColumnPickerDialog: function () {
        //get the list and sequence of the selected items
        const checkboxList = document.querySelectorAll('#sortable-list input[type="checkbox"]');
        const checkboxIds = [];

        checkboxList.forEach(function (checkbox) {
            let id = checkbox.id;

            // if it is a custom column, the entered text has to be used as id
            if (id === 'fixedColumn') {
                const li = checkbox.closest('li');
                const secondSpan = li.querySelector('span[contenteditable]');
                id = secondSpan.textContent;
            }
            if (checkbox.checked) {
                checkboxIds.push(id);
            }
        });
        document.querySelector('[data-type="columnPicker"]').value = checkboxIds;
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Open a file picker dialog for selecting a file path
     */
    handleFilepicker: function () {
        let type = parseInt(document.getElementById('dataSourceType').innerText);

        let mime;
        if (type === OCA.Analytics.TYPE_INTERNAL_FILE) {
            mime = ['text/csv', 'text/plain'];
        } else if (type === OCA.Analytics.TYPE_SPREADSHEET) {
            mime = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.ms-excel'];
        }
        OC.dialogs.filepicker(
            t('analytics', 'Select file'),
            function (path) {
                document.querySelector('[data-type="filePicker"]').value = path;
            },
            false,
            mime,
            true,
            1);
    },
});

OCA.Analytics.Share = OCA.Analytics.Share || {};
Object.assign(OCA.Analytics.Share = {

    searchTimeout: null,
    searchDelay: 300,

    buildShareModal: function (evt) {
        if (document.querySelector('.app-navigation-entry-menu.open') !== null) {
            document.querySelector('.app-navigation-entry-menu.open').classList.remove('open');
        }

        let navigationItem = evt.target.closest('div');

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Share') + ' ' + navigationItem.dataset.name,
            OCA.Analytics.Notification.dialogClose
        );

        OCA.Analytics.Share.updateShareModal(navigationItem.dataset.item_type, navigationItem.dataset.id);
    },

    updateShareModal: function (itemType, itemId) {
        if (!itemType) {
            itemType = document.getElementById('shareItemType').value;
        }
        if (!itemId) {
            itemId = document.getElementById('shareItemId').value;
        }

        // clone the DOM template
        let template = document.getElementById('templateShareModal').content;
        template = document.importNode(template, true);
        template.getElementById('linkShareList').appendChild(OCA.Analytics.Share.buildShareLinkRow(0, 0, true));
        template.getElementById('shareInput').addEventListener('keyup', OCA.Analytics.Share.searchShareeAPI);

        template.getElementById('shareItemType').value = itemType;
        template.getElementById('shareItemId').value = itemId;

        OCA.Analytics.Notification.htmlDialogUpdate(
            template,
            t('analytics', 'Select the share receiver')
        );

        let requestUrl = OC.generateUrl('apps/analytics/share/') + itemType + '/' + itemId;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                if (data !== false) {
                    for (let share of data) {
                        if (parseInt(share.type) === OCA.Analytics.SHARE_TYPE_LINK) {
                            let li = OCA.Analytics.Share.buildShareLinkRow(parseInt(share['id']), share['token'], false, (String(share['pass']) === "true"), parseInt(share['permissions']), share['domain']);
                            document.getElementById('linkShareList').appendChild(li);
                        } else {
                            if (!share['displayName']) {
                                share['displayName'] = share['uid_owner'];
                            }
                            let li = OCA.Analytics.Share.buildShareeRow(parseInt(share['id']), share['uid_owner'], share['displayName'], parseInt(share['type']), false, parseInt(share['permissions']));
                            document.getElementById('shareeList').appendChild(li);
                        }
                    }
               } else {
                    let table = '<div style="margin-left: 2em;" class="get-metadata"><p>' + t('analytics', 'No changes possible') + '</p></div>';
                    //document.getElementById('tabContainerShare').innerHTML = table;
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
            linkRow.getElementById('newLinkShare').addEventListener('click', OCA.Analytics.Share.createShare);
        } else {
            linkRow.getElementById('sharingOptionsGroupNew').remove();
            linkRow.getElementById('shareOpenDirect').href = OC.generateUrl('/apps/analytics/p/') + token;
            linkRow.getElementById('shareClipboardLink').value = OC.getProtocol() + '://' + OC.getHostName() + (OC.getPort() !== '' ? ':' + OC.getPort() : '') + OC.generateUrl('/apps/analytics/p/') + token;
            linkRow.getElementById('shareClipboard').addEventListener('click', OCA.Analytics.Share.handleShareClipboard)
            linkRow.getElementById('moreIcon').addEventListener('click', OCA.Analytics.Share.showShareMenu);
            linkRow.getElementById('showPassword').addEventListener('click', OCA.Analytics.Share.showPassMenu);
            linkRow.getElementById('showPassword').nextElementSibling.htmlFor = 'showPassword' + id;
            linkRow.getElementById('showPassword').id = 'showPassword' + id;

            linkRow.getElementById('shareChart').addEventListener('click', OCA.Analytics.Share.showChartMenu);
            linkRow.getElementById('shareChart').nextElementSibling.htmlFor = 'shareChart' + id;
            linkRow.getElementById('shareChart').id = 'shareChart' + id;
            linkRow.getElementById('shareChartDomain').id = 'shareChartDomain' + id;
            linkRow.getElementById('shareChartLink').value = OC.getProtocol() + '://' + OC.getHostName() + OC.generateUrl('/apps/analytics/pm/') + token;
            linkRow.getElementById('shareChartClipboard').addEventListener('click', OCA.Analytics.Share.handleShareChartClipboard)

            linkRow.getElementById('linkPassSubmit').addEventListener('click', OCA.Analytics.Share.updateSharePassword);
            linkRow.getElementById('linkPassSubmit').dataset.id = id;
            linkRow.getElementById('shareChartSubmit').addEventListener('click', OCA.Analytics.Share.updateShareDomain);
            linkRow.getElementById('shareChartSubmit').dataset.id = id;
            linkRow.getElementById('deleteShareIcon').addEventListener('click', OCA.Analytics.Share.removeShare);
            linkRow.getElementById('deleteShare').dataset.id = id;
            if (pw) {
                linkRow.getElementById('linkPassMenu').classList.remove('hidden');
                linkRow.getElementById('showPassword' + id).checked = true;
            }
            if (domain) {
                linkRow.getElementById('shareChart' + id).click();
                linkRow.getElementById('shareChartDomain' + id).value = domain;
            }
            linkRow.getElementById('shareEditing').addEventListener('click', OCA.Analytics.Share.updateShareCanEdit);
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
        shareeRow.getElementById('icon-more').addEventListener('click', OCA.Analytics.Share.showShareMenu);
        shareeRow.getElementById('deleteShare').addEventListener('click', OCA.Analytics.Share.removeShare);
        shareeRow.getElementById('deleteShare').dataset.id = id;
        shareeRow.getElementById('shareEditing').addEventListener('click', OCA.Analytics.Share.updateShareCanEdit);
        shareeRow.getElementById('shareEditing').dataset.id = id;
        shareeRow.getElementById('shareEditing').nextElementSibling.htmlFor = 'shareEditing' + id;
        shareeRow.getElementById('shareEditing').id = 'shareEditing' + id;
        if (permissions === OC.PERMISSION_UPDATE) {
            shareeRow.getElementById('shareEditing' + id).checked = true;
        }

        if (isSearch) {
            shareeRow.getElementById('username').addEventListener('click', OCA.Analytics.Share.createShare);
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
        let itemType = document.getElementById('shareItemType').value;
        let itemId = document.getElementById('shareItemId').value;

        let shareType = evt.target.dataset.shareType;
        let shareUser = evt.target.dataset.user;

        let requestUrl = OC.generateUrl('apps/analytics/share');
        fetch(requestUrl, {
            method: 'POST',
            headers: OCA.Analytics.headers(),
            body: JSON.stringify({
                item_type: itemType,
                item_source: itemId,
                type: shareType,
                user: shareUser,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Share.updateShareModal();
            });
    },

    removeShare: function (evt) {
        let shareId = evt.target.parentNode.dataset.id;
        let requestUrl = OC.generateUrl('apps/analytics/share/') + shareId;
        fetch(requestUrl, {
            method: 'DELETE',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Share.updateShareModal();
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
                OCA.Analytics.Share.updateShareModal();
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
                OCA.Analytics.Share.updateShareModal();
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
                OCA.Analytics.Share.updateShareModal();
            });
    },

    searchShareeAPI: function () {
        clearTimeout(OCA.Analytics.Share.searchTimeout);
        OCA.Analytics.Share.searchTimeout = setTimeout(OCA.Analytics.Share._executeShareSearch, OCA.Analytics.Share.searchDelay);
    },

    _executeShareSearch: function () {
        const shareInput = document.getElementById('shareInput').value;
        const resultElement = document.getElementById('shareSearchResult');
        if (shareInput === '') {
            resultElement.innerHTML = '';
            resultElement.style.display = 'none';
            return;
        }

        const URL = OC.linkToOCS('apps/files_sharing/api/v1/sharees').slice(0, -1);
        const params = 'format=json'
            + '&itemType=file'
            + '&search=' + shareInput
            + '&lookup=false&perPage=200'
            + '&shareType[]=0&shareType[]=1';

        const requestUrl = URL + '?' + params;
        fetch(requestUrl, {
            method: 'GET',
            headers: OCA.Analytics.headers(),
        })
            .then(response => response.json())
            .then(data => {
                const jsondata = data;
                if (jsondata['ocs']['meta']['status'] === 'ok') {
                    resultElement.style.display = '';

                    const existingItems = resultElement.querySelectorAll('li.shareSearchResultItem');
                    const existingMap = {};
                    existingItems.forEach(li => {
                        const username = li.querySelector('#username').dataset.user;
                        const type = li.querySelector('#username').dataset.shareType;
                        existingMap[type + '|' + username] = li;
                    });

                    const newKeys = new Set();
                    const addItem = (shareWith, label, type) => {
                        const key = type + '|' + shareWith;
                        newKeys.add(key);
                        if (!existingMap[key]) {
                            const sharee = OCA.Analytics.Share.buildShareeRow(0, shareWith, label, type, true);
                            resultElement.appendChild(sharee);
                        } else {
                            const elem = existingMap[key].querySelector('#username');
                            if (elem.innerText !== label && type === OCA.Analytics.SHARE_TYPE_USER) {
                                elem.innerText = label;
                            }
                        }
                    };

                    for (let user of jsondata['ocs']['data']['users']) {
                        addItem(user['value']['shareWith'], user['label'], OCA.Analytics.SHARE_TYPE_USER);
                    }
                    for (let exactUser of jsondata['ocs']['data']['exact']['users']) {
                        addItem(exactUser['value']['shareWith'], exactUser['label'], OCA.Analytics.SHARE_TYPE_USER);
                    }
                    for (let group of jsondata['ocs']['data']['groups']) {
                        addItem(group['value']['shareWith'], group['label'], OCA.Analytics.SHARE_TYPE_GROUP);
                    }
                    for (let exactGroup of jsondata['ocs']['data']['exact']['groups']) {
                        addItem(exactGroup['value']['shareWith'], exactGroup['label'], OCA.Analytics.SHARE_TYPE_GROUP);
                    }
                    for (let room of jsondata['ocs']['data']['rooms']) {
                        addItem(room['value']['shareWith'], room['label'], OCA.Analytics.SHARE_TYPE_ROOM);
                    }

                    Object.keys(existingMap).forEach(key => {
                        if (!newKeys.has(key)) {
                            existingMap[key].remove();
                        }
                    });
                } else {
                    resultElement.style.display = 'none';
                    resultElement.innerHTML = '';
                }
            });
    },
})