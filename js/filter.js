/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: OCP */
/** global: OC */
/** global: table */
/** global: Chart */
/** global: cloner */
/** global: Headers */

'use strict';
/**
 * @namespace OCA.Analytics.Filter
 */
OCA.Analytics.Filter = {
    optionTextsArray: {
        'EQ': t('analytics', 'equal to'),
        'GT': t('analytics', 'greater than'),
        'LT': t('analytics', 'less than'),
        'LIKE': t('analytics', 'contains'),
        'IN': t('analytics', 'list of values'),
    },

    /**
     * Update indicator state of report menu options
     */
    updateReportMenuIndicators: function () {
        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions || {};
        const map = {
            drilldown: 'optionsMenuColumnSelection',
            sort: 'optionsMenuSort',
            topN: 'optionsMenuTopN',
            timeAggregation: 'optionsMenuTimeAggregation'
        };
        for (const [key, id] of Object.entries(map)) {
            const el = document.getElementById(id);
            if (!el) {
                continue;
            }
            let active;
            if (key === 'drilldown') {
                active = !!(filterOptions.drilldown && Object.keys(filterOptions.drilldown).length);
            } else {
                active = filterOptions[key] !== undefined;
            }
            if (active) {
                el.classList.add('report-option-active');
            } else {
                el.classList.remove('report-option-active');
            }
        }
    },

    /**
     * Display the drilldown configuration dialog allowing the user
     * to enable or disable individual dimensions for aggregation.
     */
    openColumnsSelectionDialog: function () {
        OCA.Analytics.Report.hideReportMenu();

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Column selection'),
            OCA.Analytics.Filter.processColumnsSelectionDialog
        );

        const container = document.importNode(document.getElementById('templateDrilldownOptions').content, true);
        const table = container.getElementById('drilldownOptionsTable');

        const availableDimensions = OCA.Analytics.currentReportData.dimensions;
        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions;

        const fragment = document.createDocumentFragment();
        Object.keys(availableDimensions).forEach((dimension, index) => {
            const row = document.createElement('div');
            row.style.display = 'table-row';

            const cellLabel = document.createElement('div');
            cellLabel.style.display = 'table-cell';
            cellLabel.textContent = availableDimensions[dimension];
            row.appendChild(cellLabel);

            const cellCheckbox = document.createElement('div');
            cellCheckbox.style.display = 'table-cell';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = 'drilldownColumn' + index;
            checkbox.className = 'checkbox';
            checkbox.name = 'drilldownColumn';
            checkbox.value = dimension;
            if (!(filterOptions['drilldown'] !== undefined && filterOptions['drilldown'][dimension] !== undefined)) {
                checkbox.checked = true;
            }
            const label = document.createElement('label');
            label.setAttribute('for', checkbox.id);
            label.textContent = ' ';
            cellCheckbox.appendChild(checkbox);
            cellCheckbox.appendChild(label);
            row.appendChild(cellCheckbox);

            fragment.appendChild(row);
        });
        table.appendChild(fragment);

        OCA.Analytics.Notification.htmlDialogUpdate(container,
            t('analytics', 'Removing columns will aggregate the key figures.<br>This applies to the chart and table')
        );
    },

    /**
     * Persist the drilldown dialog selections and reload the report.
     */
    processColumnsSelectionDialog: function () {
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        let drilldownColumns = document.getElementsByName('drilldownColumn');

        for (let i = 0; i < drilldownColumns.length; i++) {
            let dimension = drilldownColumns[i].value;
            if (drilldownColumns[i].checked === false) {
                if (filterOptions['drilldown'] === undefined) {
                    filterOptions['drilldown'] = {};
                }
                filterOptions['drilldown'][dimension] = false;
            } else {
                if (filterOptions['drilldown'] !== undefined && filterOptions['drilldown'][dimension] !== undefined) {
                    delete filterOptions['drilldown'][dimension];
                }
                if (filterOptions['drilldown'] !== undefined && Object.keys(filterOptions['drilldown']).length === 0) {
                    delete filterOptions['drilldown'];
                }
            }
        }

        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Show the filter dialog where a dimension, comparison operator
     * and value can be selected for report filtering.
     */
    openFilterDialog: function () {
        OCA.Analytics.Report.hideReportMenu();

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Filter'),
            OCA.Analytics.Filter.processFilterDialog
        );

        let container = document.getElementById('templateFilterDialog').content;
        container = document.importNode(container, true);

        const availableDimensions = OCA.Analytics.currentReportData.dimensions;
        const optionTexts = OCA.Analytics.Filter.optionTextsArray;
        const table = container.getElementById('filterDialogTable');
        const addButton = container.getElementById('addFilterRowButton');

        const initRow = function (row, dimension = '', option = '', value = '') {
            const dimSelect = row.querySelector('.filterDialogDimension');
            dimSelect.innerHTML = '';
            Object.keys(availableDimensions).forEach(key => {
                dimSelect.options.add(new Option(availableDimensions[key], key));
            });
            if (dimension) {
                dimSelect.value = dimension;
            }
            dimSelect.dataset.prevValue = dimSelect.value;
            dimSelect.addEventListener('change', function (evt) {
                // prevent duplicate dimensions
                const selected = evt.target.value;
                let count = 0;
                container.querySelectorAll('.filterDialogDimension').forEach(sel => {
                    if (sel.value === selected) {
                        count++;
                    }
                });
                if (count > 1) {
                    evt.target.value = evt.target.dataset.prevValue;
                    return;
                }
                evt.target.dataset.prevValue = selected;
                row.querySelector('.filterDialogValue').dataset.dropdownlistindex = evt.target.selectedIndex;
            });

            const optSelect = row.querySelector('.filterDialogOption');
            optSelect.innerHTML = '';
            Object.keys(optionTexts).forEach(key => {
                optSelect.options.add(new Option(optionTexts[key], key));
            });
            if (option) {
                optSelect.value = option;
            }

            const valueInput = row.querySelector('.filterDialogValue');
            valueInput.value = value;
            valueInput.addEventListener('click', OCA.Analytics.Report.showDropDownList);
            valueInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    OCA.Analytics.Filter.processFilterDialog();
                }
            });
            valueInput.dataset.dropdownlistindex = dimSelect.selectedIndex;

            const removeIcon = row.querySelector('.icon-analytics-filterRow-remove');
            if (removeIcon) {
                removeIcon.addEventListener('click', function () {
                    row.remove();
                });
            }
        };

        const addRow = function (dimension = '', option = '', value = '') {
            const rowTemplate = document.getElementById('templateFilterDialogRow').content;
            const newRow = document.importNode(rowTemplate, true).firstElementChild;
            table.appendChild(newRow);
            initRow(newRow, dimension, option, value);
        };

        // initialize first row
        const firstRow = table.querySelector('.filterRow');

        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        let existing = [];
        if (filterOptions && filterOptions.filter) {
            existing = Object.entries(filterOptions.filter);
        }

        if (existing.length > 0) {
            const [firstDim, firstData] = existing.shift();
            initRow(firstRow, firstDim, firstData.option, firstData.value);
            existing.forEach(([d, data]) => {
                addRow(d, data.option, data.value);
            });
        } else {
            initRow(firstRow);
        }

        addButton.addEventListener('click', function () {
            addRow();
        });

        OCA.Analytics.Notification.htmlDialogUpdate(
            container,
            t('analytics', 'Dynamic text variables can be used to select dates.<br>The selection is written between two % (e.g. %last2months%).<br>Information on available filters and alternative date formats is available in the {linkstart}Wiki{linkend}.')
        );
    },

    /**
     * Store filter dialog settings to the current report and reload data.
     */
    processFilterDialog: function () {
        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions || (OCA.Analytics.currentReportData.options.filteroptions = {});
        filterOptions.filter = {};

        const rows = document.querySelectorAll('#filterDialogTable .filterRow');
        rows.forEach(row => {
            const dimension = row.querySelector('.filterDialogDimension').value;
            if (dimension === '') {
                return;
            }
            const optionValue = row.querySelector('.filterDialogOption').value;
            const filterValue = row.querySelector('.filterDialogValue').value;
            filterOptions.filter[dimension] = {option: optionValue, value: filterValue};
        });

        if (Object.keys(filterOptions.filter).length === 0) {
            delete filterOptions.filter;
        }

        // Update global state
        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Open the Top N dialog used to limit results to top/flop lists
     * or to group remaining values under an "others" category.
     */
    openTopNDialog: function () {
        OCA.Analytics.Report.hideReportMenu();

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Top N'),
            OCA.Analytics.Filter.processTopNDialog
        );

        const container = document.importNode(document.getElementById('templateTopNOptions').content, true);

        const headerArray = OCA.Analytics.currentReportData.header;
        const dimensionSelect = container.getElementById('groupOptionDimension');
        dimensionSelect.innerHTML = '';
        headerArray.forEach((header, index) => {
            dimensionSelect.options.add(new Option(header, index));
        });

        const typeSelect = container.getElementById('groupOptionType');
        typeSelect.innerHTML = '';
        const typeOptions = [
            {value: 'none', text: t('analytics', 'none')},
            {value: 'top', text: t('analytics', 'Top N')},
            {value: 'flop', text: t('analytics', 'Flop N')}
        ];
        typeOptions.forEach(({value, text}) => {
            typeSelect.options.add(new Option(text, value));
        });

        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        if (filterOptions !== null && filterOptions['topN'] !== undefined) {
            dimensionSelect.value = filterOptions.topN.dimension;
            typeSelect.value = filterOptions.topN.type;
            container.getElementById('groupOptionNumber').value = filterOptions.topN.number;
            container.getElementById('groupOptionOthers').checked = filterOptions.topN.others === true;
        }

        OCA.Analytics.Notification.htmlDialogUpdate(
            container,
            t('analytics', 'Results can be limited to Top or Flop N. Remaining values may be summarized as "others".')
        );
    },

    /**
     * Apply grouping settings from the dialog and refresh the report.
     */
    processTopNDialog: function () {
        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions || (OCA.Analytics.currentReportData.options.filteroptions = {});

        if (!filterOptions.topN) {
            filterOptions.topN = {};
        }

        filterOptions.topN.dimension = document.getElementById('groupOptionDimension').value;
        filterOptions.topN.type = document.getElementById('groupOptionType').value;
        filterOptions.topN.number = parseInt(document.getElementById('groupOptionNumber').value);
        filterOptions.topN.others = document.getElementById('groupOptionOthers').checked;

        if (filterOptions.topN.type === 'none' || isNaN(filterOptions.topN.number)) {
            delete filterOptions.topN;
            // cleanup old legend settings where all items were displayed in a top N
            let dataOptions = OCA.Analytics.currentReportData.options.dataoptions;

            // Keep only the first 4 elements
            dataOptions = dataOptions.length > 4 ? dataOptions.slice(0, 4) : dataOptions;

            // Check if all first 4 elements are empty objects
            const allEmpty = dataOptions.length > 0 && dataOptions.every(obj => Object.keys(obj).length === 0 && obj.constructor === Object);

            OCA.Analytics.currentReportData.options.dataoptions = allEmpty ? [] : dataOptions;
        }

        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        // remove all data options for which there is no dimension anymore
        if (filterOptions.topN && OCA.Analytics.currentReportData.options.dataoptions >> filterOptions.topN.number) {
            OCA.Analytics.currentReportData.options.dataoptions.splice(filterOptions.topN.number);
        }
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Display the dialog for grouping time based dimensions.
     */
    openTimeAggregationDialog: function () {
        OCA.Analytics.Report.hideReportMenu();

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Time aggregation'),
            OCA.Analytics.Filter.processTimeAggregationDialog
        );

        const container = document.importNode(document.getElementById('templateTimeAggregationOptions').content, true);

        const dimensions = OCA.Analytics.currentReportData.dimensions;
        const dimSelect = container.getElementById('timeGroupingDimension');
        dimSelect.innerHTML = '';
        Object.keys(dimensions).forEach(key => {
            dimSelect.options.add(new Option(dimensions[key], key));
        });

        const groupingSelect = container.getElementById('timeGroupingGrouping');
        ['none', 'day', 'week', 'month', 'year'].forEach(val => {
            groupingSelect.options.add(new Option(t('analytics', val), val));
        });

        const modeSelect = container.getElementById('timeGroupingMode');
        [{value: 'summation', text: t('analytics', 'summation')}, {value: 'average', text: t('analytics', 'average')}].forEach(({value, text}) => {
            modeSelect.options.add(new Option(text, value));
        });

        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        if (filterOptions && filterOptions.timeAggregation) {
            dimSelect.value = filterOptions.timeAggregation.dimension;
            groupingSelect.value = filterOptions.timeAggregation.grouping;
            modeSelect.value = filterOptions.timeAggregation.mode;
        }

        OCA.Analytics.Notification.htmlDialogUpdate(
            container,
            t('analytics', 'Aggregate daily data into weeks, months, or years')
        );
    },

    /**
     * Save the selected time grouping configuration and refresh data.
     */
    processTimeAggregationDialog: function () {
        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions || (OCA.Analytics.currentReportData.options.filteroptions = {});

        const grouping = document.getElementById('timeGroupingGrouping').value;

        if (!filterOptions.timeAggregation) {
            filterOptions.timeAggregation = {};
        }

        filterOptions.timeAggregation.dimension = document.getElementById('timeGroupingDimension').value;
        filterOptions.timeAggregation.grouping = grouping;
        filterOptions.timeAggregation.mode = document.getElementById('timeGroupingMode').value;

        if (grouping === 'none') {
            delete filterOptions.timeAggregation;
        }

        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Render the currently active filters as clickable labels.
     */
    refreshFilterVisualisation: function () {
        const visContainer = document.getElementById("filterVisualisation");
        visContainer.innerHTML = "";
        const fragment = document.createDocumentFragment();
        const filterDimensions = OCA.Analytics.currentReportData.dimensions;
        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        if (filterOptions && filterOptions["filter"]) {
            for (const filterDimension of Object.keys(filterOptions["filter"])) {
                let optionText = OCA.Analytics.Filter.optionTextsArray[filterOptions["filter"][filterDimension]["option"]];
                const container = document.createElement("span");
                let filterValue = filterOptions["filter"][filterDimension]["value"];
                if (filterValue.match(/%/g) && filterValue.match(/%/g).length === 2) {
                    optionText = "";
                    filterValue = filterValue.replace(/%/g, "");
                    filterValue = filterValue.replace(/\(.*?\)/g, "");
                }

                const removeIcon = document.createElement("span");
                removeIcon.classList.add("filterVisualizationRemove", "icon-close");
                removeIcon.addEventListener("click", OCA.Analytics.Filter.removeFilter);

                const textSpan = document.createElement("span");
                textSpan.innerText = filterDimensions[filterDimension] + " " + optionText + " " + filterValue;

                container.classList.add("filterVisualizationItem");
                container.id = filterDimension;
                container.appendChild(removeIcon);
                container.appendChild(textSpan);

                fragment.appendChild(container);
            }
        }
        visContainer.appendChild(fragment);
        OCA.Analytics.Filter.toggleSaveButtonDisplay();

        // update report menu indicators for active options
        OCA.Analytics.Filter.updateReportMenuIndicators();
    },

    toggleSaveButtonDisplay: function () {
        const saveIcon = document.getElementById("saveIcon");
        if (OCA.Analytics.unsavedChanges === true) {
            saveIcon.style.removeProperty("display");
        } else {
            saveIcon.style.display = "none";
        }
    },

    handleSaveButton: function (evt) {
        const type = OCA.Analytics.currentContentType;
        const handler = OCA.Analytics.handlers['saveIcon']?.[type];
        if (handler) {
            handler(evt);
        }
    },

    /**
     * Remove a single active filter label and reload the data.
     */
    removeFilter: function (evt) {
        const parent = evt.target.closest('.filterVisualizationItem');
        let filterDimension = parent ? parent.id : evt.target.id;
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        delete filterOptions['filter'][filterDimension];
        if (Object.keys(filterOptions['filter']).length === 0) {
            delete filterOptions['filter'];
        }
        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
    },

    /**
     * Launch the table options dialog for configuring layout and
     * appearance of the table visualization.
     */
    openTableOptionsDialog: function () {
        OCA.Analytics.Report.hideReportMenu();

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Table options'),
            OCA.Analytics.Filter.processTableOptionsDialog
        );

        // clone the DOM template
        let container = document.getElementById('templateTableOptions').content;
        container = document.importNode(container, true);

        // Attach event listeners programmatically
        container.querySelectorAll('.columnSection').forEach(section => {
            section.addEventListener('drop', OCA.Analytics.Filter.Drag.drop);
            section.addEventListener('dragover', OCA.Analytics.Filter.Drag.allowDrop);
        });
        container.getElementById('tableOptionsResetState').addEventListener('click', OCA.Analytics.Filter.processTableOptionsReset);

        let tableOptions = OCA.Analytics.currentReportData.options.tableoptions;

        if (tableOptions && tableOptions.footer) {
            container.querySelector('input[name="totalOption"][value="true"]').checked = true;
        }

        if (tableOptions && tableOptions.formatLocales !== undefined) {
            container.querySelector('input[name="formatLocalesOption"][value="false"]').checked = true;
        }

        if (tableOptions && tableOptions.compactDisplay !== undefined) {
            container.querySelector('input[name="compactDisplayOption"][value="true"]').checked = true;
        }

        if (tableOptions && tableOptions.calculatedColumns) {
            container.getElementById('tableOptionsCalculatedColumns').value = tableOptions.calculatedColumns;
        }

        OCA.Analytics.Notification.htmlDialogUpdate(
            container,
            t('analytics', 'The table can be customized by positioning the rows and columns. <br>If a classic list view is required, all fields need to be placed in the rows section.')
        );

        OCA.Analytics.Filter.Drag.initialize();

        OCA.Analytics.Sidebar.assignSectionHeaderClickEvents();
    },

    /**
     * Read the values from the table options dialog and apply them
     * to the current report before requesting fresh data.
     */
    processTableOptionsDialog: function () {
        let tableOptions = OCA.Analytics.currentReportData.options.tableoptions;

        const showTotalsSelected = document.querySelector('input[name="totalOption"]:checked');
        const showTotals = showTotalsSelected ? showTotalsSelected.value : null;
        if (showTotals === 'true') {
            tableOptions.footer = true;
        } else if (tableOptions) {
            delete tableOptions.footer;
        }

        const formatLocalesSelected = document.querySelector('input[name="formatLocalesOption"]:checked');
        const formatLocales = formatLocalesSelected ? formatLocalesSelected.value : null;
        // default is, that the locales are calculated
        if (formatLocales === 'false') {
            tableOptions.formatLocales = false;
        } else if (tableOptions) {
            delete tableOptions.formatLocales;
        }

        // Compact view for table with less line space and bold first column
        const compactDisplaySelected = document.querySelector('input[name="compactDisplayOption"]:checked');
        const compactDisplay = compactDisplaySelected ? compactDisplaySelected.value : null;
        if (compactDisplay === 'true') {
            tableOptions.compactDisplay = true;
        } else if (tableOptions) {
            delete tableOptions.compactDisplay;
        }

        let layout = {};
        document.querySelectorAll('.columnSection').forEach(function (section) {
            const sectionId = section.id;
            // Initialize the layout[sectionId] as an empty array if undefined
            if (!layout[sectionId]) {
                layout[sectionId] = [];
            }
            const childNodes = section.getElementsByClassName('draggable');
            for (let i = 0; i < childNodes.length; i++) {
                const columnId = parseInt(childNodes[i].id.replace('column-', ''));
                layout[sectionId].push(columnId);
            }
        });

        const isSequential = (arr) => arr.every((val, i, array) => i === 0 || (val === array[i - 1] + 1));
        if (layout.columns.length === 0 && layout.measures.length === 0 && layout.notRequired.length === 0) {
            if (!isSequential(layout.rows)) {
                tableOptions.layout = layout;
            } else if (tableOptions && tableOptions.layout) {
                delete tableOptions.layout;
            }
        } else {
            tableOptions.layout = layout;
        }

        let calculatedColumns = document.getElementById('tableOptionsCalculatedColumns').value;
        if (calculatedColumns !== '') {
            tableOptions.calculatedColumns = calculatedColumns;
        }

        if (OCA.Analytics.currentReportData.options.tableoptions !== tableOptions) {
            delete tableOptions.colReorder;
        }

        OCA.Analytics.currentReportData.options.tableoptions = tableOptions;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Reset all saved table settings to their defaults.
     */
    processTableOptionsReset: function () {
        if (OCA.Analytics.currentReportData.options.tableoptions !== null) {
            OCA.Analytics.currentReportData.options.tableoptions = null;
            OCA.Analytics.unsavedChanges = true;
            OCA.Analytics.Report.Backend.getData();
            OCA.Analytics.Notification.dialogClose();
        }
    },

    /**
     * Open the sort configuration dialog used to define default
     * sort direction and dimension.
     */
    openSortDialog: function () {
        OCA.Analytics.Report.hideReportMenu();

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Sort order'),
            OCA.Analytics.Filter.processSortOptionsDialog
        );

        // Clone the DOM template
        const container = document.importNode(document.getElementById('templateSortOptions').content, true);

        // Get the header array
        let headerArray = OCA.Analytics.currentReportData.header;

        // Clear existing options
        let sortOptionDimension = container.getElementById('sortOptionDimension');
        sortOptionDimension.innerHTML = ''; // Clear existing options

        // Create options for every available report header column
        const fragment = document.createDocumentFragment();
        headerArray.forEach((header, index) => {
            const dimensionOption = new Option(header, index); // Create option directly
            fragment.appendChild(dimensionOption);
        });
        sortOptionDimension.appendChild(fragment); // Append all options at once

        // Define an array of options
        const directionOptions = [
            {value: 'def', text: t('analytics', 'Default')},
            {value: 'asc', text: t('analytics', 'Ascending')},
            {value: 'desc', text: t('analytics', 'Descending')}
        ];

        // Create options for sortOptionDirection
        let sortOptionDirection = container.getElementById('sortOptionDirection');
        directionOptions.forEach(({value, text}) => {
            const directionOption = new Option(text, value); // Create option directly
            sortOptionDirection.options.add(directionOption); // Add option to dropdown
        });

        // set current values
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        if (filterOptions !== null && filterOptions['sort'] !== undefined) {
            container.getElementById('sortOptionDimension').value = filterOptions.sort.dimension;
            container.getElementById('sortOptionDirection').value = filterOptions.sort.direction;
        }

        OCA.Analytics.Notification.htmlDialogUpdate(
            container,
            t('analytics', 'Sort data ascending or descending')
        );
    },

    /**
     * Save the chosen sorting dimension and direction and refresh the report.
     */
    processSortOptionsDialog: function () {
        // Ensure filterOptions is initialized properly
        const filterOptions = OCA.Analytics.currentReportData.options.filteroptions || (OCA.Analytics.currentReportData.options.filteroptions = {});

        // Initialize sort if it doesn't exist
        if (!filterOptions.sort) {
            filterOptions.sort = {};
        }

        // Set dimension and direction
        filterOptions.sort.dimension = document.getElementById('sortOptionDimension').value;
        filterOptions.sort.direction = document.getElementById('sortOptionDirection').value;

        // Remove sort if direction is 'def'
        if (filterOptions.sort.direction === 'def') {
            delete filterOptions.sort;
        }

        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Open the dialog used to configure chart specific options such
     * as chart type, axis and colors for every data series.
     */
    openChartOptionsDialog: function () {
        OCA.Analytics.Report.hideReportMenu();

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Chart options'),
            OCA.Analytics.Filter.processChartOptionsDialog
        );

        let dataOptions;
        try {
            dataOptions = OCA.Analytics.currentReportData.options.dataoptions;
        } catch (e) {
            dataOptions = '';
        }
        const distinctCategories = OCA.Analytics.Core.getDistinctValues(OCA.Analytics.currentReportData.data, 0);
        if (dataOptions === null) dataOptions = {};

        // clone the DOM template
        let container = document.getElementById('templateChartOptions').content;
        container = document.importNode(container, true);
        const table = container.getElementById('chartOptionsTable');

        // get the default chart type to preset the drop downs
        const defaultChartType = OCA.Analytics.chartTypeMapping[OCA.Analytics.currentReportData.options.chart];

        const fragment = document.createDocumentFragment();
        Object.values(distinctCategories).forEach((category, i) => {
            const color = OCA.Analytics.Filter.checkColor(dataOptions, i);

            const row = document.createElement('div');
            row.style.display = 'table-row';

            const titleCell = document.createElement('div');
            titleCell.style.display = 'table-cell';
            titleCell.id = 'optionsTitle' + i;
            titleCell.textContent = category;
            row.appendChild(titleCell);

            const yAxisCell = document.createElement('div');
            yAxisCell.style.display = 'table-cell';
            const yAxisSelect = document.createElement('select');
            yAxisSelect.id = 'optionsYAxis' + i;
            yAxisSelect.name = 'optionsYAxis';
            yAxisSelect.className = 'optionsInput';
            yAxisSelect.appendChild(new Option(t('analytics', 'Primary'), 'primary'));
            yAxisSelect.appendChild(new Option(t('analytics', 'Secondary'), 'secondary'));
            yAxisSelect.value = OCA.Analytics.Filter.checkOption(dataOptions, i, 'yAxisID', 'primary', 'primary') ? 'primary' :
                (OCA.Analytics.Filter.checkOption(dataOptions, i, 'yAxisID', 'secondary', 'primary') ? 'secondary' : 'primary');
            yAxisCell.appendChild(yAxisSelect);
            row.appendChild(yAxisCell);

            const typeCell = document.createElement('div');
            typeCell.style.display = 'table-cell';
            const typeSelect = document.createElement('select');
            typeSelect.id = 'optionsChartType' + i;
            typeSelect.name = 'optionsChartType';
            typeSelect.className = 'optionsInput';
            typeSelect.appendChild(new Option(t('analytics', 'Line'), 'line'));
            typeSelect.appendChild(new Option(t('analytics', 'Bar'), 'bar'));
            typeSelect.appendChild(new Option(t('analytics', 'Doughnut'), 'doughnut'));
            typeSelect.appendChild(new Option(t('analytics', 'Funnel'), 'funnel'));
            typeSelect.value = OCA.Analytics.Filter.checkOption(dataOptions, i, 'type', 'line', defaultChartType) ? 'line' :
                (OCA.Analytics.Filter.checkOption(dataOptions, i, 'type', 'bar', defaultChartType) ? 'bar' :
                    (OCA.Analytics.Filter.checkOption(dataOptions, i, 'type', 'doughnut', defaultChartType) ? 'doughnut' :
                        (OCA.Analytics.Filter.checkOption(dataOptions, i, 'type', 'funnel', defaultChartType) ? 'funnel' : defaultChartType)));
            typeCell.appendChild(typeSelect);
            row.appendChild(typeCell);

            const colorCell = document.createElement('div');
            colorCell.style.display = 'table-cell';
            const colorInput = document.createElement('input');
            colorInput.id = 'optionsColor' + i;
            colorInput.name = 'optionsColor';
            colorInput.className = 'optionsInput';
            colorInput.value = color;
            colorInput.style.backgroundColor = color;
            colorCell.appendChild(colorInput);
            row.appendChild(colorCell);

            fragment.appendChild(row);
        });

        table.appendChild(fragment);

        let dataModel;
        try {
            dataModel = OCA.Analytics.currentReportData.options.chartoptions["analyticsModel"];
            if (dataModel === 'accountModel') {
                container.getElementById('analyticsModelOpt2').checked = true;
            } else if (dataModel === 'timeSeriesModel') {
                container.getElementById('analyticsModelOpt3').checked = true;
            }
        } catch (e) {
            dataModel = '';
        }

        OCA.Analytics.Notification.htmlDialogUpdate(
            container,
            t('analytics', 'Select the format of the data and how it should be visualized')
        );

        const optionsColor = container.querySelectorAll('[name="optionsColor"]');
        optionsColor.forEach(field => {
            OCA.Analytics.Filter.updateColor({target: field});
            field.addEventListener('keyup', OCA.Analytics.Filter.updateColor);
        });
    },

    /**
     * Read all user configured chart options, merge them with any
     * existing settings and trigger a reload of the report.
     */
    processChartOptionsDialog: function () {
        let dataOptions = OCA.Analytics.currentReportData.options.dataoptions;
        if (dataOptions === '' || dataOptions === null) {
            dataOptions = [];
        }
        let chartOptions = OCA.Analytics.currentReportData.options.chartoptions;
        if (chartOptions === null) {
            chartOptions = [];
        }
        let userDatasetOptions = [];
        let nonDefaultValues, secondaryAxisRequired = false;
        let optionObject;

        // get the defaults (e.g. line or bar) to derive if there is any relevant change by the user
        let defaultChartType = OCA.Analytics.chartTypeMapping[OCA.Analytics.currentReportData.options.chart];
        let defaultYAxis = 'primary';
        let defaultColors = OCA.Analytics.Visualization.defaultColorPalette;

        // loop all selections from the option dialog and add them to an array
        let optionsYAxis = document.getElementsByName('optionsYAxis');
        let optionsChartType = document.getElementsByName('optionsChartType');
        let optionsColor = document.getElementsByName('optionsColor');

        for (let i = 0; i < optionsYAxis.length; i++) {
            let j = i % defaultColors.length
            optionObject = {};
            if (optionsYAxis[i].value !== defaultYAxis) {
                optionObject['yAxisID'] = optionsYAxis[i].value;
                secondaryAxisRequired = true;
            }
            if (optionsChartType[i].value !== defaultChartType) {
                optionObject['type'] = optionsChartType[i].value;
            }
            if (optionsColor[i].value !== defaultColors[j] && optionsColor[i].value !== '') {
                if (optionsColor[i].value.length === 7 && optionsColor[i].value.charAt(0) === '#') {
                    optionObject['backgroundColor'] = optionsColor[i].value;
                    optionObject['borderColor'] = optionsColor[i].value;
                }
            }
            if (Object.keys(optionObject).length) nonDefaultValues = true;
            userDatasetOptions.push(optionObject);
        }

        // decide of the data series array is relevant to be saved or not.
        // if all settings are default, all options can be removed can be removed completely
        // to keep the array clean, it will overwrite any existing settings.
        if (nonDefaultValues === true) {
            dataOptions = userDatasetOptions;
        } else {
            dataOptions = '';
        }

        let chartOptionsObj = (chartOptions && chartOptions.length !== 0) ? chartOptions : {};

        let dataModel = document.querySelector('input[name="analyticsModel"]:checked').value;
        if (dataModel === 'accountModel' || dataModel === 'timeSeriesModel') {
            let enableModel = {analyticsModel: dataModel};
            try {
                // if there are existing settings, merge them
                chartOptionsObj = cloner.deep.merge(chartOptionsObj, enableModel);
            } catch (e) {
                chartOptionsObj = enableModel;
            }
        } else {
            try {
                if (chartOptionsObj["analyticsModel"]) {
                    delete chartOptionsObj["analyticsModel"];
                }
            } catch (e) {
                // Handle error if JSON parsing fails
            }
        }

        // if any data series is tied to the secondary yAxis or not
        // if yes, it needs to be enabled in the chart options (in addition to the dataseries options)
        // additional combinations for dataMode apply
        if (secondaryAxisRequired === true) {
            let enableAxis = {scales: {secondary: {display: true}}};
            try {
                // if there are existing settings, merge them
                chartOptionsObj = cloner.deep.merge(chartOptionsObj, enableAxis);
            } catch (e) {
                chartOptionsObj = enableAxis;
            }
        } else {
            try {
                if (chartOptionsObj.scales && chartOptionsObj.scales.secondary) {
                    delete chartOptionsObj.scales.secondary;
                    // if the secondary axis is not required anymore but was enabled before
                    // the options are cleared all together
                    // this does only apply when ONLY the axis was enabled before
                    // this does not do anything, if the user had own custom settings
                }
            } catch (e) {
                // Handle error if JSON parsing fails
            }
        }

        OCA.Analytics.currentReportData.options.dataoptions = dataOptions;
        OCA.Analytics.currentReportData.options.chartoptions = chartOptionsObj;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Report.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    /**
     * Close the currently opened dialog and clean up global listeners.
     */
    close: function () {
        document.getElementById('analytics_dialog_container').remove();
        document.getElementById('analytics_dialog_overlay').remove();
        // remove the global event listener which was added by the drop down lists
        document.removeEventListener('click', OCA.Analytics.Report.handleDropDownListClicked);
    },

    // function for shorter coding of the dialog creation
    /**
     * Helper used by option dialogs to preselect dropdown values based
     * on existing configuration objects.
     */
    checkOption: function (array, index, field, check, defaultChartType) {
        if (Array.isArray(array) && array.length && array[index]) {
            if (field in array[index]) {
                return array[index][field] === check ? 'selected' : '';
            } else if (check === defaultChartType) {
                return 'selected';
            } else {
                return '';
            }
        } else if (check === defaultChartType) {
            return 'selected';
        } else {
            return '';
        }
    },

    // function to define the color for a data series
    /**
     * Return the configured color for a dataset or a fallback from
     * the default palette when none is set.
     */
    checkColor: function (array, index) {
        let colors = OCA.Analytics.Visualization.defaultColorPalette;
        let j = index % colors.length
        let field = 'backgroundColor';
        if (Array.isArray(array) && array.length && array[index]) {
            if (field in array[index]) {
                return array[index][field];
            } else {
                return colors[j];
            }
        } else {
            return colors[j];
        }
    },

    // function to check non standard legend selections during save
    /**
     * Ensure custom legend visibility settings are stored and
     * obsolete entries removed before persisting chart options.
     */
    processChartLegendSelections: function (dataOptions) {
        if (OCA.Analytics.chartObject !== null) {
            let legendItems = OCA.Analytics.chartObject.legend.legendItems;
            dataOptions.length = legendItems.length;                        // cleanup any obsolete settings from data sets not being there anymore
            for (let i = 0; i < legendItems.length; i++) {
                if (!dataOptions.hasOwnProperty(i)) dataOptions[i] = {};    // create dummy for every index as a later index might get a setting
                if (i < 4 && legendItems[i]['hidden'] === true) {           // per default, the first 4 are  visible
                    dataOptions[i]['hidden'] = true;
                } else if (i > 3 && legendItems[i]['hidden'] === false) {   // per default, all others are hidden
                    dataOptions[i]['hidden'] = false;
                } else if (i in dataOptions) {
                    delete dataOptions[i]['hidden'];
                }
            }
        }
        // Convert to array without index numbers
        return Object.values(dataOptions).slice(0, dataOptions.length);
    },

    /**
     * Remove obsolete dataset option entries exceeding the number of
     * available categories in the current result set.
     */
    cleanupDataOptionsArray: function (dataOptions) {
        // Get the correct number of items from the report
        const itemCount = OCA.Analytics.Core.getDistinctValues(OCA.Analytics.currentReportData.data, 0).length;
        // Remove all array values beyond itemCount
        if (Array.isArray(dataOptions)) {
            dataOptions.splice(itemCount);
        }

        const allEmpty = dataOptions.length > 0 && dataOptions.every(obj => Object.keys(obj).length === 0 && obj.constructor === Object);

        return allEmpty ? [] : dataOptions;
    },

    // live update the background and font color of the input boxes
    /**
     * Update the color preview inputs while the user types and adjust
     * text color for readability.
     */
    updateColor: function (evt) {
        let field = evt.target;
        // Check if the selected color is valid (e.g., #RRGGBB format)
        if (field.value.length === 7 && field.value.charAt(0) === '#') {
            field.style.backgroundColor = field.value;

            // Convert HEX to RGB
            const hexToRgb = (hex) => {
                hex = hex.replace('#', '');
                let bigint = parseInt(hex, 16);
                return {
                    r: (bigint >> 16) & 255, // Extract red value
                    g: (bigint >> 8) & 255,  // Extract green value
                    b: bigint & 255,         // Extract blue value
                };
            };

            // Calculate brightness based on luminance formula
            const calculateBrightness = ({r, g, b}) => {
                return (0.299 * r + 0.587 * g + 0.114 * b);
            };

            const rgb = hexToRgb(field.value);
            const brightness = calculateBrightness(rgb);

            // Adjust font color based on brightness
            // If brightness is below 128, set text to white; otherwise, set it to black
            field.style.color = brightness < 128 ? '#FFFFFF' : '#000000';
        }
    }
};

OCA.Analytics.Filter.Drag = {
    /**
     * Allow dropping an element during drag and drop operations.
     */
    allowDrop: function (ev) {
        ev.preventDefault();
    },

    /**
     * Handle start of a drag event and update placeholders.
     */
    drag: function (ev) {
        ev.dataTransfer.setData("text", ev.target.id);
        // Manage placeholders after updating layout
        OCA.Analytics.Filter.Drag.managePlaceholders();
    },

    /**
     * Drop handler used for rearranging table layout items.
     */
    drop: function (ev) {
        ev.preventDefault();
        const data = ev.dataTransfer.getData("text");
        const draggedElement = document.getElementById(data);
        let dropTarget = ev.target;

        // Check if the drop target is within a section or is the section title
        while (!dropTarget.classList.contains('columnSection') && !dropTarget.classList.contains('draggable')) {
            dropTarget = dropTarget.parentNode; // Traverse up to find the section
        }

        // Remove placeholder if present
        let placeholder = ev.target.querySelector('.placeholder');
        if (placeholder) {
            ev.target.removeChild(placeholder);
        }

        if (dropTarget.classList.contains('draggable')) {
            dropTarget.parentNode.insertBefore(draggedElement, dropTarget);
        } else if (dropTarget.classList.contains('columnSection')) {
            dropTarget.appendChild(draggedElement);
        }

        // Update layout and manage placeholders
        OCA.Analytics.Filter.Drag.managePlaceholders();
    },

    /**
     * Build the drag and drop table layout from current configuration
     * and attach all necessary event handlers.
     */
    initialize: function () {
        let layoutConfig = OCA.Analytics.currentReportData.options.tableoptions.layout;
        let headerItems = OCA.Analytics.currentReportData.header;
        let rowsSection = document.getElementById('rows');

        // Clear existing sections
        document.querySelectorAll('.section').forEach(function (section) {
            section.innerHTML = '<p>' + section.id.charAt(0).toUpperCase() + section.id.slice(1) + '</p>';
        });

        // Function to create a draggable div
        const createDraggableItem = (text, index) => {
            let div = document.createElement('div');
            div.id = 'column-' + index;
            div.className = 'draggable tableOptionsLayoutItem';
            div.draggable = true;
            div.ondragstart = OCA.Analytics.Filter.Drag.drag;

            // Create and append the icon div
            let iconDiv = document.createElement('div');
            iconDiv.className = 'icon-analytics-gripLines sidebarPointer';
            div.appendChild(iconDiv);

            // Create and append the text span
            let textSpan = document.createElement('span');
            textSpan.textContent = text;
            div.appendChild(textSpan);

            return div;
        };

        if (!layoutConfig || Object.keys(layoutConfig).length === 0) {
            // Fallback: Add all header items to the "rows" section
            headerItems.forEach((text, index) => {
                let draggableItem = createDraggableItem(text, index);
                rowsSection.appendChild(draggableItem);
            });
        } else {
            // Append items to their respective sections based on layoutConfig
            Object.keys(layoutConfig).forEach(section => {
                let sectionElement = document.getElementById(section);
                layoutConfig[section].forEach(index => {
                    let draggableItem = createDraggableItem(headerItems[index], index);
                    sectionElement.appendChild(draggableItem);
                });
            });
        }

        // Manage placeholders initially
        OCA.Analytics.Filter.Drag.managePlaceholders();
    },

    /**
     * Insert or remove drag-and-drop placeholders depending on section
     * content to guide the user during layout editing.
     */
    managePlaceholders: function () {
        // Function to create a placeholder div
        const createPlaceholder = () => {
            let placeholderDiv = document.createElement('div');
            placeholderDiv.className = 'dragAndDropPlaceholder';
            placeholderDiv.textContent = t('analytics', 'Drag fields here');
            return placeholderDiv;
        };

        document.querySelectorAll('.columnSection').forEach(section => {
            let placeholder = section.querySelector('.dragAndDropPlaceholder');
            if (section.getElementsByClassName('draggable').length === 0) {
                if (!placeholder) {
                    section.appendChild(createPlaceholder());
                }
            } else {
                if (placeholder) {
                    section.removeChild(placeholder);
                }
            }
        });
    },
};

OCA.Analytics.Filter.Backend = {
    /**
     * Create a copy of the current report on the server and load it.
     */
    newReport: function () {
        const reportId = parseInt(OCA.Analytics.currentReportData.options.id);

        if (typeof (OCA.Analytics.currentReportData.options.filteroptions) === 'undefined') {
            OCA.Analytics.currentReportData.options.filteroptions = {};
        }

        OCA.Analytics.unsavedChanges = false;

        let requestUrl = OC.generateUrl('apps/analytics/report/copy');
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');

        fetch(requestUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                reportId: reportId,
                chartoptions: JSON.stringify(OCA.Analytics.currentReportData.options.chartoptions),
                dataoptions: JSON.stringify(OCA.Analytics.currentReportData.options.dataoptions),
                filteroptions: JSON.stringify(OCA.Analytics.currentReportData.options.filteroptions),
                tableoptions: JSON.stringify(OCA.Analytics.currentReportData.options.tableoptions),
            })
        })
            .then(response => response.json())
            .then(id => {
                return fetch(OC.generateUrl('apps/analytics/report/') + id, {
                    method: 'GET',
                    headers: OCA.Analytics.headers(),
                });
            })
            .then(response => response.json())
            .then(data => {
                data.item_type = 'report';
                OCA.Analytics.isNewObject = false;
                OCA.Analytics.Navigation.addNavigationItem(data);
                const anchor = document.querySelector('#navigationDatasets a[data-id="' + data.id + '"][data-item_type="report"]');
                anchor?.click();
            })
            .catch(err => console.error(err));
    },

    /**
     * Persist all current filter and option settings for the report.
     */
    saveReport: function () {
        const reportId = parseInt(OCA.Analytics.currentReportData.options.id);
        OCA.Analytics.unsavedChanges = false;

        if (typeof (OCA.Analytics.currentReportData.options.filteroptions) === 'undefined') {
            OCA.Analytics.currentReportData.options.filteroptions = {};
        } else {
            OCA.Analytics.currentReportData.options.filteroptions = OCA.Analytics.currentReportData.options.filteroptions;
        }

        let tableOptions = {};

        //get the table states
        if (OCA.Analytics.tableObject.length !== 0) {
            let key = Object.keys(OCA.Analytics.tableObject)[0];
            let fullState = OCA.Analytics.tableObject[key].state();
            if (fullState.order.length !== 0) {
                tableOptions.order = fullState.order;
            }
            if (fullState.length !== 10) {
                tableOptions.length = fullState.length;
            }
            if (!fullState.colReorder.every((value, index) => value === index)) {
                // store the column order just if the index is not counting upwards, because this is the default
                tableOptions.colReorder = {
                    order: fullState.colReorder
                };
            }
        }

        // get the other states which are not related to the tableState itself
        if ((OCA.Analytics.currentReportData.options.tableoptions)?.formatLocales === false) {
            tableOptions.formatLocales = false;
        }

        if ((OCA.Analytics.currentReportData.options.tableoptions)?.footer === true) {
            tableOptions.footer = true;
        }

        if ((OCA.Analytics.currentReportData.options.tableoptions)?.compactDisplay === true) {
            tableOptions.compactDisplay = true;
        }

        if ((OCA.Analytics.currentReportData.options.tableoptions)?.layout) {
            tableOptions.layout = OCA.Analytics.currentReportData.options.tableoptions.layout;
        }

        if ((OCA.Analytics.currentReportData.options.tableoptions)?.calculatedColumns) {
            tableOptions.calculatedColumns = OCA.Analytics.currentReportData.options.tableoptions.calculatedColumns;
        }

        OCA.Analytics.currentReportData.options.tableoptions = tableOptions;

        let dataOptions = OCA.Analytics.currentReportData.options.dataoptions;
        dataOptions === '' || dataOptions === null ? dataOptions = [] : dataOptions = OCA.Analytics.currentReportData.options.dataoptions;

        // function to check non standard legend selections during save
        dataOptions = OCA.Analytics.Filter.processChartLegendSelections(dataOptions);
        // cleanup the array to keep it as small as necessary
        dataOptions = OCA.Analytics.Filter.cleanupDataOptionsArray(dataOptions);

        let requestUrl = OC.generateUrl('apps/analytics/report/') + reportId + '/options';
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');

        fetch(requestUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                chartoptions: JSON.stringify(OCA.Analytics.currentReportData.options.chartoptions),
                dataoptions: JSON.stringify(dataOptions),
                filteroptions: JSON.stringify(OCA.Analytics.currentReportData.options.filteroptions),
                tableoptions: JSON.stringify(OCA.Analytics.currentReportData.options.tableoptions),
            })
        })
            .then(response => response.json())
            .then(data => {
                delete OCA.Analytics.currentReportData.options;
                OCA.Analytics.Report.Backend.getData();
            })
            .catch(err => console.error(err));
    },

    /**
     * Save and start the automatic refresh timer with the given interval.
     */
    saveRefresh: function (evt) {
        OCA.Analytics.Report.hideReportMenu();
        let refresh = evt.target.id;
        refresh = parseInt(refresh.substring(7));
        const datasetId = parseInt(OCA.Analytics.currentReportData.options.id);

        let requestUrl = OC.generateUrl('apps/analytics/report/') + datasetId + '/refresh';
        let headers = new Headers();
        headers.append('requesttoken', OC.requestToken);
        headers.append('OCS-APIREQUEST', 'true');
        headers.append('Content-Type', 'application/json');

        fetch(requestUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                refresh: refresh,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Notification.notification('success', t('analytics', 'Saved'));
                OCA.Analytics.Report.Backend.startRefreshTimer(refresh);
            })
            .catch(err => console.error(err));
    },
}; 
