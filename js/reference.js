/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */
/** global: OCA */
/** global: Chart */
/** global: t */
/** global: _registerWidget */

'use strict';

const getSafeReferenceUrl = function (value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    try {
        const url = new URL(value, window.location.origin);

        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return null;
        }

        return url.href;
    } catch (error) {
        return null;
    }
};

if (!window.OCA) {
    window.OCA = {};
}
if (!OCA.Analytics) {
    /**
     * @namespace
     */
    OCA.Analytics = {};
}

// minimal namespace state required by visualization.js when it runs outside the main app
OCA.Analytics.chartObject = OCA.Analytics.chartObject || null;
OCA.Analytics.tableObject = OCA.Analytics.tableObject || {};
OCA.Analytics.unsavedChanges = OCA.Analytics.unsavedChanges || null;
OCA.Analytics.chartTypeMapping = OCA.Analytics.chartTypeMapping || {
    'datetime': 'line',
    'column': 'bar',
    'columnSt': 'bar', // map stacked type also to base type; needed in filter
    'columnSt100': 'bar', // map stacked type also to base type; needed in filter
    'area': 'line',
    'line': 'line',
    'doughnut': 'doughnut',
    'funnel': 'funnel'
};

/**
 * @namespace OCA.Analytics.Reference
 */
OCA.Analytics.Reference = {
    PANORAMA_CONTENT_TYPE_REPORT: 0,
    PANORAMA_CONTENT_TYPE_TEXT: 1,
    PANORAMA_CONTENT_TYPE_PICTURE: 2,

    instanceCounter: 0,
    // widget root element => {canvases: [], tableUids: []}, used by the destroy callback
    widgetRegistry: new Map(),
    coreAssetsPromise: null,
    tableAssetsPromise: null,
    scriptPromises: {},

    init: function () {
        if (typeof _registerWidget !== 'function') {
            return;
        }
        _registerWidget('analytics', async (el, {richObjectType, richObject, accessible}) => {
            await OCA.Analytics.Reference.renderWidget(el, richObject);
        }, (el) => {
            OCA.Analytics.Reference.destroyWidget(el);
        }, {hasInteractiveView: false});
    },

    // *************
    // *** rendering
    // *************

    renderWidget: async function (el, richObject) {
        if (!richObject
            || !richObject.id
            || richObject.found === false
            || (richObject.item_type !== 'report' && richObject.item_type !== 'panorama')
        ) {
            // covers items the user cannot access and references cached before this widget existed
            OCA.Analytics.Reference.renderStaticCard(el, richObject);
            return;
        }

        const widget = document.createElement('div');
        widget.classList.add('analytics-reference-widget');

        const header = document.createElement('div');
        header.classList.add('analytics-reference-header');
        const icon = document.createElement('img');
        const iconUrl = getSafeReferenceUrl(richObject.image);
        if (iconUrl) {
            icon.setAttribute('src', iconUrl);
            icon.setAttribute('alt', '');
            header.appendChild(icon);
        }
        const referenceUrl = getSafeReferenceUrl(richObject.url);
        const headerLink = document.createElement(referenceUrl ? 'a' : 'span');
        if (referenceUrl) {
            headerLink.setAttribute('href', referenceUrl);
            headerLink.setAttribute('target', '_blank');
            headerLink.setAttribute('rel', 'noopener noreferrer');
        }
        headerLink.textContent = richObject.subheader || richObject.name || '';
        header.appendChild(headerLink);
        widget.appendChild(header);

        const body = document.createElement('div');
        body.classList.add('analytics-reference-body');
        body.appendChild(OCA.Analytics.Reference.buildLoadingIndicator());
        widget.appendChild(body);

        el.textContent = '';
        el.appendChild(widget);
        OCA.Analytics.Reference.widgetRegistry.set(el, {canvases: [], tableUids: []});

        try {
            if (richObject.item_type === 'panorama') {
                await OCA.Analytics.Reference.renderPanorama(el, richObject, headerLink, body);
            } else {
                await OCA.Analytics.Reference.renderReport(el, richObject, headerLink, body);
            }
        } catch (error) {
            // asset loading blocked, report deleted, permission revoked, …
            OCA.Analytics.Reference.destroyWidget(el);
            OCA.Analytics.Reference.renderStaticCard(el, richObject);
        }
    },

    renderReport: async function (el, richObject, headerLink, body) {
        await OCA.Analytics.Reference.ensureCoreAssets();
        let data = await OCA.Analytics.Reference.fetchReportData(
            OC.generateUrl('apps/analytics/data/' + richObject.id, true),
            'analytics-report-' + richObject.id
        );

        data = OCA.Analytics.Reference.processReceivedData(data);
        if (data.options && data.options.name) {
            headerLink.textContent = data.options.name;
        }

        if (data.status === 'nodata' || !Array.isArray(data.data) || data.data.length === 0) {
            body.replaceChildren(OCA.Analytics.Reference.buildMessage(t('analytics', 'No data found')));
            return;
        }

        data.data = OCA.Analytics.Visualization.formatDates(data.data);
        await OCA.Analytics.Reference.renderVisualization(el, body, data, false);
    },

    renderPanorama: async function (el, richObject, headerLink, body) {
        await OCA.Analytics.Reference.ensureCoreAssets();
        const meta = await OCA.Analytics.Reference.fetchJson(
            OC.generateUrl('apps/analytics/panorama/' + richObject.id, true)
        );
        if (!meta || !meta.id) {
            throw new Error('panorama not available');
        }
        if (meta.name) {
            headerLink.textContent = meta.name;
        }

        let pages = meta.pages;
        if (typeof pages === 'string') {
            pages = JSON.parse(pages);
        }
        if (!Array.isArray(pages) || pages.length === 0
            || !Array.isArray(pages[0].reports) || pages[0].reports.length === 0) {
            body.replaceChildren(OCA.Analytics.Reference.buildMessage(t('analytics', 'No data found')));
            return;
        }

        body.replaceChildren();
        body.classList.add('analytics-reference-panorama');

        const cellPromises = pages[0].reports.map((item) => {
            const cell = document.createElement('div');
            cell.classList.add('analytics-reference-panorama-cell');
            body.appendChild(cell);
            return OCA.Analytics.Reference.renderPanoramaCell(el, cell, item);
        });

        if (pages.length > 1) {
            const footer = document.createElement('div');
            footer.classList.add('analytics-reference-footer');
            const referenceUrl = getSafeReferenceUrl(richObject.url);
            const link = document.createElement(referenceUrl ? 'a' : 'span');
            if (referenceUrl) {
                link.setAttribute('href', referenceUrl);
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            }
            link.textContent = t('analytics', 'Page') + ' 1/' + pages.length;
            footer.appendChild(link);
            body.parentNode.appendChild(footer);
        }

        // a failing single cell must not tear down the whole panorama widget
        await Promise.allSettled(cellPromises);
    },

    renderPanoramaCell: async function (el, cell, item) {
        if (item === null || item === undefined) {
            return;
        }
        const contentType = parseInt(item['type']);
        const contentValue = item['value'];

        if (contentType === OCA.Analytics.Reference.PANORAMA_CONTENT_TYPE_TEXT) {
            const text = document.createElement('div');
            text.classList.add('analytics-reference-panorama-text');
            // DOMParser neither executes scripts nor loads resources; plain text is enough here
            text.textContent = new DOMParser().parseFromString(String(contentValue ?? ''), 'text/html').body.textContent || '';
            cell.appendChild(text);
            return;
        }

        if (contentType === OCA.Analytics.Reference.PANORAMA_CONTENT_TYPE_PICTURE) {
            const pictureContainer = document.createElement('div');
            pictureContainer.classList.add('analytics-reference-panorama-picture');
            const image = document.createElement('img');
            image.setAttribute('alt', '');
            image.src = OC.generateUrl('/core/preview') + '?fileId=' + encodeURIComponent(contentValue) + '&x=300&y=300&a=true';
            pictureContainer.appendChild(image);
            cell.appendChild(pictureContainer);
            return;
        }

        if (contentType !== OCA.Analytics.Reference.PANORAMA_CONTENT_TYPE_REPORT) {
            return;
        }

        const title = document.createElement('div');
        title.classList.add('analytics-reference-panorama-cell-title');
        cell.appendChild(title);
        const content = document.createElement('div');
        content.classList.add('analytics-reference-panorama-cell-content');
        content.appendChild(OCA.Analytics.Reference.buildLoadingIndicator());
        cell.appendChild(content);

        try {
            const reportId = parseInt(contentValue);
            let data = await OCA.Analytics.Reference.fetchReportData(
                OC.generateUrl('apps/analytics/data/pa/' + reportId, true),
                'analytics-report-' + reportId
            );
            data = OCA.Analytics.Reference.processReceivedData(data);
            title.textContent = (data.options && data.options.name) || '';

            if (data.status === 'nodata' || !Array.isArray(data.data) || data.data.length === 0) {
                content.replaceChildren(OCA.Analytics.Reference.buildMessage(t('analytics', 'No data found')));
                return;
            }
            data.data = OCA.Analytics.Visualization.formatDates(data.data);

            const legend = item?.options?.legend;
            await OCA.Analytics.Reference.renderVisualization(el, content, data, true, legend);
        } catch (error) {
            content.replaceChildren(OCA.Analytics.Reference.buildMessage(t('analytics', 'The report is not available anymore')));
        }
    },

    /**
     * dispatch a processed data payload to chart / KPI / table rendering
     * compact = panorama cell; legend only applies to compact charts
     */
    renderVisualization: async function (el, container, data, compact, legend) {
        const visualization = data.options.visualization;
        const registryEntry = OCA.Analytics.Reference.widgetRegistry.get(el);

        if (visualization === 'table' && data.data.length === 1) {
            // KPI view, same heuristic as the panorama
            const kpi = document.createElement('div');
            container.replaceChildren(kpi);
            OCA.Analytics.Visualization.buildKpiDisplay(kpi, data, false, OCA.Analytics.Reference.nextUid());
            return;
        }

        if (visualization === 'table') {
            await OCA.Analytics.Reference.ensureTableAssets();
            container.replaceChildren();
            container.classList.add('analytics-reference-scroll');
            OCA.Analytics.Reference.buildTable(container, data, registryEntry);
            return;
        }

        if (visualization === 'ct') {
            await OCA.Analytics.Reference.ensureTableAssets();
            container.replaceChildren();
            container.classList.add('analytics-reference-ct');
            const chartArea = document.createElement('div');
            chartArea.classList.add('analytics-reference-chart-area');
            container.appendChild(chartArea);
            OCA.Analytics.Reference.buildChart(chartArea, data, compact, legend, registryEntry);
            const tableArea = document.createElement('div');
            tableArea.classList.add('analytics-reference-table-area');
            container.appendChild(tableArea);
            OCA.Analytics.Reference.buildTable(tableArea, data, registryEntry);
            return;
        }

        // 'chart' and anything unknown
        container.replaceChildren();
        OCA.Analytics.Reference.buildChart(container, data, compact, legend, registryEntry);
    },

    buildChart: function (container, data, compact, legend, registryEntry) {
        const canvas = document.createElement('canvas');
        canvas.id = 'analyticsReferenceChart' + OCA.Analytics.Reference.nextUid();
        container.appendChild(canvas);
        if (registryEntry) {
            registryEntry.canvases.push(canvas);
        }

        const chartOptions = compact
            ? OCA.Analytics.Reference.getCompactChartOptions(legend)
            : OCA.Analytics.Reference.getDefaultChartOptions();
        OCA.Analytics.Visualization.buildChart(canvas.getContext('2d'), data, chartOptions);
    },

    buildTable: function (container, data, registryEntry) {
        const table = document.createElement('table');
        const uid = OCA.Analytics.Reference.nextUid();
        table.id = 'analyticsReferenceTable' + uid;
        container.appendChild(table);
        OCA.Analytics.Visualization.buildDataTable(table, data, true, uid);
        if (registryEntry) {
            registryEntry.tableUids.push(uid);
        }
    },

    // unique per widget instance; buildDataTable/buildKpiDisplay reduce the uid to its digits
    nextUid: function () {
        return String(++OCA.Analytics.Reference.instanceCounter);
    },

    destroyWidget: function (el) {
        const entry = OCA.Analytics.Reference.widgetRegistry.get(el);
        if (!entry) {
            return;
        }
        entry.canvases.forEach((canvas) => {
            try {
                const chart = window.Chart ? Chart.getChart(canvas) : null;
                if (chart) {
                    chart.destroy();
                }
            } catch (error) {
            }
        });
        entry.tableUids.forEach((uid) => {
            const numericUid = parseInt(String(uid).replace(/[^0-9]+/g, ''), 10);
            const tableObject = OCA.Analytics.tableObject && OCA.Analytics.tableObject[numericUid];
            if (tableObject && typeof tableObject.destroy === 'function') {
                try {
                    tableObject.destroy();
                } catch (error) {
                }
                delete OCA.Analytics.tableObject[numericUid];
            }
        });
        OCA.Analytics.Reference.widgetRegistry.delete(el);
    },

    buildLoadingIndicator: function () {
        const loading = document.createElement('div');
        loading.classList.add('icon-loading');
        loading.style.height = '100%';
        return loading;
    },

    buildMessage: function (message) {
        const div = document.createElement('div');
        div.classList.add('analytics-reference-message');
        div.textContent = message;
        return div;
    },

    renderStaticCard: function (el, richObject) {
        const referenceUrl = getSafeReferenceUrl(richObject?.url);
        const imageUrl = getSafeReferenceUrl(richObject?.image);
        const widget = document.createElement(referenceUrl ? 'a' : 'div');
        widget.classList.add('analytics-reference-fallback');
        widget.style.display = 'flex';

        if (referenceUrl) {
            widget.setAttribute('href', referenceUrl);
            widget.setAttribute('target', '_blank');
            widget.setAttribute('rel', 'noopener noreferrer');
        }

        const content = document.createElement('div');
        content.classList.add('analytics-reference-fallback-content');
        content.style.padding = '10px';
        content.style.width = imageUrl ? '75%' : '100%';

        const title = document.createElement('div');
        title.classList.add('analytics-reference-fallback-title');
        title.style.fontWeight = '600';
        title.textContent = richObject?.name || '';

        const subheader = document.createElement('div');
        subheader.classList.add('analytics-reference-fallback-subheader');
        subheader.style.marginTop = '1em';
        subheader.textContent = richObject?.subheader || '';

        content.appendChild(title);
        content.appendChild(subheader);

        if (imageUrl) {
            const image = document.createElement('img');
            image.setAttribute('src', imageUrl);
            image.setAttribute('alt', '');
            image.style.width = '20%';
            image.style.padding = '20px';
            image.style.opacity = '.5';
            widget.appendChild(image);
        }

        widget.appendChild(content);

        el.textContent = '';
        el.appendChild(widget);
    },

    // *************
    // *** data access
    // *************

    fetchJson: function (url) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', url);
            xhr.setRequestHeader('requesttoken', OC.requestToken);
            xhr.setRequestHeader('OCS-APIREQUEST', 'true');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== XMLHttpRequest.DONE) {
                    return;
                }
                if (xhr.status === 200) {
                    try {
                        resolve(JSON.parse(xhr.response));
                    } catch (e) {
                        reject(e);
                    }
                } else {
                    reject(new Error('request failed: ' + xhr.status));
                }
            };
            xhr.onerror = function () {
                reject(new Error('request failed'));
            };
            xhr.send();
        });
    },

    // ETag / localStorage caching identical to the dashboard widget, but without
    // its 20-row truncation - the reference widget shows the full report
    fetchReportData: function (url, cacheKey) {
        const storage = OCA.Analytics.Reference.getLocalStorage();

        let cachedData = null;
        let cachedVersion = null;
        if (storage) {
            try {
                const cachedEntry = storage.getItem(cacheKey);
                if (cachedEntry) {
                    const parsed = JSON.parse(cachedEntry);
                    cachedData = parsed.data;
                    cachedVersion = parsed.version;
                }
            } catch (e) {
                try {
                    storage.removeItem(cacheKey);
                } catch (removeError) {
                }
            }
        }

        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', url);
            xhr.setRequestHeader('requesttoken', OC.requestToken);
            xhr.setRequestHeader('OCS-APIREQUEST', 'true');

            if (cachedVersion) {
                xhr.setRequestHeader('If-None-Match', cachedVersion);
            }

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== XMLHttpRequest.DONE) {
                    return;
                }
                if (xhr.status === 200) {
                    let data;
                    try {
                        data = JSON.parse(xhr.response);
                    } catch (e) {
                        reject(e);
                        return;
                    }

                    const newVersion = xhr.getResponseHeader('ETag') || null;
                    const cacheable = xhr.getResponseHeader('X-Analytics-Cacheable') === 'true';
                    if (cacheable && newVersion && storage) {
                        try {
                            storage.setItem(cacheKey, JSON.stringify({data: data, version: newVersion}));
                        } catch (e) {
                        }
                    }
                    resolve(data);
                } else if (xhr.status === 304 && cachedData) {
                    resolve(cachedData);
                } else {
                    reject(new Error('request failed: ' + xhr.status));
                }
            };
            xhr.onerror = function () {
                reject(new Error('request failed'));
            };
            xhr.send();
        });
    },

    getLocalStorage: function () {
        if (typeof window === 'undefined') {
            return null;
        }
        try {
            return typeof window.localStorage === 'undefined' ? null : window.localStorage;
        } catch (e) {
            return null;
        }
    },

    processReceivedData: function (data) {
        data.options.chartoptions = OCA.Analytics.ChartOptions.parseAndNormalize(data.options.chartoptions);

        const parsedDataOptions = OCA.Analytics.ChartOptions.safeParse(data.options.dataoptions, []);
        data.options.dataoptions = Array.isArray(parsedDataOptions) ? parsedDataOptions : [];

        const parsedFilterOptions = OCA.Analytics.ChartOptions.safeParse(data.options.filteroptions, {});
        data.options.filteroptions = (
            parsedFilterOptions !== null
            && typeof parsedFilterOptions === 'object'
            && !Array.isArray(parsedFilterOptions)
        ) ? parsedFilterOptions : {};

        const parsedTableOptions = OCA.Analytics.ChartOptions.safeParse(data.options.tableoptions, {});
        data.options.tableoptions = (parsedTableOptions !== null && typeof parsedTableOptions === 'object') ? parsedTableOptions : {};

        // if the user uses a special time parser (e.g. DD.MM), the data needs to be sorted differently
        data = OCA.Analytics.Visualization.sortDates(data);
        data = OCA.Analytics.Visualization.applyTimeAggregation(data);
        data = OCA.Analytics.Visualization.applyTopN(data);

        return data;
    },

    // *************
    // *** chart options
    // *************

    // full view: axes, grid and stored report options apply (same as the public report page)
    getDefaultChartOptions: function () {
        return {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                'primary': {
                    type: 'linear',
                    stacked: false,
                    position: 'left',
                    display: true,
                    grid: {
                        display: true,
                    },
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString();
                        },
                    },
                },
                'secondary': {
                    type: 'linear',
                    stacked: false,
                    position: 'right',
                    display: false,
                    grid: {
                        display: false,
                    },
                    ticks: {
                        callback: function (value) {
                            return value.toLocaleString();
                        },
                    },
                },
                'x': {
                    type: 'category',
                    time: {
                        parser: 'YYYY-MM-DD HH:mm',
                        tooltipFormat: 'LL',
                    },
                    distribution: 'linear',
                    grid: {
                        display: false
                    },
                    display: true,
                },
            },
            animation: {
                duration: 0 // general animation time
            },
            interaction: {
                mode: 'x',
                intersect: false,
            },
            plugins: {
                tooltip: OCA.Analytics.Visualization.getSharedTooltipOptions(),
                datalabels: {
                    display: false,
                    formatter: (value, ctx) => {
                        let sum = 0;
                        let dataArr = ctx.chart.data.datasets[0].data;
                        dataArr.map(data => {
                            sum += data;
                        });
                        value = (value * 100 / sum).toFixed(0);
                        if (value > 5) {
                            return value + "%";
                        } else {
                            return '';
                        }
                    },
                },
            },
        };
    },

    // panorama cells: compact like the panorama page (no grid lines, optional legend)
    getCompactChartOptions: function (legend) {
        const options = {
            devicePixelRatio: 2,
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                'primary': {
                    stacked: false,
                    position: 'left',
                    display: true,
                    grid: {
                        display: false,
                    },
                },
                'secondary': {
                    stacked: false,
                    position: 'right',
                    display: false,
                    grid: {
                        display: false,
                    },
                },
                'x': {
                    type: 'category',
                    distribution: 'linear',
                    grid: {
                        display: false
                    },
                    display: true,
                },
            },
            animation: {
                duration: 0 // general animation time
            },
            interaction: {
                mode: 'x',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                },
                tooltip: OCA.Analytics.Visualization.getSharedTooltipOptions(),
                datalabels: {
                    display: false,
                    formatter: (value, ctx) => {
                        let sum = 0;
                        let dataArr = ctx.chart.data.datasets[0].data;
                        dataArr.map(data => {
                            sum += data;
                        });
                        value = (value * 100 / sum).toFixed(0);
                        if (value > 5) {
                            return value + "%";
                        } else {
                            return '';
                        }
                    },
                }
            },
        };
        if (legend !== undefined) {
            options.plugins.legend.display = legend;
        }
        return options;
    },

    // *************
    // *** lazy asset loading
    // *************

    // the chart stack (~600KB) is only loaded once an analytics reference is actually
    // rendered, not on every page that might show references (Talk, Text, Tables)
    ensureCoreAssets: function () {
        if (OCA.Analytics.Reference.coreAssetsPromise) {
            return OCA.Analytics.Reference.coreAssetsPromise;
        }
        const load = OCA.Analytics.Reference.loadScript;
        OCA.Analytics.Reference.coreAssetsPromise = Promise.all([
            load('3rdParty/moment.min', () => window.moment),
            load('3rdParty/cloner', () => window.cloner),
        ])
            .then(() => load('3rdParty/chart.umd', () => window.Chart))
            .then(() => Promise.all([
                load('3rdParty/chartjs-adapter-moment'),
                load('3rdParty/chartjs-plugin-datalabels.min', () => window.ChartDataLabels),
                load('3rdParty/chartjs-plugin-funnel.min'),
                load('3rdParty/chartjs-plugin-annotation.min'),
            ]))
            .then(() => load('chartOptions', () => OCA.Analytics.ChartOptions && OCA.Analytics.ChartOptions.parseAndNormalize))
            .then(() => load('visualization', () => OCA.Analytics.Visualization && OCA.Analytics.Visualization.buildChart))
            .then(() => {
                // visualization.js event handlers call into modules of the main app
                // which are not loaded in the reference context
                OCA.Analytics.Filter = OCA.Analytics.Filter || {};
                OCA.Analytics.Filter.toggleSaveButtonDisplay = OCA.Analytics.Filter.toggleSaveButtonDisplay || function () {};
                OCA.Analytics.Filter.syncChartLegendSelections = OCA.Analytics.Filter.syncChartLegendSelections || function () {};
                OCA.Analytics.Report = OCA.Analytics.Report || {};
                OCA.Analytics.Report.hideReportMenu = OCA.Analytics.Report.hideReportMenu || function () {};
            });
        return OCA.Analytics.Reference.coreAssetsPromise;
    },

    ensureTableAssets: function () {
        if (OCA.Analytics.Reference.tableAssetsPromise) {
            return OCA.Analytics.Reference.tableAssetsPromise;
        }
        const load = OCA.Analytics.Reference.loadScript;
        // Talk/Text may already ship a jQuery; never load a second one
        OCA.Analytics.Reference.tableAssetsPromise = load('3rdParty/jquery.min', () => window.jQuery)
            .then(() => load('3rdParty/datatables.min', () => window.DataTable && window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable))
            .then(() => OCA.Analytics.Reference.loadStyle('3rdParty/datatables.min'));
        return OCA.Analytics.Reference.tableAssetsPromise;
    },

    loadScript: function (name, testFn) {
        if (testFn && testFn()) {
            return Promise.resolve();
        }
        if (OCA.Analytics.Reference.scriptPromises[name]) {
            return OCA.Analytics.Reference.scriptPromises[name];
        }
        OCA.Analytics.Reference.scriptPromises[name] = new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = OC.filePath('analytics', 'js', name + '.js');
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('could not load ' + name));
            document.head.appendChild(script);
        });
        return OCA.Analytics.Reference.scriptPromises[name];
    },

    loadStyle: function (name) {
        const href = OC.filePath('analytics', 'css', name + '.css');
        if (document.querySelector('link[href="' + href + '"]')) {
            return Promise.resolve();
        }
        return new Promise(function (resolve) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            // a missing stylesheet only degrades the table styling
            link.onload = () => resolve();
            link.onerror = () => resolve();
            document.head.appendChild(link);
        });
    },
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        OCA.Analytics.Reference.init();
    });
} else {
    OCA.Analytics.Reference.init();
}
