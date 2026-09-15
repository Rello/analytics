/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, attachPageIssueListeners, createCapture} = require('./common');
const config = buildScenarioConfig('48');

(async () => {
    const browser = await chromium.launch({headless: config.headless});
    const page = await browser.newPage({viewport: config.viewport});
    const issues = [];
    attachPageIssueListeners(page, issues);
    const capture = createCapture(page, config);
    try {
        await ensureAnalyticsLoaded(page, config);
        const actual = await page.evaluate(() => {
            const options = OCA.Analytics.ChartOptions;
            const visualization = OCA.Analytics.Visualization;
            const run = (header, data, extra = {}, chartoptions = {}) => {
                const report = {header, data, options:{chart:'column',chartoptions,dataoptions:[]}, ...extra};
                const before = JSON.stringify(report);
                const model = options.getGuiState(chartoptions).model;
                const [labels, datasets] = visualization.convertDataToChartJsFormat(report, 'column');
                const preview = visualization.chartMappingPreview(report, model, options.getGuiState(chartoptions).columnMapping || null);
                return {
                    labels,
                    series:datasets.map(d=>({label:d.label ?? null, data:d.data, hidden:d.hidden ?? false})),
                    items:visualization.getChartSeriesItems(report, model).map(item=>item.label),
                    mapping:options.columnMapping(report, model, options.getGuiState(chartoptions).columnMapping),
                    previewLabels:preview.config.data.labels,
                    previewSeries:preview.config.data.datasets.map(d=>d.label ?? null),
                    unchanged:JSON.stringify(report) === before,
                };
            };
            const financeRows = [['North','2025','12'],['North','2026','15'],['Total','2025','20'],['Total','2026','25']];
            return {
                finance:run(['Segment','Year','€'],financeRows),
                gui4:run(['Segment','Year','€'],financeRows,{}, {__analytics_gui:{version:4,model:'kpiModel'}}),
                numericSeries:run(['ID','Year','€'],[[101,2025,'12'],[102,2025,'15']]),
                twoColumns:run(['Year','€'],[['2025','12'],['2026','15']]),
                extraColumn:run(['Ignored','Segment','Year','€'],[['x','North','2025','12'],['y','North','2026','15']],{options:{type:4,chart:'column',chartoptions:{}}}),
                inColumns:run(['Segment','2025','2026'],[['North',12,15],['South',8,10]],{}, {analyticsModel:'accountModel'}),
                timestamps:run(['Date','Revenue','Cost'],[['2026-01-01',12,7],['2026-02-01',15,9]],{}, {analyticsModel:'timeSeriesModel'}),
                explicit:run(['Segment','Year','€'],financeRows,{}, {__analytics_gui:{model:'kpiModel',columnMapping:{category:0,series:[1],measures:[2]}}}),
                typed:run(['Year','Revenue','Cost'],[[2025,12,7],[2026,15,9]],{
                    columnRefs:['c_1','c_2','c_3'],columns:[{ref:'c_1',role:'dimension',type:'integer'},{ref:'c_2',role:'measure',type:'decimal'},{ref:'c_3',role:'measure',type:'decimal'}],
                }),
                empty:run(['Segment','Year','€'],[]),
            };
        });
        assert.deepEqual(actual.finance.mapping, {category:1,series:[0],measures:[2]});
        assert.deepEqual(actual.finance.labels, ['2025','2026']);
        assert.deepEqual(actual.finance.series, [
            {label:'North',data:[{x:'2025',y:12},{x:'2026',y:15}],hidden:false},
            {label:'Total',data:[{x:'2025',y:20},{x:'2026',y:25}],hidden:false},
        ]);
        assert.deepEqual(actual.gui4,actual.finance);
        assert.deepEqual(actual.numericSeries.items,[101,102]);
        assert.deepEqual(actual.numericSeries.series.map(d=>d.label),[101,102]);
        assert.deepEqual(actual.twoColumns.mapping,{category:0,series:[],measures:[1]});
        assert.deepEqual(actual.twoColumns.series,[{label:null,data:[{x:'2025',y:12},{x:'2026',y:15}],hidden:false}]);
        assert.deepEqual(actual.twoColumns.items,['']);
        assert.deepEqual(actual.extraColumn.mapping,{category:2,series:[1],measures:[3]});
        assert.deepEqual(actual.extraColumn.series,[actual.finance.series[0]]);
        assert.deepEqual(actual.inColumns.labels,['2025','2026']);
        assert.deepEqual(actual.inColumns.series,[
            {label:'North',data:[{x:'2025',y:12},{x:'2026',y:15}],hidden:false},
            {label:'South',data:[{x:'2025',y:8},{x:'2026',y:10}],hidden:false},
        ]);
        assert.deepEqual(actual.timestamps.labels,['2026-01-01','2026-02-01']);
        assert.deepEqual(actual.timestamps.series.map(d=>d.label),['Revenue','Cost']);
        assert.deepEqual(actual.timestamps.series[0].data,[{x:'2026-01-01',y:12},{x:'2026-02-01',y:15}]);
        assert.deepEqual(actual.explicit.labels,['North','Total']);
        assert.deepEqual(actual.explicit.series.map(d=>d.label),['2025','2026']);
        assert.deepEqual(actual.typed.mapping,{category:'c_1',series:[],measures:['c_2','c_3']});
        assert.deepEqual(actual.typed.series.map(d=>d.label),['Revenue','Cost']);
        assert.deepEqual(actual.empty.mapping,{category:1,series:[0],measures:[2]});
        for (const fixture of Object.values(actual)) {
            assert.equal(fixture.unchanged,true);
            assert.deepEqual(fixture.previewLabels,fixture.labels);
            assert.deepEqual(fixture.previewSeries,fixture.series.map(d=>d.label));
        }

        // Exercise the complete dialog against the original Finance data shape.
        // Capture the Apply reload locally so no real report is changed.
        await page.evaluate(() => {
            OCA.Analytics.currentReportData = {
                header:['Segment','Year','€'],
                data:[['Channel Partners','2025',12],['Enterprise','2025',20],['Government','2025',8],['Total Sales','2025',40]],
                options:{chart:'column',chartoptions:{__analytics_gui:{version:4,model:'kpiModel'}},
                    dataoptions:[{type:'bar'},{type:'bar'},{type:'bar'},{type:'line',hidden:true,backgroundColor:'#123456',borderColor:'#123456'}],filteroptions:{}},
            };
            OCA.Analytics.Filter.openChartOptionsDialog();
        });
        await page.waitForFunction(()=>Chart.getChart(document.getElementById('chartColumnPreviewCanvas'))?.data.datasets.length===4);
        assert.equal(await page.locator('#chartColumnMappingSummary').innerText(),'Show € by Year, broken down by Segment.');
        assert.equal(await page.locator('#optionsChartType3').inputValue(),'line');
        assert.equal(await page.evaluate(()=>OCA.Analytics.Filter.getChartColumnMapping()),null);
        assert.equal(await page.evaluate(()=>Chart.getChart(document.getElementById('chartColumnPreviewCanvas')).data.datasets[3].type),'line');
        await page.locator('.analyticsEnhancedDialogNavButton').filter({hasText:'Data mapping'}).click();
        await page.waitForTimeout(400);
        await capture('legacy_finance_mapping');
        await page.evaluate(()=>{
            window.compatibilityReload = OCA.Analytics.Report.Backend.getData;
            OCA.Analytics.Report.Backend.getData = () => {};
        });
        await page.locator('#analyticsDialogBtnGo').click();
        assert.equal(await page.evaluate(()=>OCA.Analytics.ChartOptions.getGuiState(OCA.Analytics.currentReportData.options.chartoptions).columnMapping),undefined);
        assert.deepEqual(await page.evaluate(()=>OCA.Analytics.Flexible.seriesOptions(OCA.Analytics.currentReportData.options.dataoptions)[3]),{type:'line',hidden:true,backgroundColor:'#123456',borderColor:'#123456'});
        await page.evaluate(()=>{
            OCA.Analytics.Report.Backend.getData = window.compatibilityReload;
            OCA.Analytics.Filter.openChartOptionsDialog();
        });
        assert.equal(await page.locator('#chartColumnMappingSummary').innerText(),'Show € by Year, broken down by Segment.');
        await page.locator('#analyticsDialogBtnCancel').click();
        assert.deepEqual(issues,[]);
        console.log('PASS: legacy positional mapping, numeric dimensions, two/extra columns, all data formats, explicit mappings, typed schema, preview and unchanged Apply.');
    } catch (error) {
        await capture('failure');
        throw error;
    } finally {
        await browser.close();
    }
})();
