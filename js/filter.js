/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
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

    openDrilldownDialog: function () {
        OCA.Analytics.UI.hideReportMenu();
        let drilldownRows = '';
        let availableDimensions = OCA.Analytics.currentReportData.dimensions;
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;

        for (let i = 0; i < Object.keys(availableDimensions).length; i++) {
            let checkboxStatus = 'checked';
            if (filterOptions['drilldown'] !== undefined && filterOptions['drilldown'][Object.keys(availableDimensions)[i]] !== undefined) {
                checkboxStatus = '';
            }
            drilldownRows = drilldownRows + '<div style="display: table-row;">'
                + '<div style="display: table-cell;">'
                + Object.values(availableDimensions)[i]
                + '</div>'
                + '<div style="display: table-cell;">'
                + '<input type="checkbox" id="drilldownColumn' + [i] + '" class="checkbox" name="drilldownColumn" value="' + Object.keys(availableDimensions)[i] + '" ' + checkboxStatus + '>'
                + '<label for="drilldownColumn' + [i] + '"> </label>'
                + '</div>'
                + '</div>';
        }

        document.body.insertAdjacentHTML('beforeend',
            '<div id="analytics_dialog_overlay" class="oc-dialog-dim"></div>'
            + '<div id="analytics_dialog_container" class="oc-dialog" style="position: fixed;">'
            + '<div id="analytics_dialog">'
            + '<a class="analyticsDialogClose" id="btnClose"></a>'
            + '<h2 class="analyticsDialogHeader" style="display:flex;margin-right:30px;">'
            + t('analytics', 'Drilldown')
            + '</h2>'
            + '<div class="table" style="display: table;">'

            + '<div style="display: table-row;">'
            + '<div style="display: table-cell; width: 150px;">'
            + '</div>'
            + '<div style="display: table-cell; width: 50px;">'
            + '<img src="' + OC.imagePath('analytics', 'column') + '" style="height: 20px;" alt="column">'
            + '</div>'
            + '</div>'
            + drilldownRows
            + '</div>'
            + '<div class="analyticsDialogButtonrow" id="buttons">'
            + '<a class="button primary" id="drilldownDialogGo">' + t('analytics', 'OK') + '</a>'
            + '<a class="button" id="drilldownDialogCancel">' + t('analytics', 'Cancel') + '</a>'
            + '</div>'
        );

        document.getElementById("btnClose").addEventListener("click", OCA.Analytics.Filter.close);
        document.getElementById("drilldownDialogCancel").addEventListener("click", OCA.Analytics.Filter.close);
        document.getElementById("drilldownDialogGo").addEventListener("click", OCA.Analytics.Filter.processDrilldownDialog);
    },

    processDrilldownDialog: function () {
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
        OCA.Analytics.unsavedFilters = true;
        OCA.Analytics.Backend.getData();
        OCA.Analytics.Filter.close();
    },

    openFilterDialog: function () {
        OCA.Analytics.UI.hideReportMenu();
        document.body.insertAdjacentHTML('beforeend',
            '<div id="analytics_dialog_overlay" class="oc-dialog-dim"></div>'
            + '<div id="analytics_dialog_container" class="oc-dialog" style="position: fixed;">'
            + '<div id="analytics_dialog">'
            + '<a class="analyticsDialogClose" id="btnClose"></a>'
            + '<h2 class="analyticsDialogHeader" style="display:flex;margin-right:30px;">'
            + t('analytics', 'Filter')
            + '</h2>'
            + '<span hidden id="filterDialogHintText" class="userGuidance">'
            + t('analytics', 'Dynamic text variables can be used to select dates.<br>The selection is written between two % (e.g. %last2months%).<br>Information on available filters and alternative date formats is available in the {linkstart}Wiki{linkend}.')
                .replace('{linkstart}', '<a href="https://github.com/Rello/analytics/wiki/Filter,-chart-options-&-drilldown##text-variables" target="_blank">')
                .replace('{linkend}', '</a>')
            + '<br><br></span>'
            + '<div class="table" style="display: table;">'
            + '<div style="display: table-row;">'
            //+ '<div style="display: table-cell; width: 50px;"></div>'
            + '<div style="display: table-cell; width: 80px;"></div>'
            + '<div style="display: table-cell; width: 150px;">'
            + '<label for="filterDialogDimension">' + t('analytics', 'Filter by') + '</label>'
            + '</div>'
            + '<div style="display: table-cell; width: 150px;">'
            + '<label for="filterDialogOption">' + t('analytics', 'Operator') + '</label>'
            + '</div>'
            + '<div style="display: table-cell; width: 220px;">'
            + '<label for="filterDialogValue">' + t('analytics', 'Value') + '</label>'
            + '</div>'
            + '<div style="display: table-cell; width: 20px;"></div>'
            + '</div>'
            + '<div style="display: table-row;">'
            + '<div style="display: table-cell;">'
            + '<img src="' + OC.imagePath('analytics', 'filteradd') + '" alt="filter">'
            + '</div>'
            //+ '<div style="display: table-cell;">'
            //+ '<select id="filterDialogType" class="checkbox" disabled>'
            //+ '<option value="and">' + t('analytics', 'and') + '</option>'
            //+ '</select>'
            //+ '</div>'
            + '<div style="display: table-cell;">'
            + '<select id="filterDialogDimension" class="checkbox optionsInput">'
            + '</select>'
            + '</div>'
            + '<div style="display: table-cell;">'
            + '<select id="filterDialogOption" class="checkbox optionsInput">'
            + '</select>'
            + '</div>'
            + '<div style="display: table-cell;">'
            + '<input type="text" id="filterDialogValue" class="optionsInputValue" autocomplete="off" data-dropDownListIndex="0">'
            + '</div>'
            + '<div style="display: table-cell;">'
            + '<a id="filterDialogHint" title ="' + t('analytics', 'Variables') + '">'
            + '<div class="icon-info" style="opacity: 0.5;padding: 0 10px;"></div>'
            + '</a></div>'
            + '</div></div>'
            + '<br>'
            + '<div class="analyticsDialogButtonrow" id="buttons">'
            + '<a class="button primary" id="filterDialogGo">' + t('analytics', 'Add') + '</a>'
            + '<a class="button" id="filterDialogCancel">' + t('analytics', 'Cancel') + '</a>'
            + '</div>'
        );

        // fill Dimension dropdown
        let dimensionSelectOptions = '';
        let availableDimensions = OCA.Analytics.currentReportData.dimensions;
        for (let i = 0; i < Object.keys(availableDimensions).length; i++) {
            dimensionSelectOptions = dimensionSelectOptions + '<option value="' + Object.keys(availableDimensions)[i] + '">' + Object.values(availableDimensions)[i] + '</option>';
        }
        document.getElementById('filterDialogDimension').innerHTML = dimensionSelectOptions;
        document.getElementById('filterDialogDimension').addEventListener('change', function () {
            document.getElementById('filterDialogValue').dataset.dropdownlistindex = document.getElementById('filterDialogDimension').selectedIndex;
        });

        // fill Options dropdown
        let optionSelectOptions = '';
        for (let i = 0; i < Object.keys(OCA.Analytics.Filter.optionTextsArray).length; i++) {
            optionSelectOptions = optionSelectOptions + '<option value="' + Object.keys(OCA.Analytics.Filter.optionTextsArray)[i] + '">' + Object.values(OCA.Analytics.Filter.optionTextsArray)[i] + '</option>';
        }
        document.getElementById('filterDialogOption').innerHTML = optionSelectOptions;

        // preselect existing filter
        // TODO: currently only one filter preset
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        if (filterOptions !== null && filterOptions['filter'] !== undefined) {
            for (let filterDimension of Object.keys(filterOptions['filter'])) {
                let filterOption = filterOptions['filter'][filterDimension]['option'];
                let filterValue = filterOptions['filter'][filterDimension]['value']
                document.getElementById('filterDialogValue').value = filterValue;
                document.getElementById('filterDialogOption').value = filterOption;
                document.getElementById('filterDialogDimension').value = filterDimension;
                document.getElementById('filterDialogValue').dataset.dropdownlistindex = document.getElementById('filterDialogDimension').selectedIndex;
            }
        }

        document.getElementById('filterDialogHint').addEventListener('click', OCA.Analytics.Filter.handleVariableHint);
        document.getElementById("btnClose").addEventListener("click", OCA.Analytics.Filter.close);
        document.getElementById("filterDialogCancel").addEventListener("click", OCA.Analytics.Filter.close);
        document.getElementById("filterDialogGo").addEventListener("click", OCA.Analytics.Filter.processFilterDialog);
        document.getElementById('filterDialogValue').addEventListener('click', OCA.Analytics.UI.showDropDownList);

        document.getElementById('filterDialogValue').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                OCA.Analytics.Filter.processFilterDialog();
            }
        });
    },

    handleVariableHint: function () {
        OCA.Analytics.Visualization.showElement('filterDialogHintText');
    },

    processFilterDialog: function () {
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        let dimension = document.getElementById('filterDialogDimension').value;
        if (filterOptions['filter'] === undefined) {
            filterOptions['filter'] = {};
        }
        if (filterOptions['filter'][dimension] === undefined) {
            filterOptions['filter'][dimension] = {};
        }
        filterOptions['filter'][dimension]['option'] = document.getElementById('filterDialogOption').value;
        filterOptions['filter'][dimension]['value'] = document.getElementById('filterDialogValue').value.replace(', ', ',');

        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        OCA.Analytics.unsavedFilters = true;
        OCA.Analytics.Backend.getData();
        OCA.Analytics.Filter.close();
    },

    refreshFilterVisualisation: function () {
        document.getElementById('filterVisualisation').innerHTML = '';
        let filterDimensions = OCA.Analytics.currentReportData.dimensions;
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        if (filterOptions !== null && filterOptions['filter'] !== undefined) {
            for (let filterDimension of Object.keys(filterOptions['filter'])) {
                let optionText = OCA.Analytics.Filter.optionTextsArray[filterOptions['filter'][filterDimension]['option']];
                let span = document.createElement('span');
                let filterValue = filterOptions['filter'][filterDimension]['value'];

                if (filterValue.match(/%/g) && filterValue.match(/%/g).length === 2) {
                    // text variable used
                    optionText = '';
                    filterValue = filterValue.replace(/%/g, '');
                    filterValue = filterValue.replace(/\(.*?\)/g, '');
                }
                span.innerText = filterDimensions[filterDimension] + ' ' + optionText + ' ' + filterValue;
                span.classList.add('filterVisualizationItem');
                span.id = filterDimension;
                span.addEventListener('click', OCA.Analytics.Filter.removeFilter)
                document.getElementById('filterVisualisation').appendChild(span);
            }
        }
        if (OCA.Analytics.unsavedFilters === true) {
            document.getElementById('saveIcon').style.removeProperty('display');
        } else {
            document.getElementById('saveIcon').style.display = 'none';
        }

    },

    removeFilter: function (evt) {
        let filterDimension = evt.target.id;
        let filterOptions = OCA.Analytics.currentReportData.options.filteroptions;
        delete filterOptions['filter'][filterDimension];
        if (Object.keys(filterOptions['filter']).length === 0) {
            delete filterOptions['filter'];
        }
        OCA.Analytics.currentReportData.options.filteroptions = filterOptions;
        OCA.Analytics.unsavedFilters = true;
        OCA.Analytics.Backend.getData();
    },

    openTableOptionsDialog: function () {
        OCA.Analytics.UI.hideReportMenu();

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

        let tableOptions;
        try {
            tableOptions = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions);
        } catch (e) {
            tableOptions = {};
        }

        if (tableOptions && tableOptions.footer) {
            container.querySelector('input[name="totalOption"][value="true"]').checked = true;
        } else {
            container.querySelector('input[name="totalOption"][value="false"]').checked = true;
        }

        if (tableOptions && tableOptions.calculatedColumns) {
            container.getElementById('tableOptionsCalculatedColumns').value = tableOptions.calculatedColumns;
        }

        OCA.Analytics.Notification.htmlDialogUpdate(
            container,
            t('analytics', 'The table can be customized by positioning the rows and columns. <br>If a classic list view is required, all fields need to be placed in the rows section.')
        );

        OCA.Analytics.Filter.Drag.initialize();
    },

    processTableOptionsDialog: function () {
        let tableOptions;
        try {
            tableOptions = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions);
            if (tableOptions === null) {
                tableOptions = {};
            }
        } catch (e) {
            tableOptions = {};
        }

        const selectedRadio = document.querySelector('input[name="totalOption"]:checked');
        const tableOptionTotalRow = selectedRadio ? selectedRadio.value : null;

        if (tableOptionTotalRow === 'true') {
            tableOptions.footer = true;
        } else if (tableOptions) {
            delete tableOptions.footer;
        }

        let layout = {};
        document.querySelectorAll('.columnSection').forEach(function (section) {
            var sectionId = section.id;
            // Initialize the layout[sectionId] as an empty array if undefined
            if (!layout[sectionId]) {
                layout[sectionId] = [];
            }
            var childNodes = section.getElementsByClassName('draggable');
            for (var i = 0; i < childNodes.length; i++) {
                var columnId = parseInt(childNodes[i].id.replace('column-', ''));
                layout[sectionId].push(columnId);
            }
        });

        const isSequential = (arr) => arr.every((val, i, array) => i === 0 || (val === array[i - 1] + 1));
        if (layout.columns.length === 0 && layout.measures.length === 0 && layout.hidden.length === 0) {
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

        if (OCA.Analytics.currentReportData.options.tableoptions !== JSON.stringify(tableOptions)) {
            delete tableOptions.colReorder;
        }

        OCA.Analytics.currentReportData.options.tableoptions = JSON.stringify(tableOptions);
        OCA.Analytics.unsavedFilters = true;
        OCA.Analytics.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
    },

    processTableOptionsReset: function () {
        if (OCA.Analytics.currentReportData.options.tableoptions !== null) {
            OCA.Analytics.currentReportData.options.tableoptions = null;
            OCA.Analytics.unsavedFilters = true;
        }
    },

    openChartOptionsDialog: function () {
        OCA.Analytics.UI.hideReportMenu();
        let drilldownRows = '';
        let dataOptions;
        try {
            dataOptions = JSON.parse(OCA.Analytics.currentReportData.options.dataoptions);
        } catch (e) {
            dataOptions = '';
        }
        let distinctCategories = OCA.Analytics.Core.getDistinctValues(OCA.Analytics.currentReportData.data, 0);
        if (dataOptions === null) dataOptions = {};

        // check if defined dataoptions don´t match the number of dataseries anymore
        if (Object.keys(dataOptions).length !== Object.keys(distinctCategories).length) {
            //dataOptions = '';
        }

        // get the default chart type to preset the drop downs
        let defaultChartType = OCA.Analytics.chartTypeMapping[OCA.Analytics.currentReportData.options.chart];

        for (let i = 0; i < Object.keys(distinctCategories).length; i++) {
            let color = OCA.Analytics.Filter.checkColor(dataOptions, i);
            drilldownRows = drilldownRows + '<div style="display: table-row;">'
                + '<div style="display: table-cell;">'
                + Object.values(distinctCategories)[i]
                + '</div>'
                + '<div style="display: table-cell;">'
                + '<select id="optionsYAxis' + [i] + '" name="optionsYAxis" class="optionsInput">'
                + '<option value="primary" ' + OCA.Analytics.Filter.checkOption(dataOptions, i, 'yAxisID', 'primary', 'primary') + '>' + t('analytics', 'Primary') + '</option>'
                + '<option value="secondary" ' + OCA.Analytics.Filter.checkOption(dataOptions, i, 'yAxisID', 'secondary', 'primary') + '>' + t('analytics', 'Secondary') + '</option>'
                + '</select>'
                + '</div>'
                + '<div style="display: table-cell;">'
                + '<select id="optionsChartType' + [i] + '" name="optionsChartType" class="optionsInput">'
                + '<option value="line" ' + OCA.Analytics.Filter.checkOption(dataOptions, i, 'type', 'line', defaultChartType) + '>' + t('analytics', 'Line') + '</option>'
                + '<option value="doughnut" ' + OCA.Analytics.Filter.checkOption(dataOptions, i, 'type', 'doughnut', defaultChartType) + '>' + t('analytics', 'doughnut') + '</option>'
                + '<option value="bar" ' + OCA.Analytics.Filter.checkOption(dataOptions, i, 'type', 'bar', defaultChartType) + '>' + t('analytics', 'Bar') + '</option>'
                + '</select>'
                + '</div>'
                + '<div style="display: table-cell;">'
                + '<input id="optionsColor' + [i] + '" name="optionsColor" value=' + color + ' style="background-color:' + color + ';" class="optionsInput">'
                + '</div>'
                + '</div>';
        }

        document.body.insertAdjacentHTML('beforeend',
            '<div id="analytics_dialog_overlay" class="oc-dialog-dim"></div>'
            + '<div id="analytics_dialog_container" class="oc-dialog" style="position: fixed;">'
            + '<div id="analytics_dialog">'
            + '<a class="analyticsDialogClose" id="btnClose"></a>'
            + '<h2 class="analyticsDialogHeader" style="display:flex;margin-right:30px;">'
            + t('analytics', 'Chart options')
            + '</h2>'
            + '<div class="table" style="display: table;">'

            + '<div style="display: table-row;">'
            + '<div style="display: table-cell; width: 150px;">' + t('analytics', 'Data series')
            + '</div>'
            + '<div style="display: table-cell; width: 150px;">' + t('analytics', 'Vertical axis')
            + '</div>'
            + '<div style="display: table-cell; width: 150px;">' + t('analytics', 'Chart type')
            + '</div>'
            + '<div style="display: table-cell; width: 150px;">' + t('analytics', 'Color')
            + '</div>'
            + '</div>'
            + drilldownRows
            + '</div>'
            + '<div class="analyticsDialogButtonrow" id="buttons">'
            + '<a class="button primary" id="drilldownDialogGo">' + t('analytics', 'OK') + '</a>'
            + '<a class="button" id="drilldownDialogCancel">' + t('analytics', 'Cancel') + '</a>'
            + '</div>'
        );

        let optionsColor = document.getElementsByName('optionsColor');
        for (let i = 0; i < optionsColor.length; i++) {
            optionsColor[i].addEventListener('keyup', OCA.Analytics.Filter.updateColor);
        }

        document.getElementById("btnClose").addEventListener("click", OCA.Analytics.Filter.close);
        document.getElementById("drilldownDialogCancel").addEventListener("click", OCA.Analytics.Filter.close);
        document.getElementById("drilldownDialogGo").addEventListener("click", OCA.Analytics.Filter.processOptionsDialog);
    },

    processOptionsDialog: function () {
        let dataOptions = OCA.Analytics.currentReportData.options.dataoptions;
        dataOptions === '' || dataOptions === null ? dataOptions = [] : dataOptions;
        let chartOptions = OCA.Analytics.currentReportData.options.chartoptions;
        chartOptions === '' || chartOptions === null ? chartOptions = [] : chartOptions;
        let userDatasetOptions = [];
        let nonDefaultValues, seondaryAxisRequired = false;
        let optionObject;

        // get the defaults (e.g. line or bar) to derive if there is any relevant change by the user
        let defaultChartType = OCA.Analytics.chartTypeMapping[OCA.Analytics.currentReportData.options.chart];
        let defaultYAxis = 'primary';
        let defaultColors = ["#aec7e8", "#ffbb78", "#98df8a", "#ff9896", "#c5b0d5", "#c49c94", "#f7b6d2", "#c7c7c7", "#dbdb8d", "#9edae5"];

        // loop all selections from the option dialog and add them to an array
        let optionsYAxis = document.getElementsByName('optionsYAxis');
        let optionsChartType = document.getElementsByName('optionsChartType');
        let optionsColor = document.getElementsByName('optionsColor');

        for (let i = 0; i < optionsYAxis.length; i++) {
            let j = i - (Math.floor(i / defaultColors.length) * defaultColors.length)
            optionObject = {};
            if (optionsYAxis[i].value !== defaultYAxis) {
                optionObject['yAxisID'] = optionsYAxis[i].value;
                seondaryAxisRequired = true;
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

        // decide of the dataseries array is relevant to be saved or not.
        // if all settings are default, all options can be removed can be removed completely
        // to keep the array clean, it will overwrite any existing settings.
        if (nonDefaultValues === true) {
            dataOptions = JSON.stringify(userDatasetOptions);
        } else {
            dataOptions = '';
        }

        // if any data series is tied to the secondary yAxis or not
        // if yes, it needs to be enabled in the chart options (in addition to the dataseries options)
        let enableAxisV2 = '{"scales":{"yAxes":[{},{"display":true}]}}';
        let enableAxis = '{"scales":{"secondary":{"display":true}}}';
        if (seondaryAxisRequired === true) {
            try {
                // if there are existing settings, merge them
                chartOptions = JSON.stringify(cloner.deep.merge(JSON.parse(chartOptions), JSON.parse(enableAxis)));
            } catch (e) {
                chartOptions = enableAxis;
            }
        } else {
            if (chartOptions === enableAxis || chartOptions === enableAxisV2) {
                // if the secondary axis is not required anymore but was enabled before
                // the options are cleared all together
                // this does only apply when ONLY the axis was enabled before
                // this does not do anything, if the user had own custom settings
                chartOptions = '';
            }
        }

        OCA.Analytics.currentReportData.options.dataoptions = dataOptions;
        OCA.Analytics.currentReportData.options.chartoptions = chartOptions;
        OCA.Analytics.unsavedFilters = true;
        OCA.Analytics.Backend.getData();
        OCA.Analytics.Filter.close();
    },

    close: function () {
        document.getElementById('analytics_dialog_container').remove();
        document.getElementById('analytics_dialog_overlay').remove();
        // remove the global event listener which was added by the drop down lists
        document.removeEventListener('click', OCA.Analytics.UI.handleDropDownListClicked);
    },

    // function for shorter coding of the dialog creation
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
    checkColor: function (array, index) {
        let colors = ["#aec7e8", "#ffbb78", "#98df8a", "#ff9896", "#c5b0d5", "#c49c94", "#f7b6d2", "#c7c7c7", "#dbdb8d", "#9edae5"];
        let j = index - (Math.floor(index / colors.length) * colors.length)
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
        return dataOptions;
    },

    cleanupDataOptionsArray: function (dataOptions) {
        let lastNonDefaultValue = false;
        let items = dataOptions.length;
        for (let i = 0; i < items; i++) {
            if (Object.entries(dataOptions[i]).length !== 0) lastNonDefaultValue = i;
        }

        if (lastNonDefaultValue !== false) {    // remove all tailing {}
            dataOptions.splice(lastNonDefaultValue + 1, items - lastNonDefaultValue);
        } else {                                // or reset the whole array if no setting at all exists
            dataOptions = [];
        }
        return dataOptions;
    },

    // live update the background color of the input boxes
    updateColor: function (evt) {
        let field = evt.target;
        if (field.value.length === 7 && field.value.charAt(0) === '#') {
            field.style.backgroundColor = field.value;
        }
    }
};

OCA.Analytics.Filter.Drag = {
    allowDrop: function (ev) {
        ev.preventDefault();
    },

    drag: function (ev) {
        ev.dataTransfer.setData("text", ev.target.id);
        // Manage placeholders after updating layout
        OCA.Analytics.Filter.Drag.managePlaceholders();
    },

    drop: function (ev) {
        ev.preventDefault();
        var data = ev.dataTransfer.getData("text");
        var draggedElement = document.getElementById(data);
        var dropTarget = ev.target;

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

    initialize: function () {
        let layoutConfig;
        try {
            layoutConfig = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions).layout;
        } catch (e) {
            layoutConfig = null;
        }

        let headerItems = OCA.Analytics.currentReportData.header;
        let rowsSection = document.getElementById('rows');

        // Clear existing sections
        document.querySelectorAll('.section').forEach(function(section) {
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

        if (!layoutConfig) {
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

    managePlaceholders: function() {
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
    newReport: function () {
        const reportId = parseInt(OCA.Analytics.currentReportData.options.id);

        if (typeof (OCA.Analytics.currentReportData.options.filteroptions) === 'undefined') {
            OCA.Analytics.currentReportData.options.filteroptions = {};
        } else {
            OCA.Analytics.currentReportData.options.filteroptions = JSON.stringify(OCA.Analytics.currentReportData.options.filteroptions);
        }

        OCA.Analytics.unsavedFilters = false;

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
                chartoptions: OCA.Analytics.currentReportData.options.chartoptions,
                dataoptions: OCA.Analytics.currentReportData.options.dataoptions,
                filteroptions: OCA.Analytics.currentReportData.options.filteroptions,
                tableoptions: OCA.Analytics.currentReportData.options.tableoptions,
            })
        })
            .then(response => response.json())
            .then(data => {
                OCA.Analytics.Navigation.init(data);
            });
    },

    saveReport: function () {
        const reportId = parseInt(OCA.Analytics.currentReportData.options.id);
        OCA.Analytics.unsavedFilters = false;

        if (typeof (OCA.Analytics.currentReportData.options.filteroptions) === 'undefined') {
            OCA.Analytics.currentReportData.options.filteroptions = {};
        } else {
            OCA.Analytics.currentReportData.options.filteroptions = JSON.stringify(OCA.Analytics.currentReportData.options.filteroptions);
        }

        let tableOptions = {};

        //get the table states
        if (OCA.Analytics.tableObject) {
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
        if (JSON.parse(OCA.Analytics.currentReportData.options.tableoptions)?.footer === true) {
            tableOptions.footer = true;
        }

        if (JSON.parse(OCA.Analytics.currentReportData.options.tableoptions)?.layout) {
            tableOptions.layout = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions).layout;
        }

        if (JSON.parse(OCA.Analytics.currentReportData.options.tableoptions)?.calculatedColumns) {
            tableOptions.calculatedColumns = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions).calculatedColumns;
        }

        OCA.Analytics.currentReportData.options.tableoptions = JSON.stringify(tableOptions);

        let dataOptions = OCA.Analytics.currentReportData.options.dataoptions;
        dataOptions === '' || dataOptions === null ? dataOptions = [] : dataOptions = JSON.parse(OCA.Analytics.currentReportData.options.dataoptions);

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
                chartoptions: OCA.Analytics.currentReportData.options.chartoptions,
                dataoptions: JSON.stringify(dataOptions),
                filteroptions: OCA.Analytics.currentReportData.options.filteroptions,
                tableoptions: OCA.Analytics.currentReportData.options.tableoptions,
            })
        })
            .then(response => response.json())
            .then(data => {
                delete OCA.Analytics.currentReportData.options;
                OCA.Analytics.Backend.getData();
            });
    },

    saveRefresh: function (evt) {
        OCA.Analytics.UI.hideReportMenu();
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
                OCA.Analytics.Backend.startRefreshTimer(refresh);
            });
    },
};