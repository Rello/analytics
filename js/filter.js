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
        OCA.Analytics.UI.showElement('filterDialogHintText');
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
        let tableOptions;
        try {
            tableOptions = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions);
        } catch (e) {
            tableOptions = {};
        }

        OCA.Analytics.Notification.htmlDialogInitiate(
            t('analytics', 'Table options'),
            OCA.Analytics.Filter.processTableOptionsDialog
        );

        // Total row setting
        let tableRow = document.createElement('div');
        tableRow.style = 'display: table-row';
        let tableCell1 = document.createElement('div');
        tableCell1.style = 'display: table-cell';
        tableCell1.innerText =  t('analytics', 'Totals row');
        let selectField = document.createElement('select');
        selectField.id = 'tableOptionTotalRow';
        const option1 = new Option(t('analytics', 'yes'), 'true');
        const option2 = new Option(t('analytics', 'no'), 'false');
        (tableOptions.footer ? option1 : option2).selected = true;
        selectField.add(option1);
        selectField.add(option2);
        tableRow.appendChild(tableCell1);
        tableRow.appendChild(selectField);

        // Rows input field
        let tableRow2 = document.createElement('div');
        tableRow2.style = 'display: table-row';
        let tableCell2 = document.createElement('div');
        tableCell2.style = 'display: table-cell';
        tableCell2.innerText =  t('analytics', 'What to show in the rows:');
        let inputField = document.createElement('input');
        inputField.id = 'tableOptionRows';
        inputField.value = (tableOptions.layout && tableOptions.layout.rows) ? tableOptions.layout.rows : '';
        tableRow2.appendChild(tableCell2);
        tableRow2.appendChild(inputField);

        // Columns input field
        let tableRow3 = document.createElement('div');
        tableRow3.style = 'display: table-row';
        let tableCell3 = document.createElement('div');
        tableCell3.style = 'display: table-cell';
        tableCell3.innerText =  t('analytics', 'What to show in the columns:');
        let inputField2 = document.createElement('input');
        inputField2.id = 'tableOptionColumns';
        inputField2.value = (tableOptions.layout && tableOptions.layout.columns) ? tableOptions.layout.columns : '';
        tableRow3.appendChild(tableCell3);
        tableRow3.appendChild(inputField2);

        // Data input field
        let tableRow4 = document.createElement('div');
        tableRow4.style = 'display: table-row';
        let tableCell4 = document.createElement('div');
        tableCell4.style = 'display: table-cell';
        tableCell4.innerText =  t('analytics', 'What to show in the columns:');
        let inputField3 = document.createElement('input');
        inputField3.id = 'tableOptionData';
        inputField3.value = (tableOptions.layout && tableOptions.layout.data) ? tableOptions.layout.data : '';
        tableRow4.appendChild(tableCell4);
        tableRow4.appendChild(inputField3);

        let layoutRaw = '<div class="container">\n' +
            '    <div id="rows" class="columnSection">\n' +
            '        <p>Rows</p>\n' +
            '    </div>\n' +
            '    <div id="columns" class="columnSection">\n' +
            '        <p>Columns</p>\n' +
            '    </div>\n' +
            '    <div id="measures" class="columnSection">\n' +
            '        <p>Measures</p>\n' +
            '    </div>\n' +
            '    <div id="notVisualized" class="columnSection">\n' +
            '        <p>Not Visualized</p>\n' +
            '    </div>\n' +
            '</div>'

        let parser = new DOMParser();
        let layout = parser.parseFromString(layoutRaw, 'text/html');
        let container = layout.querySelector('.container');


        // add the final content to the modal
        let content = document.createDocumentFragment();
        content.appendChild(tableRow);
        //content.appendChild(tableRow2);
        //content.appendChild(tableRow3);
        //content.appendChild(tableRow4);
        content.appendChild(container.cloneNode(true)); // Clone the container with event listeners

        // Attach event listeners programmatically
        content.querySelectorAll('.columnSection').forEach(section => {
            section.addEventListener('drop', OCA.Analytics.Filter.Drag.drop);
            section.addEventListener('dragover', OCA.Analytics.Filter.Drag.allowDrop);
        });

        OCA.Analytics.Notification.htmlDialogUpdate(
            content,
            t('analytics', 'Table options guidance')
        );

        OCA.Analytics.Filter.Drag.initialize();
    },

    processTableOptionsDialog: function () {
        let tableOptions;
        try {
            tableOptions = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions);
        } catch (e) {
            tableOptions = [];
        }
        let tableOptionTotalRow = document.getElementById('tableOptionTotalRow').value;
/*
        let tableOptionRows = document.getElementById('tableOptionRows').value;
        let tableOptionColumns = document.getElementById('tableOptionColumns').value;
        let tableOptionData = document.getElementById('tableOptionData').value;
*/

        if (tableOptionTotalRow === 'true') {
            tableOptions.footer = true;
        } else {
            delete tableOptions.footer;
        }

/*
        let rows = tableOptionRows ? tableOptionRows.split(',').map(Number) : [];
        let columns = tableOptionColumns ? tableOptionColumns.split(',').map(Number) : [];
        let data = tableOptionData ? tableOptionData.split(',').map(Number) : [];
        let layout = {
            rows: rows,
            columns: columns,
            data: data
        };
*/
        let layout = {};

        document.querySelectorAll('.columnSection').forEach(function(section) {
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

        tableOptions.layout = layout;

        OCA.Analytics.currentReportData.options.tableoptions = JSON.stringify(tableOptions);
        OCA.Analytics.unsavedFilters = true;
        OCA.Analytics.Backend.getData();
        OCA.Analytics.Notification.dialogClose();
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
    allowDrop: function(ev) {
        ev.preventDefault();
    },
    drag: function(ev) {
        ev.dataTransfer.setData("text", ev.target.id);
    },
    drop: function(ev) {
        ev.preventDefault();
        var data = ev.dataTransfer.getData("text");
        var draggedElement = document.getElementById(data);
        var dropTarget = ev.target;

        // Check if the drop target is within a section or is the section title
        while (!dropTarget.classList.contains('columnSection') && !dropTarget.classList.contains('draggable')) {
            dropTarget = dropTarget.parentNode; // Traverse up to find the section
        }

        if (dropTarget.classList.contains('draggable')) {
            dropTarget.parentNode.insertBefore(draggedElement, dropTarget);
        } else if (dropTarget.classList.contains('columnSection')) {
            dropTarget.appendChild(draggedElement);
        }
    },


    initialize: function() {
        let layoutConfig = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions).layout;
        let headerItems = OCA.Analytics.currentReportData.header;

       /* // Clear existing sections
        document.querySelectorAll('.columnSection').forEach(function(section) {
            section.innerHTML = '<p>' + section.id.charAt(0).toUpperCase() + section.id.slice(1) + '</p>';
        });*/

        // Function to create a draggable div
    const createDraggableItem = (text, index) => {
        let div = document.createElement('div');
        div.id = 'column-' + index;
        div.className = 'draggable';
        div.draggable = true;
        div.ondragstart = OCA.Analytics.Filter.Drag.drag;
        div.style.display = 'flex';
        div.style.alignItems = 'center';
        div.style.margin = '5px';
        div.style.backgroundColor = 'var(--color-background-hover)';

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

        // Append items to their respective sections
        Object.keys(layoutConfig).forEach(section => {
            let sectionElement = document.getElementById(section);
            layoutConfig[section].forEach(index => {
                let draggableItem = createDraggableItem(headerItems[index], index);
                sectionElement.appendChild(draggableItem);
            });
        });
    }
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
        if (OCA.Analytics.tableObject) {
            let fullState = OCA.Analytics.tableObject.state();
            let extractedState = {
                order: fullState.order,
                length: fullState.length
            };
            if (fullState.order.length !== 0) {
                tableOptions.order = fullState.order;
            }
            if (fullState.length !== 10) {
                tableOptions.length = fullState.length;
            }
        }

        if (JSON.parse(OCA.Analytics.currentReportData.options.tableoptions).footer === true) {
            tableOptions.footer = true;
        }

        if (JSON.parse(OCA.Analytics.currentReportData.options.tableoptions).layout) {
            tableOptions.layout = JSON.parse(OCA.Analytics.currentReportData.options.tableoptions).layout;
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