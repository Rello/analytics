/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// Historical single-report fixtures keep compatibility checks independent of bundle setup.
Object.assign(OCA.Analytics.Wizard, {
    salesRankingDemo: function () {
        const segment = 'Sales channel';
        const year = 'Year';
        const revenue = 'Revenue';
        const previousYear = OCA.Analytics.Visualization.getTableColumnReference('pivot', 1, year, '2025');
        const currentYear = OCA.Analytics.Visualization.getTableColumnReference('pivot', 1, year, '2026');
        const currencyFormat = {format: 'currency', currency: 'EUR', decimals: 0, align: 'right'};
        const channels = [
            ['Online shop', 42000, 58000, 76000],
            ['Retail stores', 38000, 51000, 63000],
            ['Channel partners', 31000, 39000, 54000],
            ['Direct sales', 14000, 18000, 24000],
            ['Marketplace', 9000, 12000, 16000],
            ['Events', 4000, 6000, 8000],
        ];

        // Pivot calculations run after Top N, so they can coexist with grouping.
        // Shared report transformations cannot currently be combined with Top N.
        return JSON.stringify({
            report: {
                name: 'Demo: Sales Ranking',
                type: 2,
                link: '{}',
                visualization: 'ct',
                chart: 'column',
                dimension1: segment,
                dimension2: year,
                value: revenue,
                subheader: 'Top 3 sales channels and others, comparing 2025 and 2026. Explore filters, sorting, chart field mapping and styles, and a pivot table with calculated totals and currency formatting.',
                chartoptions: JSON.stringify({
                    __analytics_gui: {
                        version: 4,
                        model: 'kpiModel',
                        columnMapping: {category: 0, series: [1], measures: [2]},
                    },
                    plugins: {legend: {display: true, position: 'bottom'}},
                    scales: {
                        x: {title: {display: true, text: segment}},
                        primary: {beginAtZero: true, title: {display: true, text: revenue + ' (€)'}},
                    },
                }),
                dataoptions: JSON.stringify([
                    {type: 'bar', yAxisID: 'primary', backgroundColor: '#0082c9', borderColor: '#0082c9'},
                    {type: 'line', yAxisID: 'primary', backgroundColor: '#e9a23b', borderColor: '#e9a23b', borderWidth: 3, pointRadius: 0.5, fill: false},
                ]),
                filteroptions: JSON.stringify({
                    filter: {dimension2: {option: 'GT', value: '2024'}},
                    sort: {dimension: '1', direction: 'ASC'},
                    topN: {dimension: '0', type: 'top', number: 3, others: true},
                }),
                tableoptions: JSON.stringify({
                    layout: {rows: [0], columns: [1], measures: [2], notRequired: []},
                    calculatedColumns: [
                        JSON.stringify({version: 2, operation: 'add', references: [previousYear, currentYear], title: 'Total revenue'}),
                        JSON.stringify({version: 2, operation: 'formula', references: [previousYear, currentYear], expression: 'ROUND(ref2 - ref1, 0)', title: 'Revenue increase'}),
                        JSON.stringify({version: 2, operation: 'percentage', references: [currentYear, previousYear], title: '2026 vs. 2025'}),
                    ].join(','),
                    columnFormats: [
                        {reference: OCA.Analytics.Visualization.getTableColumnReference('source', 0, segment), format: 'text', wrap: true, width: 180},
                        {reference: previousYear, ...currencyFormat},
                        {reference: currentYear, ...currencyFormat},
                        {reference: 'calculation:0', ...currencyFormat},
                        {reference: 'calculation:1', ...currencyFormat},
                        {reference: 'calculation:2', format: 'percent', decimals: 1, align: 'right'},
                    ],
                    order: [[3, 'desc']],
                    colReorder: {order: [0, 2, 1, 3, 4, 5]},
                    footer: true,
                    density: 'compact',
                    striped: true,
                    showHeader: true,
                    length: 10,
                }),
            },
            dataload: [],
            threshold: [],
            favorite: 'true',
            data: channels.flatMap(([channel, ...values]) => values.map((value, index) => [channel, String(2024 + index), String(value)])),
        });
    },

    salesRawDataDemo: async function (rankingReportId) {
        const request = async (path, method = 'GET', body) => {
            const response = await fetch(OC.generateUrl('apps/analytics/' + path), {
                method,
                headers: OCA.Analytics.headers(),
                body: body === undefined ? undefined : JSON.stringify(body),
            });
            if (!response.ok) throw new Error('Demo report creation failed');
            return response.json();
        };
        try {
            const ranking = await request('report/' + rankingReportId);
            if (!(Number(ranking.dataset) > 0)) throw new Error('Demo dataset missing');
            const reportId = await request('report', 'POST', {
                name: 'Demo: Sales raw data',
                subheader: 'All sales channels and years from the same dataset as Demo: Sales Ranking, without filters, grouping, or calculated columns. Compare the two reports to see what report options can do with the same data.',
                parent: 0,
                type: 2,
                dataset: Number(ranking.dataset),
                link: '{}',
                visualization: 'table',
                chart: '',
                dimension1: ranking.dimension1,
                dimension2: ranking.dimension2,
                value: ranking.value,
            });
            if (!(Number(reportId) > 0)) throw new Error('Demo report creation failed');
            await request('favorite/' + reportId, 'POST', {favorite: 'true'});
            return reportId;
        } catch (error) {
            OCA.Analytics.Notification.notification('error', t('analytics', 'Import failed'));
            throw error;
        }
    },

});
