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

// Workaround because NC still delivers moment but triggers a deprecated warning every time it is used
var myMoment = moment;

/**
 * @namespace OCA.Analytics.Visualization
 */
OCA.Analytics.Visualization = {

    // *************
    // *** table ***
    // *************

    buildDataTable: function (domTarget, jsondata, ordering = true, uniqueId) {

        if (!uniqueId) {
            uniqueId = jsondata.options.id;
        } else {
            uniqueId = parseInt(uniqueId.replace(/[^0-9]+/g, ''),10);
        }

        if (OCA.Analytics.tableObject?.uniqueId) {
            OCA.Analytics.tableObject[uniqueId].destroy();
            domTarget.innerHTML = '';
            OCA.Analytics.tableObject[uniqueId] = [];
            //test
        }

        this.showElement('tableContainer');

        // get current table state
        let tableOptions = JSON.parse(jsondata.options.tableoptions)
            ? JSON.parse(jsondata.options.tableoptions)
            : {};
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

        ({data, columns} = this.convertDataToDataTableFormat(jsondata.data, tableOptions.layout, jsondata.header));
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

        const isDataLengthGreaterThanDefault = data.length > ((tableOptions && tableOptions.length) || defaultLength);

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
                OCA.Analytics.Visualization.dataTableRowCallback(row, data, index, jsondata.thresholds);
            },
            footerCallback: function () {
                OCA.Analytics.Visualization.dataTablefooterCallback(this.api(), tableOptions);
            }
        });

        if (!OCA.Analytics.isPanorama) {
            // Listener for when the pagination length is changed, table sorted, columns re-ordered
            OCA.Analytics.tableObject[uniqueId].on('length.dt', this.handleDataTableChanged);
            OCA.Analytics.tableObject[uniqueId].on('order.dt', this.handleDataTableChanged);
            OCA.Analytics.tableObject[uniqueId].on('column-reorder', this.handleDataTableChanged);
        }
    },

    convertDataToDataTableFormat: function (originalData, layoutConfig, header) {
        let uniqueHeaders = new Set();
        let transformedData = {};
        let columns = [];
        let data = '';

        if (!layoutConfig || Object.keys(layoutConfig).length === 0) {
            // No special layout is defined: display all data in rows
            columns = header.map((header, index) => ({title: header, className: index === 0 ? '' : 'dt-right'}));
            data = originalData.map(row =>
                row.map((value, index) =>
                    index === row.length - 1 && !isNaN(parseFloat(value)) ? parseFloat(value).toLocaleString() : value
                )
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

    dataTableRowCallback: function (row, data, index, thresholds) {
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
    },

    dataTablefooterCallback: function (api, tableOptions) {
        const footerRow = api.table().footer().querySelector('tr');
        const colReorder = tableOptions.colReorder ? tableOptions.colReorder.order : [...Array(api.columns().count()).keys()];

        // If footer should not be displayed, empty the footer and return
        if (tableOptions.footer !== true) {
            while (footerRow.firstChild) {
                footerRow.removeChild(footerRow.firstChild);
            }
            return;
        }

        colReorder.forEach((colIdx, displayIdx) => {
            const columnData = api.column(colIdx).data().toArray();
            const total = columnData.reduce(function (sum, curValue) {
                return sum + parseFloat(curValue || 0);
            }, 0);

            let cell = footerRow.querySelector('td:nth-child(' + (displayIdx + 1) + ')');
            // Append a new td if not exist
            if (!cell) {
                cell = footerRow.appendChild(document.createElement('td'));
            }
            // set cell value
            if (displayIdx === 0) {
                cell.innerHTML = 'Total';
            } else {
                cell.innerHTML = (total !== undefined && !isNaN(total)) ? parseFloat(total).toLocaleString() : '';
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
        Chart.defaults.elements.point.radius = 1;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.onClick = newLegendClickHandler;

        // convert the data array
        let [xAxisCategories, datasets] = this.convertDataToChartJsFormat(jsondata.data, chartType);

        // show legend button only when useful with >1 dataset
        datasets.length > 1 ? this.showElement('chartLegendContainer') : null;

        // do the color magic
        // a predefined color array is used
        let colors = ["#aec7e8", "#ffbb78", "#98df8a", "#ff9896", "#c5b0d5", "#c49c94", "#f7b6d2", "#c7c7c7", "#dbdb8d", "#9edae5"];
        for (let i = 0; i < datasets.length; ++i) {
            let j = i - (Math.floor(i / colors.length) * colors.length)

            // in only one dataset is being shown, create a fancy gradient fill
            if (datasets.length === 1 && chartType !== 'column' && chartType !== 'doughnut') {
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
            } else if (chartType === 'doughnut') {
                // special array handling for doughnuts
                if (jsondata.options.dataoptions !== null) {
                    const arr = JSON.parse(jsondata.options.dataoptions);
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
            chartOptions.scales['primary'].stacked = chartOptions.scales['xAxes'].stacked = true;
            chartOptions.scales['primary'].max = 100;
        }
        if (stacked100 === true) {
            datasets = this.calculateStacked100(datasets);
        }

        // overwrite some default chart options depending on the chart type
        if (chartType === 'datetime') {
            chartOptions.scales['xAxes'].type = 'time';
            chartOptions.scales['xAxes'].distribution = 'linear';
        } else if (chartType === 'area') {
            chartOptions.scales['xAxes'].type = 'time';
            chartOptions.scales['xAxes'].distribution = 'linear';
            chartOptions.scales['primary'].stacked = true;
            chartOptions.scales['xAxes'].stacked = false; // area does not work otherwise
            Chart.defaults.elements.line.fill = true;
        } else if (chartType === 'doughnut') {
            chartOptions.scales['xAxes'].display = false;
            chartOptions.scales['primary'].display = chartOptions.scales['primary'].grid.display = false;
            chartOptions.scales['secondary'].display = chartOptions.scales['secondary'].grid.display = false;
            chartOptions.circumference = 180;
            chartOptions.rotation = -90;
            chartOptions.plugins.datalabels.display = true;
        }

        // the user can add/overwrite chart options
        // the user can put the options in array-format into the report definition
        // these are merged with the standard report settings
        // e.g. the display unit for the x-axis can be overwritten '{"scales": {"xAxes": {"time": {"unit" : "month"}}}}'
        // e.g. add a secondary y-axis '{"scales":{"secondary":{"display":true}}}'

        let userChartOptions = jsondata.options.chartoptions;
        if (userChartOptions !== '' && userChartOptions !== null) {
            chartOptions = cloner.deep.merge(chartOptions, JSON.parse(userChartOptions));
        }

        // todo was used in panorame
        // never show any axis in the dashboard
        // chartOptions.scales['secondary'].display = false;
        //chartOptions.scales['primary'].display = true;

        // the user can modify dataset/series settings
        // these are merged with the data array coming from the backend
        // e.g. assign one series to the secondary y-axis: '[{"yAxisID":"B"},{},{"yAxisID":"B"},{}]'
        // for doughnuts, no overwrites are allowed. Colors were taken care of before already
        let userDatasetOptions = jsondata.options.dataoptions;
        if (userDatasetOptions !== '' && userDatasetOptions !== null && chartType !== 'doughnut') {
            let numberOfDatasets = datasets.length;
            let userDatasetOptionsCleaned = JSON.parse(userDatasetOptions);
            userDatasetOptionsCleaned.length = numberOfDatasets; // cut saved definitions if report now has less data sets
            datasets = cloner.deep.merge({}, datasets);
            datasets = cloner.deep.merge(datasets, userDatasetOptionsCleaned);
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

    convertDataToChartJsFormat: function (data, chartType) {
        const labelMap = new Map();
        let datasetCounter = 0;
        let datasets = [], xAxisCategories = [];
        data.forEach((row) => {
            // default expected columns
            let dataSeriesColumn, characteristicColumn, value;

            // when only 2 columns are provided, no label will be set
            if (row.length >= 3) {
                [dataSeriesColumn, characteristicColumn, value] = row.slice(-3);
            } else if (row.length === 2) {
                [characteristicColumn, value] = row;
                dataSeriesColumn = '';
            }

            // Add category labels only once and not for every data series
            if (!xAxisCategories.includes(characteristicColumn)) {
                xAxisCategories.push(characteristicColumn);
            }

            // create the data series
            if (!labelMap.has(dataSeriesColumn)) {
                labelMap.set(dataSeriesColumn, {
                    ...(chartType !== 'doughnut' && {label: dataSeriesColumn || undefined}), // no label for doughnut charts
                    data: [],
                    hidden: datasetCounter >= 4 // default hide > 4th series for better visibility
                });
                datasetCounter++;
            }

            const dataset = labelMap.get(dataSeriesColumn);
            if (chartType === 'doughnut') {
                dataset.data.push(parseFloat(value));
            } else {
                dataset.data.push({x: characteristicColumn, y: parseFloat(value)});
            }
        });

        if (chartType === 'doughnut') {
            datasets = [{data: Array.from(labelMap.values()).flatMap(d => d.data)}];
        } else {
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

    sortDates: function (data) {
        if (data.options.chartoptions !== '') {
            if (JSON.parse(data.options.chartoptions)?.scales?.xAxes?.time?.parser !== undefined) {
                let parser = JSON.parse(data.options.chartoptions)["scales"]["xAxes"]["time"]["parser"];
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

    showElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = false;
            //document.getElementById(element).style.removeProperty('display');
        }
    },

    hideElement: function (element) {
        if (document.getElementById(element)) {
            document.getElementById(element).hidden = true;
            //document.getElementById(element).style.display = 'none';
        }
    },

}