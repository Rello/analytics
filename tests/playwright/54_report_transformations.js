/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {
  buildScenarioConfig,
  buildUniqueName,
  ensureAnalyticsLoaded,
  ensureStoredReportWithDefaultData,
  openOptionsMenuItem,
  saveAndReloadReport,
  attachPageIssueListeners,
  createCapture,
} = require('./common');

const config = buildScenarioConfig('54');
const reportName = buildUniqueName('Playwright Transformations', process.env.REPORT_NAME);

(async () => {
  const browser = await chromium.launch({headless: config.headless,
    args: new URL(config.baseUrl).hostname === 'localhost' ? ['--host-resolver-rules=MAP localhost host.docker.internal'] : [],
  });
  const page = await browser.newPage({viewport: config.viewport});
  const issues = [];
  const capture = createCapture(page, config);
  attachPageIssueListeners(page, issues);
  try {
    await ensureAnalyticsLoaded(page, config);
    await ensureStoredReportWithDefaultData(page, reportName, 'Calculated report measures');
    const baselineReport = await page.evaluate(() => structuredClone(OCA.Analytics.currentReportData));
    // A single sort uses the returned column index after excluded dimensions are removed.
    await openOptionsMenuItem(page, 'optionsMenuColumnSelection', 'grouped sorting');
    await page.locator('#drilldownColumn0').uncheck();
    await page.locator('.analyticsTransformColumnRow[data-ref="value"] .transformSort').selectOption('DESC');
    await page.locator('#analyticsDialogBtnGo').click();
    await page.waitForFunction(() => OCA.Analytics.currentReportData.header.length === 2);
    const grouped = await page.evaluate(() => OCA.Analytics.currentReportData);
    const amounts = grouped.data.map(row => Number(row[1]));
    assert.ok(amounts.length > 1);
    assert.deepEqual(amounts, [...amounts].sort((a, b) => b - a));
    await openOptionsMenuItem(page, 'optionsMenuColumnSelection', 'saved grouped sort');
    assert.equal(await page.locator('.analyticsTransformColumnRow[data-ref="value"] .transformSort').inputValue(), 'DESC');
    await page.locator('#analyticsDialogBtnCancel').click();
    await page.evaluate(baseline => {
      OCA.Analytics.currentReportData = baseline;
      OCA.Analytics.Report.Backend.getData();
    }, baselineReport);
    await page.waitForFunction(() => OCA.Analytics.currentReportData.header.length === 3);
    await openOptionsMenuItem(page, 'optionsMenuColumnSelection', 'columns');
    assert.equal(await page.locator('#drilldownAggregate').count(), 0);
    const valueAggregation = page.locator('.analyticsTransformColumnRow[data-ref="value"] .transformAggregation');
    const previewData = async () => {
      const responsePromise = page.waitForResponse(response => response.url().includes('/analytics/data/')
        && response.request().method() === 'GET');
      await page.locator('#transformPreviewButton').click();
      const result = await (await responsePromise).json();
      await page.locator('#transformPreviewButton').waitFor({state: 'visible'});
      await page.waitForFunction(() => !document.getElementById('transformPreviewButton').disabled);
      assert.equal(Number(result.error), 0);
      return result;
    };
    await valueAggregation.selectOption('none');
    const originalRows = await previewData();
    assert.equal(originalRows.queryProcessing.aggregation, false);
    assert.equal(originalRows.data.length, baselineReport.data.length);
    const comparableRows = rows => rows.map(row => JSON.stringify([row[0], row[1], Number(row[2])])).sort();
    assert.deepEqual(comparableRows(originalRows.data), comparableRows(baselineReport.data));
    await page.locator('#drilldownColumn0').uncheck();
    await page.locator('#drilldownColumn1').uncheck();
    await valueAggregation.selectOption('count');
    assert.deepEqual((await previewData()).data, [[baselineReport.data.length]]);
    await valueAggregation.selectOption('count_distinct');
    assert.deepEqual((await previewData()).data, [[new Set(baselineReport.data.map(row => Number(row[2]))).size]]);
    await valueAggregation.selectOption('sum');
    await page.locator('#drilldownColumn0').check();
    await page.locator('#drilldownColumn1').check();
    await page.locator('#drilldownColumn0').click();
    await page.locator('.analyticsTransformColumnRow[data-ref="value"] .transformInclude').uncheck();

    await page.locator('#transformAddCalculation').click();
    const amount = page.locator('.analyticsTransformCalculation').first();
    const amountRef = await amount.getAttribute('data-ref');
    await amount.locator('.transformCalculationName').fill('Double');
    await amount.locator('.transformCalculationFormula').fill('{value} * 2');
    await amount.locator('.transformPhase').selectOption('before');
    await amount.locator('.transformSort').selectOption('DESC');
    assert.equal(await amount.locator('.transformPriority').isHidden(), true);
    if (await page.locator('.analyticsTransformColumnsHeader span:last-child').count()) {
      assert.equal(await page.locator('.analyticsTransformColumnsHeader span:last-child').isHidden(), true);
    }

    await page.locator('#transformAddCalculation').click();
    const ratio = page.locator('.analyticsTransformCalculation').last();
    await ratio.locator('.transformCalculationName').fill('Ratio');
    await ratio.locator('.transformCalculationFormula').fill('{value} / ');
    await ratio.locator('.analyticsTransformColumnChips button').filter({hasText: /^Double$/}).click();
    assert.equal(await ratio.locator('.transformCalculationFormula').inputValue(), '{value} / [Double]');
    await ratio.locator('.transformCalculationFormula').fill('{value} / ');
    await ratio.evaluate(row => {
      const transfer = new DataTransfer();
      const chip = [...row.querySelectorAll('.analyticsTransformColumnChips button')].find(item => item.textContent === 'Double');
      chip.dispatchEvent(new DragEvent('dragstart', {dataTransfer: transfer, bubbles: true}));
      row.querySelector('.transformCalculationFormula').dispatchEvent(new DragEvent('drop', {dataTransfer: transfer, bubbles: true, cancelable: true}));
    });
    assert.equal(await ratio.locator('.transformCalculationFormula').inputValue(), '{value} / [Double]');
    await ratio.locator('.transformPhase').selectOption('after');
    await page.locator('#transformPreviewButton').click();
    await page.locator('#transformPreviewStatus').filter({hasText: 'Showing up to 10 result rows.'}).waitFor();
    assert.equal(await page.locator('#transformPreviewResult th').allTextContents().then(items => items.join('|')),
      'Column 2|Double|Ratio');
    assert.equal(await page.evaluate(() => !!OCA.Analytics.currentReportData.options.filteroptions.transformations), false);
    await capture('columns_dialog');
    await page.locator('#analyticsDialogBtnGo').click();
    await page.waitForFunction(() => OCA.Analytics.currentReportData?.header?.includes('Ratio'));

    const report = await page.evaluate(() => ({
      header: OCA.Analytics.currentReportData.header,
      data: OCA.Analytics.currentReportData.data,
      options: OCA.Analytics.currentReportData.options.filteroptions,
    }));
    assert.deepEqual(report.header, ['Column 2', 'Double', 'Ratio']);
    assert.equal(report.options.transformations.calculations.length, 2);
    assert.equal(report.data[0][0], 'Dimension 2');
    assert.ok(Math.abs(Number(report.data[0][1]) - 12.2) < 0.00001);
    assert.ok(Math.abs(Number(report.data[0][2]) - 0.5) < 0.00001);
    const chartData = await page.evaluate(() => {
      const chart = Chart.getChart(document.querySelector('#myChart'));
      return chart ? {labels: chart.data.labels, datasets: chart.data.datasets.map(item => item.label)} : null;
    });
    assert.ok(chartData, 'Expected a rendered report chart');
    assert.equal(chartData.labels[0], 'Dimension 2');
    assert.ok(chartData.datasets.some(label => String(label).includes('Double')));
    assert.ok(chartData.datasets.some(label => String(label).includes('Ratio')));
    const oldPositionalMapping = await page.evaluate(() => OCA.Analytics.ChartOptions.columnMapping(
      OCA.Analytics.currentReportData, 'kpiModel', {category: 0, series: [], measures: [2]}));
    assert.equal(oldPositionalMapping.category, 'dimension2');
    assert.equal(oldPositionalMapping.measures[0], report.options.transformations.calculations[1].id);
    await capture('calculated_measures');

    await saveAndReloadReport(page, reportName);
    await page.waitForFunction(() => OCA.Analytics.currentReportData?.header?.includes('Ratio'));
    const persisted = await page.evaluate(() => OCA.Analytics.currentReportData.options.filteroptions.transformations);
    assert.equal(persisted.calculations[0].name, 'Double');
    assert.equal(persisted.sort[0].column, amountRef);

    for (const [menu, select, value, key] of [
      ['optionsMenuTopN', '#groupOptionType', 'top', 'topN'],
      ['optionsMenuTimeAggregation', '#timeGroupingGrouping', 'month', 'timeAggregation'],
    ]) {
      await openOptionsMenuItem(page, menu, key);
      await page.locator(select).selectOption(value);
      await page.locator('#analyticsDialogBtnGo').click();
      assert.equal(await page.locator('#analyticsDialogContainer').isVisible(), true);
      assert.equal(await page.evaluate(key => OCA.Analytics.currentReportData.options.filteroptions[key], key), undefined);
      await page.getByText('Remove calculated measures and other column transformations before using Top N or time aggregation.', {exact: true}).first().waitFor();
      await page.locator('#analyticsDialogBtnCancel').click();
    }

    // Existing invalid settings and empty Top N responses must still render a recoverable state.
    const emptyResults = await page.evaluate(baseline => {
      const filters = {topN: {type: 'top', dimension: 0, number: 1}};
      const empty = {...baseline, data: [], options: {...baseline.options, filteroptions: filters}};
      const error = OCA.Analytics.Report.Backend.processReceivedData({...empty, error: 'Calculated report measures cannot yet be combined with Top N or time aggregation.'});
      OCA.Analytics.currentReportData = error;
      OCA.Analytics.Report.buildReport();
      return {error: error.error, empty: OCA.Analytics.Visualization.applyTopN(empty).data};
    }, baselineReport);
    assert.deepEqual(emptyResults.empty, []);
    assert.ok(await page.locator('#noDataContainer').isVisible());
    assert.ok((await page.locator('#noDataContainer').innerText()).includes(emptyResults.error));

    // Move an existing row-layout table calculation without saving this fixture.
    await page.evaluate((baseline) => {
      OCA.Analytics.currentReportData = baseline;
      OCA.Analytics.currentReportData.options.tableoptions = {
        calculatedColumns: JSON.stringify({operation: 'formula', expression: 'column3 * 2', title: 'Legacy double'}),
        layout: {rows: [0, 1, 2], columns: [], measures: []},
        columnFormats: [{reference: 'calculation:0', format: 'currency', currency: 'EUR'}],
      };
      window.originalTransformReload = OCA.Analytics.Report.Backend.getData;
      OCA.Analytics.Report.Backend.getData = () => {};
    }, baselineReport);
    await openOptionsMenuItem(page, 'optionsMenuColumnSelection', 'columns migration');
    await page.locator('#transformImportTableCalculations').click();
    assert.equal(await page.locator('.analyticsTransformCalculation').count(), 1);
    await page.locator('#analyticsDialogBtnGo').click();
    const migrated = await page.evaluate(() => ({
      calculations: OCA.Analytics.currentReportData.options.filteroptions.transformations.calculations,
      table: OCA.Analytics.currentReportData.options.tableoptions.calculatedColumns,
      layout: OCA.Analytics.currentReportData.options.tableoptions.layout,
      format: OCA.Analytics.currentReportData.options.tableoptions.columnFormats[0],
    }));
    assert.equal(migrated.calculations[0].name, 'Legacy double');
    assert.equal(migrated.calculations[0].expression, '{value} * 2');
    assert.equal(migrated.table, undefined);
    assert.deepEqual(migrated.layout.rows, ['source:0', 'source:1', 'source:2', 'calc:legacy_0']);
    assert.equal(migrated.format.reference, 'source-ref:calc:legacy_0');
    await page.evaluate(() => { OCA.Analytics.Report.Backend.getData = window.originalTransformReload; });
    assert.equal(issues.length, 0, JSON.stringify(issues));
    console.log(JSON.stringify({scriptId: config.scriptId, status: 'PASS', reportName}));
  } catch (error) {
    await capture('failure_state').catch(() => {});
    console.log(JSON.stringify({scriptId: config.scriptId, status: 'FAIL', reportName, error: error.message, issues}));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
