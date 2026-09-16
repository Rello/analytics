/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {buildScenarioConfig, ensureAnalyticsLoaded, createCapture, attachPageIssueListeners} = require('./common');
const config = buildScenarioConfig('49');

(async () => {
    const browser = await chromium.launch({headless: config.headless});
    const page = await browser.newPage({viewport: config.viewport});
    const capture = createCapture(page, config);
    const issues = [];
    attachPageIssueListeners(page, issues);
    page.on('response', response => {
        if (response.status() >= 500) console.error('HTTP', response.status(), response.url());
    });
    const settle = () => page.waitForFunction(() => !!OCA.Analytics.TableOptions.active?.preview);
    const open = async (fixture = {}) => {
        await page.evaluate(fixture => {
            OCA.Analytics.Notification.dialogClose();
            OCA.Analytics.currentReportData = {
                header: ['Region', 'Quarter', 'Revenue', 'Cost'],
                data: [['North','Q1',12400,8100],['North','Q2',16800,10300],['South','Q1',8200,5900],['South','Q2',10900,7700]],
                thresholds: [], options: {id: 999999, chart: 'column', tableoptions: {}, filteroptions: {}},
                ...fixture,
            };
            window.tableBefore = JSON.stringify(OCA.Analytics.currentReportData);
            window.tableRegistryBefore = Object.keys(OCA.Analytics.tableObject);
            window.tableInstancesBefore = DataTable.settings.length;
            OCA.Analytics.unsavedChanges = false;
            OCA.Analytics.Filter.openTableOptionsDialog();
        }, fixture);
        await settle();
    };
    const nav = async name => {
        await page.getByRole('link', {name, exact: true}).click();
        await page.waitForTimeout(200);
    };
    const tableText = () => page.locator('#tableOptionsPreviewTable').innerText();
    try {
        await ensureAnalyticsLoaded(page, config);
        const liveStripeProbe = await page.evaluate(() => {
            const table = document.getElementById('tableContainer');
            const report = {
                header: ['Name', 'Value'], data: [['A', 1], ['B', 2]], thresholds: [],
                options: {id: 999997, tableoptions: {}, filteroptions: {}},
            };
            const render = () => OCA.Analytics.Visualization.buildDataTable(table, report);
            let instance = render();
            const defaultEnabled = table.classList.contains('stripe');
            const defaultShadows = [...table.querySelectorAll('tbody tr td:last-child')]
                .map(cell => getComputedStyle(cell).boxShadow);
            instance.destroy();
            delete OCA.Analytics.tableObject[report.options.id];
            table.replaceChildren();
            report.options.tableoptions = {striped: false};
            instance = render();
            const disabled = !table.classList.contains('stripe');
            instance.destroy();
            delete OCA.Analytics.tableObject[report.options.id];
            table.replaceChildren();
            table.className = '';
            return {defaultEnabled, disabled, defaultShadows};
        });
        assert.equal(liveStripeProbe.defaultEnabled, true);
        assert.equal(liveStripeProbe.disabled, true);
        assert.notEqual(liveStripeProbe.defaultShadows[0], liveStripeProbe.defaultShadows[1]);
        const referencePreviewProbe = await page.evaluate(() => {
            const host = document.createElement('div');
            host.style.width = '600px';
            const table = document.createElement('table');
            host.appendChild(table);
            document.body.appendChild(host);
            const uid = 'referencePreview888001';
            const before = DataTable.settings.length;
            const instance = OCA.Analytics.Visualization.buildDataTable(table, {
                header: ['Name', 'Value'],
                data: Array.from({length: 15}, (_, index) => ['Row ' + index, index]),
                thresholds: [],
                options: {id: 888001, tableoptions: {length: 25}, filteroptions: {}},
            }, true, uid, {referencePreview: true});
            const container = instance.table().container();
            const result = {
                rows: table.querySelectorAll('tbody tr').length,
                pageLength: instance.page.len(),
                total: instance.page.info().recordsTotal,
                topControls: container.querySelectorAll('.dt-search, .dt-length, .dt-info').length,
                pagination: container.querySelectorAll('.dt-paging').length,
                registered: OCA.Analytics.tableObject[888001] === instance,
            };
            instance.page(1).draw('page');
            result.secondPage = instance.page.info().page;
            result.secondPageRows = table.querySelectorAll('tbody tr').length;
            instance.destroy();
            delete OCA.Analytics.tableObject[888001];
            host.remove();
            return {...result, cleanedUp: DataTable.settings.length === before};
        });
        assert.deepEqual(referencePreviewProbe, {
            rows: 10, pageLength: 10, total: 15, topControls: 0, pagination: 1,
            registered: true, secondPage: 1, secondPageRows: 5, cleanedUp: true,
        });
        await open();
        const navigationOffset = await page.locator('.analyticsEnhancedDialogNav').evaluate(nav => {
            const bounds = nav.getBoundingClientRect();
            const links = nav.querySelector('.analyticsEnhancedDialogNavList').getBoundingClientRect();
            return Math.abs((bounds.top + bounds.bottom) / 2 - (links.top + links.bottom) / 2);
        });
        assert.ok(navigationOffset <= 1, `Navigation is off-center by ${navigationOffset}px`);
        const documentationLink = page.getByRole('link', {name: 'Open documentation', exact: true});
        assert.equal(await documentationLink.getAttribute('href'), 'https://github.com/Rello/analytics/wiki/Table-Options');
        assert.equal(await documentationLink.getAttribute('target'), '_blank');
        const headerButtonStyles = await page.locator('#tableOptionsPreviewTable thead th').evaluateAll(headers =>
            headers.slice(0, 2).map(header => {
                const button = header.querySelector('.tableOptionsPreviewColumn');
                const style = getComputedStyle(button);
                return {background: style.backgroundColor, outline: style.outlineStyle, radius: style.borderRadius};
            }));
        assert.equal(headerButtonStyles[0].background, headerButtonStyles[1].background);
        assert.equal(headerButtonStyles[0].outline, 'solid');
        assert.notEqual(headerButtonStyles[0].radius, '0px');
        const activeNavigation = () => page.locator('.analyticsEnhancedDialogNavButton--active').innerText();
        assert.equal(await activeNavigation(), 'Layout');
        assert.equal(await page.getByRole('link', {name: 'Layout', exact: true}).evaluate(link =>
            getComputedStyle(link.querySelector('.analyticsEnhancedDialogNavLabel'), '::after').height), '2px');
        await nav('Columns');
        await page.waitForFunction(() => {
            const panel = document.querySelector('.analyticsEnhancedDialogPanel');
            const heading = document.querySelector('#tableColumnSection h2').getBoundingClientRect();
            const bounds = panel.getBoundingClientRect();
            return heading.top >= bounds.top + 20 && heading.top <= bounds.top + 50;
        });
        assert.equal(await activeNavigation(), 'Columns');
        await page.evaluate(() => {
            const panel = document.querySelector('.analyticsEnhancedDialogPanel');
            const section = document.getElementById('tableHighlightSection');
            panel.scrollTo({top: section.getBoundingClientRect().top - panel.getBoundingClientRect().top + panel.scrollTop});
        });
        await page.waitForFunction(() => document.querySelector('.analyticsEnhancedDialogNavButton--active')?.textContent.trim() === 'Highlighting');
        assert.equal(await activeNavigation(), 'Highlighting');
        await nav('Layout');
        assert.match(await tableText(), /12,400/);
        await page.locator('#tableOptionsPreviewTable button').filter({hasText:'Revenue'}).click();
        assert.deepEqual(await page.locator('#tableOptionsPreviewTable th.tableOptionsSelectedColumn button').allTextContents(), ['Revenue']);
        assert.equal(await page.locator('#tableOptionsPreviewTable button[aria-pressed="true"]').count(), 1);
        await page.locator('#tableColumnFormat').selectOption('currency');
        await page.locator('#tableColumnDecimals').selectOption('2');
        await page.waitForFunction(() => document.getElementById('tableOptionsPreviewTable').textContent.includes('12,400.00'));
        assert.match(await tableText(), /€/);
        await page.locator('#tableColumnTitle').fill('Net revenue');
        await page.waitForFunction(() => document.querySelector('#tableOptionsPreviewTable thead').textContent.includes('Net revenue'));
        const raw = await page.evaluate(() => OCA.Analytics.TableOptions.active.preview.column(2).data().toArray());
        assert.deepEqual(raw, [12400,16800,8200,10900]);
        await nav('Appearance');
        assert.equal(await page.locator('#tableStriped').isChecked(), true);
        assert.equal(await page.locator('#tableOptionsPreviewTable').evaluate(table => table.classList.contains('stripe')), true);
        const defaultRowShadows = await page.locator('#tableOptionsPreviewTable tbody tr td:last-child').evaluateAll(cells =>
            cells.slice(0, 2).map(cell => getComputedStyle(cell).boxShadow));
        assert.notEqual(defaultRowShadows[0], defaultRowShadows[1]);
        await page.locator('#tableDensity').selectOption('compact');
        await page.waitForFunction(() => document.getElementById('tableOptionsPreviewTable').classList.contains('analyticsTableDense'));
        assert.equal(await page.locator('#tableOptionsPreviewTable').evaluate(table => table.classList.contains('analyticsTableDense')), true);
        await page.locator('.analyticsSwitch[for="tableShowHeader"]').click();
        await page.waitForFunction(() => document.querySelector('#tableOptionsPreviewTable thead').classList.contains('hidden'));
        assert.equal(await page.locator('#tableOptionsPreviewTable thead').evaluate(head => head.classList.contains('hidden')), true);
        await page.locator('.analyticsSwitch[for="tableStriped"]').click();
        await page.waitForFunction(() => !document.getElementById('tableOptionsPreviewTable').classList.contains('stripe'));
        assert.equal(await page.evaluate(() => OCA.Analytics.TableOptions.read().striped), false);
        const disabledRowShadows = await page.locator('#tableOptionsPreviewTable tbody tr td:last-child').evaluateAll(cells =>
            cells.slice(0, 2).map(cell => getComputedStyle(cell).boxShadow));
        assert.equal(disabledRowShadows[0], disabledRowShadows[1]);
        await page.locator('#tablePageLength').selectOption('25');
        await page.waitForTimeout(200);
        assert.equal(await page.evaluate(() => OCA.Analytics.TableOptions.read().length), 25);
        assert.equal(await page.locator('#tableOptionsPreviewTable tbody tr').count(), 4);
        await page.locator('label[for="totalOption"]').click();
        await page.waitForFunction(() => document.querySelector('#tableOptionsPreviewTable tfoot').textContent.includes('48,300'));
        await page.locator('label[for="formatLocalesOption"]').click();
        await page.waitForTimeout(200);
        assert.equal(await page.evaluate(() => OCA.Analytics.TableOptions.read().formatLocales), false);
        assert.equal(await page.locator('#analyticsDialogContainer').evaluate(dialog => dialog.scrollTop), 0);
        await page.locator('#analyticsDialogBtnCancel').focus();
        assert.equal(await page.locator('#analyticsDialogContainer').evaluate(dialog => dialog.scrollTop), 0);
        await nav('Highlighting');
        await page.locator('#tableHighlightBelow').fill('10000');
        await page.locator('#tableOptionsPreviewTable .analyticsTableHighlight').waitFor();
        assert.match(await page.locator('#tableOptionsPreviewTable .analyticsTableHighlight').innerText(), /8,200/);
        assert.equal(await page.evaluate(() => JSON.stringify(OCA.Analytics.currentReportData) === window.tableBefore && !OCA.Analytics.unsavedChanges), true);
        assert.deepEqual(await page.evaluate(() => Object.keys(OCA.Analytics.tableObject)), await page.evaluate(() => window.tableRegistryBefore));
        await capture('table_options_preview');
        await page.locator('#analyticsDialogBtnCancel').click();
        assert.equal(await page.evaluate(() => DataTable.settings.length === window.tableInstancesBefore), true);
        assert.equal(await page.evaluate(() => JSON.stringify(OCA.Analytics.currentReportData) === window.tableBefore), true);

        // Raw numeric strings retain decimal precision; text values and labels stay escaped.
        await open({header:['Name','Value'],data:[['<img src=x onerror="window.tableXss=1">','1.2345'],['B','2.0001']]});
        await page.locator('#tableOptionsPreviewTable button').filter({hasText:'Value'}).click();
        await page.locator('#tableColumnFormat').selectOption('number');
        await page.locator('#tableColumnDecimals').selectOption('4');
        await page.waitForFunction(() => document.getElementById('tableOptionsPreviewTable').textContent.includes('1.2345'));
        assert.deepEqual(await page.evaluate(() => OCA.Analytics.TableOptions.active.preview.column(1).data().toArray()), [1.2345,2.0001]);
        assert.equal(await page.locator('#tableOptionsPreviewTable img').count(), 0);
        await page.locator('#analyticsDialogBtnLeading').click();
        await page.waitForTimeout(200);
        assert.equal(await page.locator('#tableStriped').isChecked(), true);
        assert.equal(await page.locator('#tableOptionsPreviewTable').evaluate(table => table.classList.contains('stripe')), true);
        assert.equal(await page.evaluate(() => JSON.stringify(OCA.Analytics.currentReportData) === window.tableBefore), true);
        await page.locator('#analyticsDialogBtnCancel').click();

        // Empty responses retain headers and editable settings.
        await open({data:[]});
        assert.match(await page.locator('#tableOptionsPreviewError').innerText(), /No data/);
        assert.equal(await page.locator('#tableColumnSelect option').count(), 4);

        // Keep the preview at seven rows regardless of the report page length,
        // while calculating totals from the complete data set.
        await open({header:['Name','Value'],data:Array.from({length:50},(_,i)=>['Row '+i,i+1]),
            options:{id:999999,chart:'column',tableoptions:{footer:true},filteroptions:{}}});
        assert.match(await page.locator('#tableOptionsPreviewStatus').innerText(), /7 of 50/);
        assert.match(await page.locator('#tableOptionsPreviewTable tfoot').innerText(), /1,275/);
        assert.equal(await page.locator('#tableOptionsPreviewTable tbody tr').count(), 7);
        const previewOverflow = await page.locator('.tableOptionsPreview').evaluate(element => ({
            clientHeight: element.clientHeight,
            scrollHeight: element.scrollHeight,
        }));
        assert.ok(previewOverflow.scrollHeight <= previewOverflow.clientHeight,
            `Seven-row preview should not scroll vertically: ${JSON.stringify(previewOverflow)}`);
        await page.locator('#tablePageLength').selectOption('25');
        await page.waitForTimeout(200);
        assert.equal(await page.evaluate(() => OCA.Analytics.TableOptions.read().length), 25);
        assert.match(await page.locator('#tableOptionsPreviewStatus').innerText(), /7 of 50/);
        assert.equal(await page.locator('#tableOptionsPreviewTable tbody tr').count(), 7);

        const calculated = JSON.stringify({version:2,operation:'percentage',references:['source:2:Cost','source:1:Revenue'],title:'Ratio'});
        await open({header:['Name','Revenue','Cost'],data:[['A',100,25],['B',200,100]],
            options:{id:999999,chart:'column',tableoptions:{footer:true,calculatedColumns:calculated,colReorder:{order:[0,2,1,3]},order:[[1,'desc']]},filteroptions:{}}});
        assert.deepEqual(await page.locator('#tableOptionsPreviewTable thead button').allTextContents(), ['Name','Cost','Revenue','Ratio']);
        await page.waitForFunction(() => document.querySelector('#tableOptionsPreviewTable tbody tr td').textContent === 'B');
        await page.locator('#tableOptionsPreviewTable button').filter({hasText:'Ratio'}).click();
        await page.locator('#tableColumnFormat').selectOption('percent');
        await page.locator('#tableColumnDecimals').selectOption('2');
        await page.locator('#tableColumnTitle').fill('Cost share');
        await page.waitForFunction(() => document.querySelector('#tableOptionsPreviewTable thead').textContent.includes('Cost share'));
        assert.match(await page.locator('#tableOptionsPreviewTable tfoot').innerText(), /41.67\s*%/);
        assert.match(await page.locator('#tableOptionsPreviewTable tbody').innerText(), /25.00\s*%/);
        assert.equal(await page.locator('#tableDefaultSort').count(), 0);
        assert.equal(await page.locator('#tableSortDirection').count(), 0);
        assert.deepEqual(await page.evaluate(() => OCA.Analytics.TableOptions.read().order), [[1,'desc']]);

        // Duplicates must not silently become a last-value pivot.
        await open({data:[['A','Q1',10,2],['A','Q1',20,3]]});
        await page.locator('label[for="tableModePivot"]').click();
        await page.locator('#tableOptionsLayoutError').waitFor({state:'visible'});
        assert.match(await page.locator('#tableOptionsLayoutError').innerText(), /same pivot cell/);

        await open();
        await page.locator('label[for="tableModePivot"]').click();
        await page.waitForFunction(() => OCA.Analytics.TableOptions.active?.preview?.columns().count() === 3);
        const pivotLayout = await page.locator('.tableOptionsLayout').evaluate(layout => {
            const actions = ['#rows', '#columns', '#measures'].map(selector => {
                const item = layout.querySelector(selector + ' .tableOptionsLayoutItem');
                const action = item.querySelector('.tableOptionsFieldActions');
                return item.getBoundingClientRect().right - action.getBoundingClientRect().right;
            });
            return {rowOffset: layout.querySelector('#tableOptionsLayoutRows').getBoundingClientRect().top - layout.getBoundingClientRect().top, actions};
        });
        assert.ok(pivotLayout.rowOffset >= 8, `Rows needs space above it, got ${pivotLayout.rowOffset}px`);
        assert.ok(pivotLayout.actions.every(offset => offset <= 24), `Pivot actions are not right-aligned: ${pivotLayout.actions.join(', ')}`);
        const placeholdersMatchFields = () => page.waitForFunction(() =>
            [...document.querySelectorAll('#analyticsDialogContainer .columnSection')].every(section =>
                section.querySelectorAll('.dragAndDropPlaceholder').length === (section.querySelector('.draggable') ? 0 : 1)));
        await placeholdersMatchFields();
        assert.equal(await page.locator('.tableOptionsFieldActions select').count(), 0);
        await page.locator('#columns #column-1').dragTo(page.locator('#notRequired'));
        await page.locator('#columns .dragAndDropPlaceholder').waitFor({state:'visible'});
        await page.locator('#notRequired #column-1').dragTo(page.locator('#columns'));
        await placeholdersMatchFields();
        await settle();
        assert.match(await tableText(), /Q1/);
        await page.locator('#notRequired #column-2').dragTo(page.locator('#measures'));
        await page.locator('#tableOptionsLayoutError').waitFor({state:'visible'});
        assert.match(await page.locator('#tableOptionsLayoutError').innerText(), /exactly one/);
        await page.locator('#analyticsDialogBtnGo').click();
        assert.equal(await page.locator('#analyticsDialogContainer').isVisible(), true);
        await page.locator('#measures #column-3').dragTo(page.locator('#notRequired'));
        await settle();
        assert.match(await tableText(), /12,400/);

        // Stable references survive response reorder/rename and Apply leaves the registry alone.
        const format = {reference:'source-ref:c_12',format:'currency',currency:'EUR',decimals:'2',title:'Sales'};
        await open({header:['Amount renamed','Region'],columnRefs:['c_12','c_11'],data:[[12.25,'A']],
            options:{id:999999,chart:'column',tableoptions:{columnFormats:[format]},filteroptions:{}}});
        assert.match(await tableText(), /Sales/);
        assert.match(await tableText(), /12.25/);
        await page.evaluate(() => {
            window.tableReload = OCA.Analytics.Report.Backend.getData;
            OCA.Analytics.Report.Backend.getData = () => {window.tableApplied=true;};
        });
        await page.locator('#analyticsDialogBtnGo').click();
        assert.equal(await page.evaluate(() => window.tableApplied), true);
        assert.deepEqual(await page.evaluate(() => OCA.Analytics.currentReportData.options.tableoptions.columnFormats), [format]);
        assert.equal(await page.evaluate(() => DataTable.settings.length === window.tableInstancesBefore), true);
        await page.evaluate(() => {OCA.Analytics.Report.Backend.getData=window.tableReload;});

        await open();
        await page.setViewportSize({width:760,height:1000});
        await page.waitForTimeout(200);
        assert.equal(await page.locator('#analyticsDialogContainer').evaluate(el=>el.scrollWidth>el.clientWidth), false);
        await capture('table_options_narrow');
        await page.evaluate(() => OCA.Analytics.Notification.htmlDialogInitiate('Other dialog', () => {}));
        assert.equal(await page.evaluate(() => OCA.Analytics.TableOptions.active === null && DataTable.settings.length === window.tableInstancesBefore), true);
    } catch (error) {
        await capture('failure');
        throw error;
    } finally {
        await browser.close();
    }
    assert.equal(issues.length, 0, JSON.stringify(issues));
    console.log(JSON.stringify({
        scriptId: '49', status: 'PASS', baseUrl: config.baseUrl, issues,
        steps: ['live preview', 'raw numeric formatting', 'totals and calculated percentages',
            'highlighting', 'pivot validation', 'stable references', 'saved column order and sorting',
            'Apply/Cancel and reset', 'escaping', 'responsive layout and cleanup'],
    }, null, 2));
})();
