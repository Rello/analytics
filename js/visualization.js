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

// Workaround because NC still delivers moment but triggers a deprecated warning every time it is used
var myMoment = moment;

/**
 * @namespace OCA.Analytics.Visualization
 */
OCA.Analytics.Visualization = {
    defaultColorPalette: ["#1A366C", "#EA6A47", "#a3acb9", "#6AB187", "#39a7db", "#c85200", "#57606c", "#a3cce9", "#ffbc79", "#c8d0d9"],

    // *************
    // *** table ***
    // *************

    buildDataTable: function (domTarget, jsondata, ordering = true, uniqueId) {

        if (!uniqueId) {
            uniqueId = jsondata.options.id;
        } else {
            uniqueId = parseInt(uniqueId.replace(/[^0-9]+/g, ''), 10);
        }

        if (OCA.Analytics.tableObject?.uniqueId) {
            OCA.Analytics.tableObject[uniqueId].destroy();
            domTarget.innerHTML = '';
            OCA.Analytics.tableObject[uniqueId] = [];
            //test
        }

        this.showElement('tableContainer');

        // get current table state
        let tableOptions = jsondata.options.tableoptions;
        let defaultOrder = [];
        let defaultLength = 10;
        let defaultColReorder = true;
        let data, columns;
        let language = {
            // TRANSLATORS Noun
            search: t('analytics', 'Search'),
            lengthMenu: t('analytics', 'Show _MENU_ entries'),
            info: t('analytics', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            infoEmpty: t('analytics', 'Showing 0 to 0 of 0 entries'),
            paginate: {
                first: '<<',
                previous: '<',
                next: '>',
                last: '>>'
            },
        };

        ({data, columns} = this.convertDataToDataTableFormat(jsondata.data, tableOptions, jsondata.header));
        ({data, columns} = this.dataTableCalculatedColumns(data, columns, tableOptions));

        // Calculate column totals
        let columnTotals = new Array(data[0].length).fill(0);
        data.forEach(row => {
            row.forEach((value, index) => {
                let numericValue = value === null ? 0 : parseFloat(value);
                if (!isNaN(numericValue)) {
                    columnTotals[index] += numericValue;
                }
            });
        });

        // check table length => show/hide navigation
        let isDataLengthGreaterThanDefault = data.length > ((tableOptions && tableOptions.length) || defaultLength);
        // never show table navigation in Panorama
        if (OCA.Analytics.isPanorama) { isDataLengthGreaterThanDefault = false; }

        domTarget.createTFoot().insertRow(0);
        OCA.Analytics.tableObject[uniqueId] = new DataTable(domTarget, {
            //dom: 'lrtip',
            ordering: ordering,
            layout: {
                topStart: isDataLengthGreaterThanDefault ? 'pageLength' : null,
                topEnd: isDataLengthGreaterThanDefault ? 'search' : null,
                bottomStart: isDataLengthGreaterThanDefault ? 'info' : null,
                bottomEnd: isDataLengthGreaterThanDefault ? 'paging' : null,
            },
            colReorder: tableOptions.colReorder || defaultColReorder,
            order: tableOptions.order || defaultOrder,
            pageLength: tableOptions.length || defaultLength,
            pagingType: 'simple_numbers',
            //scrollX: true,
            autoWidth: false,
            data: data,
            columns: columns,
            language: language,
            rowCallback: function (row, data, index) {
                OCA.Analytics.Visualization.dataTableRowCallback(row, data, index, jsondata.thresholds, tableOptions);
            },
            footerCallback: function () {
                OCA.Analytics.Visualization.dataTablefooterCallback(this.api(), tableOptions);
            }
        });

        if (tableOptions.compactDisplay) {
            const thead = domTarget.querySelector('thead');
            if (thead) {
                thead.classList.add('hidden'); // Add 'hidden' class to <thead>
            }
        }

        if (!OCA.Analytics.isPanorama) {
            // Listener for when the pagination length is changed, table sorted, columns re-ordered
            OCA.Analytics.tableObject[uniqueId].on('length.dt', this.handleDataTableChanged);
            OCA.Analytics.tableObject[uniqueId].on('order.dt', this.handleDataTableChanged);
            OCA.Analytics.tableObject[uniqueId].on('column-reorder', this.handleDataTableChanged);
        }
    },

    convertDataToDataTableFormat: function (originalData, tableOptions, header) {
        let layoutConfig = tableOptions.layout !== undefined ? tableOptions.layout : false;
        let uniqueHeaders = new Set();
        let transformedData = {};
        let columns = [];
        let data = '';

        if (!layoutConfig || Object.keys(layoutConfig).length === 0) {
            // No special layout is defined: display all data in rows

            // create the columns. default alignment is left
            columns = header.map((header, index) => ({title: header, className: ''}));
            data = originalData.map(row =>
                row.map((value, index) => {
                    if (!isNaN(parseFloat(value)) && index !== 0 && tableOptions.formatLocales === undefined) {
                        // Any number gets aligned to the right and formated with locales
                        if (parseInt(value) > 1950 && parseInt(value) < 2050 && index < 2) {
                            // ToDo
                            // do not format 4 digit year numbers in the first 2 columns. dirty hack until proper column formating is there
                        } else {
                            columns[index].className = 'dt-right';
                            return parseFloat(value).toLocaleString();
                        }
                    } else if (index === row.length - 1 && !isNaN(parseFloat(value))) {
                        columns[index].className = 'dt-right';
                        return parseFloat(value).toLocaleString();
                    }
                    return value;
                })
            );
        } else if (layoutConfig.rows && !layoutConfig.columns.length && !layoutConfig.measures.length) {
            // If only the row array is filled, it means that all data is in the rows, but just the sequence was changed

            // Use titles from the headers array based on the reordered sequence (indices)
            columns = layoutConfig.rows.map((index, i) => ({
                title: header[index],
                className: i > 0 ? 'dt-right' : ''
            }));

            const rowsLength = layoutConfig.rows.length; // Cache length to avoid repeated property access

            // Reorder the data according to the new column sequence
            data = originalData.map(row =>
                layoutConfig.rows.map((index, i) =>
                    i === rowsLength - 1 ? parseFloat(row[index]).toLocaleString() : row[index]
                )
            );
        } else {
            // Case for pivot like rearrangement of the table

            // 1. Extract unique headers and initialize transformedData
            originalData.forEach(row => {
                const columnHeader = row[layoutConfig.columns[0]];
                uniqueHeaders.add(columnHeader);
                transformedData[row[layoutConfig.rows[0]]] = {};
            });

            // Sort the headers for consistent ordering
            uniqueHeaders = Array.from(uniqueHeaders).sort();

            // 2. Transform the data
            originalData.forEach(row => {
                const rowHeader = row[layoutConfig.rows[0]];
                const columnHeader = row[layoutConfig.columns[0]];
                const dataValue = row[layoutConfig.measures[0]];
                transformedData[rowHeader][columnHeader] = dataValue;
            });

            // 3. Generate the columns array which contains the column header and formatting (e.g. numbers)
            columns = [{title: header[layoutConfig.rows], className: ''}];
            uniqueHeaders.forEach(header => {
                columns.push({
                    title: header,
                    className: 'dt-right',
                    render: function (data, type, row, meta) {
                        if (data === null || isNaN(parseFloat(data))) {
                            return '';
                        } else {
                            return parseFloat(data).toLocaleString();
                        }
                    }
                });
            });

            // Convert transformed data to array format
            data = Object.entries(transformedData).map(([key, values]) => {
                return [key, ...uniqueHeaders.map(header => values[header] || null)];
            });
        }
        return {data, columns};
    },

    dataTableCalculatedColumns: function (data, columns, tableOptions) {
        let userCalculations = tableOptions.calculatedColumns ? JSON.parse('[' + tableOptions.calculatedColumns + ']') : [];
        userCalculations.forEach((calc, calcIndex) => {
            switch (calc.operation) {
                case "substract":
                    data = data.map(row => {
                        // Start with the value of the first column to subtract from
                        const initialValue = parseFloat(row[calc.columns[0]] || 0);
                        // Subtract the rest of the columns' values from the initial value
                        const difference = calc.columns.slice(1).reduce((acc, index) => acc - parseFloat(row[index] || 0), initialValue);
                        return [...row, difference];
                    });
                    columns.push({
                        title: calc.title,
                        className: 'dt-right',
                        calculationId: calcIndex, // Store the index of the calculation
                        render: function (data, type, row, meta) {
                            if (data === null || isNaN(parseFloat(data))) {
                                return '';
                            } else {
                                return parseFloat(data).toLocaleString();
                            }
                        }
                    });
                    break;
                case "add":
                    data = data.map(row => {
                        const sum = calc.columns.reduce((acc, index) => acc + parseFloat(row[index] || 0), 0);
                        return [...row, sum];
                    });
                    columns.push({
                        title: calc.title,
                        className: 'dt-right',
                        calculationId: calcIndex, // Store the index of the calculation
                        render: function (data, type, row, meta) {
                            if (data === null || isNaN(parseFloat(data))) {
                                return '';
                            } else {
                                return parseFloat(data).toLocaleString();
                            }
                        }
                    });
                    break;
                case "percentage":
                    data = data.map(row => {
                        const percentage = (parseFloat(row[calc.columns[0]] || 0) / parseFloat(row[calc.columns[1]] || 1)) * 100;
                        return [...row, percentage.toFixed(2)];
                    });
                    columns.push({
                        title: calc.title,
                        className: 'dt-right',
                        calculationId: calcIndex, // Store the index of the calculation
                        render: function (data, type, row, meta) {
                            if (data === null || isNaN(parseFloat(data))) {
                                return '';
                            } else {
                                return parseFloat(data).toLocaleString() + " %";
                            }
                        }
                    });
                    break;
                // Add more cases for different operations as needed
            }
        });
        return {data, columns};
    },

    dataTableRowCallback: function (row, data, index, thresholds, tableOptions) {
        const operators = {
            '=': function (a, b) {
                return a === b
            },
            '<': function (a, b) {
                return a < b
            },
            '>': function (a, b) {
                return a > b
            },
            '<=': function (a, b) {
                return a <= b
            },
            '>=': function (a, b) {
                return a >= b
            },
            '!=': function (a, b) {
                return a !== b
            },
        };

        thresholds = thresholds.filter(p => (p.dimension1 === data[0] || p.dimension1 === '*') && p.option !== 'new');

        let color;
        let severity;
        for (let threshold of thresholds) {
            // use the last column for comparison of the value
            const comparison = operators[threshold['option']](parseFloat(data[data.length - 1]), parseFloat(threshold['value']));
            severity = parseInt(threshold['severity']);
            if (comparison === true) {
                if (severity === 2) {
                    color = 'red';
                } else if (severity === 3) {
                    color = 'orange';
                } else if (severity === 4) {
                    color = 'green';
                }

                if (data.length > 3) {
                    // external data source
                    row.style.color = color;
                } else {
                    row.childNodes.item(data.length - 1).style.color = color;
                }
            }
        }

        // Compact view for table with less line space and bold first column
        if (tableOptions.compactDisplay) {

            const firstCell = row.querySelector('td:first-child');
            if (firstCell) {
                //firstCell.style.fontWeight = 'bold';
            }
            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                cell.style.padding = '0'; // Remove the padding
            });
        }
    },

    dataTablefooterCallback: function (api, tableOptions) {
        const footerRow = api.table().footer().querySelector('tr');
        const colReorder = tableOptions.colReorder ? tableOptions.colReorder.order : [...Array(api.columns().count()).keys()];

        if (tableOptions.footer !== true) {
            while (footerRow.firstChild) {
                footerRow.removeChild(footerRow.firstChild);
            }
            return;
        }

        colReorder.forEach((colIdx, displayIdx) => {
            const columnData = api.column(colIdx).data().toArray();
            let total;

            // Check if this column is a percentage calculation
            const calcColumn = tableOptions.calculatedColumns && JSON.parse('[' + tableOptions.calculatedColumns + ']').find(calc => calc.title === api.column(colIdx).header().textContent);

            if (calcColumn && calcColumn.operation === "percentage") {
                // Access the data for the numerator and denominator columns
                const numeratorData = api.column(calcColumn.columns[0]).data().toArray();
                const denominatorData = api.column(calcColumn.columns[1]).data().toArray();

                // Calculate the sums for numerator and denominator
                const numeratorSum = numeratorData.reduce((sum, value) => sum + parseFloat(value || 0), 0);
                const denominatorSum = denominatorData.reduce((sum, value) => sum + parseFloat(value || 1), 0);
                total = (numeratorSum / denominatorSum) * 100;
                total = total.toFixed(2);
            } else {
                // Regular sum for non-percentage columns
                total = columnData.reduce((sum, curValue) => sum + parseFloat(curValue || 0), 0);
            }

            let cell = footerRow.querySelector('td:nth-child(' + (displayIdx + 1) + ')');
            if (!cell) {
                cell = footerRow.appendChild(document.createElement('td'));
            }

            if (displayIdx === 0) {
                cell.innerHTML = 'Total';
            } else {
                cell.innerHTML = (total !== undefined && !isNaN(total)) ? parseFloat(total).toLocaleString() + (calcColumn && calcColumn.operation === "percentage" ? " %" : "") : '';
                cell.classList.add('dt-right');
            }
        });
    },

    resetTableState: function () {
        if (OCA.Analytics.tableObject !== null) {
            OCA.Analytics.tableObject
                .order([])
                .page.len(10)
                .draw();
            OCA.Analytics.unsavedFilters = true;
            document.getElementById('saveIcon')?.style.removeProperty('display');
            document.getElementById('tableContainer_length')?.style.removeProperty('display');
            document.getElementById('tableContainer_filter')?.style.removeProperty('display');
        }
        OCA.Analytics.UI.hideReportMenu();
    },

    handleDataTableChanged: function () {
        OCA.Analytics.unsavedFilters === true;
        document.getElementById('saveIcon').style.removeProperty('display');
    },

    buildKpiDisplay: function (domTarget, jsondata, ordering = true, uniqueId) {
        domTarget.innerHTML = '';
        let kpi = jsondata.data[0][0];

        let rawValue = parseFloat(jsondata.data[0][1]);
        let value;

        if (rawValue % 1 === 0) {
            // If the number is an integer, format without decimals
            value = rawValue.toLocaleString();
        } else {
            // Otherwise, format with decimals
            value = rawValue.toLocaleString(undefined, { minimumFractionDigits: 2 });
        }

        let thresholdColor = OCA.Analytics.Visualization.validateThreshold(kpi, value, jsondata.thresholds);

        // Create the KPI content dynamically
        const kpiContent = document.createElement('div');
        kpiContent.classList.add('kpiWidgetContent');

        // Create the KPI title
        const kpiTitle = document.createElement('div');
        kpiTitle.classList.add('kpiWidgetTitel');
        kpiTitle.textContent = kpi; // Set the title text

        // Create the KPI value
        const kpiValue = document.createElement('div');
        kpiValue.classList.add('kpiWidgetValue');
        kpiValue.setAttribute('style', thresholdColor);
        kpiValue.textContent = value; // Set the KPI value

        // Append the title and value to the content container
        kpiContent.appendChild(kpiTitle);
        kpiContent.appendChild(kpiValue);

        // Append the KPI content to the widget container
        domTarget.appendChild(kpiContent);
        domTarget.classList.add('kpiWidget');
    },

    // *************
    // *** chart ***
    // *************

    buildChart: function (ctx, jsondata, chartOptions) {
        const defaultLegendClickHandler = Chart.defaults.plugins.legend.onClick;
        const pieDoughnutLegendClickHandler = Chart.controllers.doughnut.overrides.plugins.legend.onClick;
        const newLegendClickHandler = function (e, legendItem, legend) {
            const index = legendItem.datasetIndex;
            const type = legend.chart.config.type;

            // Do the original logic
            if (type === 'pie' || type === 'doughnut') {
                pieDoughnutLegendClickHandler(e, legendItem, legend)
            } else {
                defaultLegendClickHandler(e, legendItem, legend);
            }
            document.getElementById('saveIcon')?.style.removeProperty('display');
        };

        this.showElement('tableSeparatorContainer');
        this.showElement('chartContainer');


        // store the full chart type for deriving the stacked attribute later
        // the general chart type is used for the chart from here on
        let chartTypeFull;
        jsondata.options.chart === '' ? chartTypeFull = 'column' : chartTypeFull = jsondata.options.chart;
        let chartType = chartTypeFull.replace(/St100$/, '').replace(/St$/, '');

        // get the default settings for a chart
        Chart.defaults.elements.line.borderWidth = 2;
        Chart.defaults.elements.line.tension = 0.1;
        Chart.defaults.elements.line.fill = false;
        Chart.defaults.elements.point.radius = 0.5;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.onClick = newLegendClickHandler;

        // convert the data array
        let [xAxisCategories, datasets] = this.convertDataToChartJsFormat(jsondata, chartType);

        // show legend button only when useful with >1 dataset
        datasets.length > 1 ? this.showElement('chartLegendContainer') : null;

        // do the color magic
        let colors = OCA.Analytics.Visualization.defaultColorPalette;
        for (let i = 0; i < datasets.length; ++i) {
            let j = i - (Math.floor(i / colors.length) * colors.length)

            // in only one dataset is being shown, create a fancy gradient fill
            if (datasets.length === 1 && chartType !== 'column' && chartType !== 'doughnut' && chartType !== 'funnel') {
                const hexToRgb = colors[j].replace(/^#?([a-f\d])([a-f\d])([a-f\d])$/i
                    , (m, r, g, b) => '#' + r + r + g + g + b + b)
                    .substring(1).match(/.{2}/g)
                    .map(x => parseInt(x, 16));

                datasets[0].backgroundColor = function (context) {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    let gradient = ctx.createLinearGradient(0, 0, 0, chart.height);
                    gradient.addColorStop(0, 'rgb(' + hexToRgb[0] + ',' + hexToRgb[1] + ',' + hexToRgb[2] + ')');
                    gradient.addColorStop(1, 'rgb(' + hexToRgb[0] + ',' + hexToRgb[1] + ',' + hexToRgb[2] + ',0)');
                    return gradient;
                }
                datasets[i].borderColor = colors[j];
                Chart.defaults.elements.line.fill = true;
            } else if (chartType === 'doughnut' || chartType === 'funnel') {
                // special array handling for doughnuts
                if (jsondata.options.dataoptions !== null && Object.keys(jsondata.options.dataoptions).length !== 0) {
                    const arr = jsondata.options.dataoptions;
                    let index = 0;
                    for (const obj of arr) {
                        if (obj.backgroundColor) {
                            colors[index] = obj.backgroundColor;
                        }
                        index++;
                    }
                }
                datasets[i].backgroundColor = datasets[i].borderColor = colors;
                Chart.defaults.elements.line.fill = false;
            } else {
                datasets[i].backgroundColor = colors[j];
                Chart.defaults.elements.line.fill = false;
                datasets[i].borderColor = colors[j];
            }
        }

        // derive the stacked or the stacked-100 option and adjust the data and options
        let stacked = chartTypeFull.endsWith('St') || chartTypeFull.endsWith('St100');
        let stacked100 = chartTypeFull.endsWith('St100');
        if (stacked === true) {
            chartOptions.scales['primary'].stacked = chartOptions.scales['x'].stacked = true;
            chartOptions.scales['primary'].max = 100;
        }
        if (stacked100 === true) {
            datasets = this.calculateStacked100(datasets);
        }

        // overwrite some default chart options depending on the chart type
        if (chartType === 'datetime') {
            chartOptions.scales['x'].type = 'time';
            chartOptions.scales['x'].distribution = 'linear';
        } else if (chartType === 'area') {
            chartOptions.scales['x'].type = 'time';
            chartOptions.scales['x'].distribution = 'linear';
            chartOptions.scales['primary'].stacked = true;
            chartOptions.scales['x'].stacked = false; // area does not work otherwise
            Chart.defaults.elements.line.fill = true;
        } else if (chartType === 'doughnut') {
            chartOptions.scales['x'].display = false;
            chartOptions.scales['primary'].display = chartOptions.scales['primary'].grid.display = false;
            chartOptions.scales['secondary'].display = chartOptions.scales['secondary'].grid.display = false;
            chartOptions.circumference = 180;
            chartOptions.rotation = -90;
            chartOptions.plugins.datalabels.display = true;
            chartOptions.plugins.datalabels.color = '#FFFFFF';
        } else if (chartType === 'funnel') {
            chartOptions.indexAxis = 'y';
            delete chartOptions.scales;
            chartOptions.plugins.datalabels.display = true;
            chartOptions.plugins.datalabels.color = '#EEEEEE';
            chartOptions.plugins.datalabels.formatter = (value, context) => {
                const label = context.chart.data.labels[context.dataIndex];
                return `${label}\n${(value).toLocaleString()}`;
                }
        }

        // the user can add/overwrite chart options
        // the user can put the options in array-format into the report definition
        // these are merged with the standard report settings
        // e.g. the display unit for the x-axis can be overwritten '{"scales": {"x": {"time": {"unit" : "month"}}}}'
        // e.g. add a secondary y-axis '{"scales":{"secondary":{"display":true}}}'

        let userChartOptions = jsondata.options.chartoptions;
        if (userChartOptions !== '' && userChartOptions !== null) {
            chartOptions = cloner.deep.merge(chartOptions, userChartOptions);
        }

        // the user can modify dataset/series settings
        // these are merged with the data array coming from the backend
        // e.g. assign one series to the secondary y-axis: '[{"yAxisID":"B"},{},{"yAxisID":"B"},{}]'
        // for doughnuts, no overwrites are allowed. Colors were taken care of before already
        let userDatasetOptions = jsondata.options.dataoptions;
        if (userDatasetOptions !== '' && userDatasetOptions !== null && chartType !== 'doughnut') {
            datasets = cloner.deep.merge({}, datasets);
            datasets = cloner.deep.merge(datasets, userDatasetOptions);
            datasets = Object.values(datasets);
        }

        OCA.Analytics.chartObject = new Chart(ctx, {
            plugins: [ChartDataLabels],
            type: OCA.Analytics.chartTypeMapping[chartType],
            data: {
                labels: xAxisCategories,
                datasets: datasets
            },
            options: chartOptions,
        });
    },

    convertDataToChartJsFormat: function (data, chartType, modelType) {
        let datasets = [], xAxisCategories = [];
        let dataModel = '';
        let header = data.header.slice(1);

        if (data.options.chartoptions !== null) {
            if (data.options.chartoptions?.analyticsModel !== undefined) {
                dataModel = data.options.chartoptions["analyticsModel"];
            }
        }

        data = data.data;

        // as of chartjs 4, the yAxis needs to be mapped to the primary axis per default

        if (dataModel === 'accountModel') {
            xAxisCategories = header;
            // Account Model: Create one dataset per row
            data.forEach(row => {
                const label = row[0]; // Date becomes the label
                const dataPoints = row.slice(1).map((value, index) => ({
                    x: xAxisCategories[index],
                    y: value
                }));
                datasets.push({label: label, data: dataPoints, yAxisID: 'primary'});
            });
        } else {
            // KPI-Model: Use existing logic
            const labelMap = new Map();
            const isTopGrouping = data.options?.filteroptions?.group?.type === 'top';
            let datasetCounter = 0;
            data.forEach((row) => {
                let dataSeriesColumn, characteristicColumn, value;
                if (row.length >= 3) {
                    [dataSeriesColumn, characteristicColumn, value] = row.slice(-3);
                } else if (row.length === 2) {
                    [characteristicColumn, value] = row;
                    dataSeriesColumn = '';
                }
                if (!xAxisCategories.includes(characteristicColumn)) {
                    xAxisCategories.push(characteristicColumn);
                }
                if (!labelMap.has(dataSeriesColumn)) {
                    labelMap.set(dataSeriesColumn, {
                        ...(chartType !== 'doughnut' && {label: dataSeriesColumn || undefined}),
                        data: [],
                        hidden: datasetCounter >= 4 && !isTopGrouping,
                        yAxisID: 'primary'
                    });
                    datasetCounter++;
                }
                const dataset = labelMap.get(dataSeriesColumn);

                // doughnut & funnel chart do not need a x/y mapping
                if (chartType === 'doughnut' || chartType === 'funnel') {
                    dataset.data.push(parseFloat(value));
                } else {
                    dataset.data.push({x: characteristicColumn, y: parseFloat(value)});
                }
            });
            datasets = Array.from(labelMap.values());
        }
        return [xAxisCategories, datasets];
    },

    calculateStacked100: function (rawData) {
        // Create a map to store total y-values for each x-label
        const totalMap = {};

        // Calculate total y-values for each x-label
        rawData.forEach(dataset => {
            dataset.data.forEach(point => {
                if (!totalMap[point.x]) {
                    totalMap[point.x] = 0;
                }
                totalMap[point.x] += point.y;
            });
        });

        // Convert y-values to percentages
        return rawData.map(dataset => {
            const newDataset = {...dataset};
            newDataset.data = dataset.data.map(point => {
                return {
                    x: point.x,
                    y: totalMap[point.x] === 0 ? 0 : (point.y / totalMap[point.x]) * 100
                };
            });
            return newDataset;
        });
    },

    // *************
    // *** backend ***
    // *************

    sortDates: function (data) {
        if (data.options.chartoptions !== null) {
            if (data.options.chartoptions?.scales?.x?.time?.parser !== undefined) {
                let parser = data.options.chartoptions["scales"]["x"]["time"]["parser"];
                data.data.sort(function (a, b) {
                    let sortColumn = a.length - 2;
                    if (sortColumn === 0) {
                        return myMoment(a[sortColumn], parser).toDate() - myMoment(b[sortColumn], parser).toDate();
                    } else {
                        return a[0] - b[0] || myMoment(a[sortColumn], parser).toDate() - myMoment(b[sortColumn], parser).toDate();
                    }
                });
            }
        }
        return data;
    },

    applyGrouping: function (data) {
        const group = data.options.filteroptions?.group;
        if (!group || group.type === 'none') {
            return data;
        }

        const dimension = parseInt(group.dimension);
        const n = parseInt(group.number);
        if (isNaN(dimension) || isNaN(n)) {
            return data;
        }

        const valueIndex = data.data[0].length - 1;

        const totals = {};
        data.data.forEach(row => {
            const key = row[dimension];
            const val = parseFloat(row[valueIndex]) || 0;
            totals[key] = (totals[key] || 0) + val;
        });

        const entries = Object.entries(totals);
        entries.sort((a, b) => group.type === 'top' ? b[1] - a[1] : a[1] - b[1]);
        const keepKeys = entries.slice(0, n).map(e => e[0]);

        const othersLabel = t('analytics', 'others');
        const aggregated = {};
        data.data.forEach(row => {
            const newRow = row.slice();
            if (!keepKeys.includes(row[dimension])) {
                if (!group.others) {
                    return;
                }
                newRow[dimension] = othersLabel;
            }

            const key = newRow.slice(0, valueIndex).join('\u0001');
            if (aggregated[key]) {
                aggregated[key][valueIndex] = (parseFloat(aggregated[key][valueIndex]) + parseFloat(newRow[valueIndex])).toString();
            } else {
                aggregated[key] = newRow;
            }
        });

        data.data = Object.values(aggregated);
        return data;
    },

    formatDates: function (data) {
        let firstRow = data[0];
        let now;
        for (let i = 0; i < firstRow.length; i++) {
            // loop columns and check for a valid date
            if (!isNaN(new Date(firstRow[i]).valueOf()) && firstRow[i] !== null && firstRow[i].length >= 19) {
                // column contains a valid date
                // then loop all rows for this column and convert to local time
                for (let j = 0; j < data.length; j++) {
                    if (data[j][i].length === 19) {
                        // values are assumed to have a timezone or are used as UTC
                        data[j][i] = data[j][i] + 'Z';
                    }
                    now = new Date(data[j][i]);
                    data[j][i] = now.getFullYear()
                        + "-" + (now.getMonth() < 9 ? '0' : '') + (now.getMonth() + 1) //getMonth will start with Jan = 0
                        + "-" + (now.getDate() < 10 ? '0' : '') + now.getDate()
                        + " " + (now.getHours() < 10 ? '0' : '') + now.getHours()
                        + ":" + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes()
                        + ":" + (now.getSeconds() < 10 ? '0' : '') + now.getSeconds()
                }
            }
        }
        return data;
    },

    validateThreshold: function (kpi, value, thresholds) {
        const operators = {
            '=': function (a, b) {
                return a === b
            },
            '<': function (a, b) {
                return a < b
            },
            '>': function (a, b) {
                return a > b
            },
            '<=': function (a, b) {
                return a <= b
            },
            '>=': function (a, b) {
                return a >= b
            },
            '!=': function (a, b) {
                return a !== b
            },
        };
        let thresholdColor;

        thresholds = thresholds.filter(p => p.dimension1 === kpi || p.dimension1 === '*');

        for (let threshold of thresholds) {
            const comparison = operators[threshold['option']](parseFloat(value), parseFloat(threshold['value']));
            threshold['severity'] = parseInt(threshold['severity']);
            if (comparison === true) {
                if (threshold['severity'] === 2) {
                    thresholdColor = 'color: red;';
                } else if (threshold['severity'] === 3) {
                    thresholdColor = 'color: orange;';
                } else if (threshold['severity'] === 4) {
                    thresholdColor = 'scolor: green;';
                }
            }
        }

        return thresholdColor;
    },

    showElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = false;
            if (element === 'tableContainer') {
                document.getElementById('chartContainer').classList.remove('fullHeight');
            }
        }
    },

    hideElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = true;
            if (element === 'tableContainer') {
                document.getElementById('chartContainer').classList.add('fullHeight');
            }
        }
    },

    toggleFullscreen: function () {
        document.getElementById('header').classList.toggle('analyticsFullscreen');
        document.getElementById('app-navigation').classList.toggle('analyticsFullscreen');
        document.getElementById('content').classList.toggle('analyticsFullscreen');
        document.getElementById('byAnalytics').classList.toggle('analyticsFullscreen');

        document.getElementById('fullscreenToggle').classList.toggle('icon-analytics-fullscreen');
        document.getElementById('fullscreenToggle').classList.toggle('icon-analytics-fullscreenExit');
    },
}