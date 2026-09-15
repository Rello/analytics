/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const {
  attachPageIssueListeners,
  buildScenarioConfig,
  buildUniqueName,
  clickFirst,
  createCapture,
  ensureAnalyticsLoaded,
} = require('./common');

const config = buildScenarioConfig('46');
const datasetName = buildUniqueName('Flexible sales');
const legacyName = buildUniqueName('Classic alongside flexible');
const reportName = buildUniqueName('Flexible sales report');

(async () => {
  const browser = await chromium.launch({headless: config.headless});
  const page = await browser.newPage({viewport: config.viewport});
  const issues = [];
  const steps = [];
  const capture = createCapture(page, config);
  let datasetId = null;
  let legacyId = null;

  attachPageIssueListeners(page, issues);

  const api = (path, method = 'GET', body) => page.evaluate(async ({path, method, body}) => {
    const response = await fetch(OC.generateUrl('apps/analytics/' + path), {
      method,
      headers: OCA.Analytics.headers(),
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => null);
    if (!response.ok) {
      throw new Error(data?.error?.message || data?.message || `HTTP ${response.status}`);
    }
    return {status: response.status, data};
  }, {path, method, body});

  try {
    steps.push('open analytics and create a classic dataset alongside the flexible one');
    await ensureAnalyticsLoaded(page, config);
    legacyId = Number((await api('dataset', 'POST', {
      name: legacyName,
      dimension1: 'Object',
      dimension2: 'Date',
      value: 'Value',
    })).data);
    await api('data/' + legacyId, 'PUT', {
      dimension1: 'North',
      dimension2: '2026-09-01',
      value: '10',
      isDataset: true,
    });

    steps.push('create the flexible schema through the dataset wizard');
    await page.evaluate(() => OCA.Analytics.handlers.create.dataset());
    await page.locator('#wizardDatasetName').waitFor({state: 'visible'});
    await page.locator('#wizardDatasetName').fill(datasetName);
    assert.equal(await page.locator('.flexibleDatasetMode').isVisible(), false);
    await page.locator('input[name="wizardDatasetMode"][value="flexible_shared"]').evaluate(input => {
      input.checked = true;
      input.dispatchEvent(new Event('change', {bubbles: true}));
    });
    const rows = page.locator('#wizardDatasetFlexibleColumnList .flexibleColumnRow');
    await rows.nth(2).locator('.flexibleColumnName').fill('Product');
    await rows.nth(2).locator('.flexibleColumnType').selectOption('text');
    await rows.nth(2).locator('.flexibleColumnRole').selectOption('dimension');
    await page.locator('#wizardDatasetFlexibleAddColumn').click();
    await page.locator('#wizardDatasetFlexibleAddColumn').click();
    await rows.nth(3).locator('.flexibleColumnName').fill('Revenue');
    await rows.nth(4).locator('.flexibleColumnName').fill('Cost');
    const createResponse = page.waitForResponse(response => response.request().method() === 'POST'
      && response.url().endsWith('/apps/analytics/dataset/flexible'));
    await page.locator('#wizardNewCreate').click();
    const created = await (await createResponse).json();
    datasetId = Number(created.id);
    assert.equal(created.storageMode, 'flexible_shared');
    assert.deepEqual(created.columns.map(column => column.name), ['Date', 'Region', 'Product', 'Revenue', 'Cost']);
    await page.waitForFunction(id => Number(OCA.Analytics.currentDataset) === id, datasetId);

    const descriptor = (await api('dataset/' + datasetId)).data;
    const refs = Object.fromEntries(descriptor.columns.map(column => [column.name, column.ref]));

    steps.push('map and import a source with an ignored column twice');
    await page.locator('#tabHeaderData').click();
    await page.locator('#flexibleRecordForm').waitFor({state: 'visible'});
    await page.locator('#dataImportSectionHeaderH3').click();
    await page.locator('#importDataClipboardButton').click();
    const firstImport = [
      'Date,Region,Product,Revenue,Cost,Unused',
      '2026-09-01,Germany,Cloud,100.00,60.00,first',
      '2026-09-01,France,Cloud,50.00,30.00,second',
    ].join('\n');
    await page.locator('#importDataClipboardText').fill(firstImport);
    await page.locator('#flexibleImportMapping select').first().waitFor();
    assert.deepEqual(
      await page.locator('#flexibleImportMapping select').evaluateAll(nodes => nodes.map(node => node.value)),
      ['0', '1', '2', '3', '4']
    );
    assert.match(await page.locator('.flexibleIgnoredColumns').innerText(), /Unused/);
    let importResponse = page.waitForResponse(response => response.request().method() === 'POST'
      && response.url().endsWith('/apps/analytics/data/importCSV'));
    await page.locator('#importDataClipboardButtonGo').click();
    assert.deepEqual(
      (({insert, update, error}) => ({insert, update, error}))(await (await importResponse).json()),
      {insert: 2, update: 0, error: 0}
    );

    const secondImport = [
      'Date,Region,Product,Revenue,Cost,Unused',
      '2026-09-01,Germany,Cloud,120.00,70.00,changed',
      '2026-09-01,France,Cloud,55.00,31.00,changed',
    ].join('\n');
    await page.locator('#importDataClipboardText').fill(secondImport);
    importResponse = page.waitForResponse(response => response.request().method() === 'POST'
      && response.url().endsWith('/apps/analytics/data/importCSV'));
    await page.locator('#importDataClipboardButtonGo').click();
    assert.deepEqual(
      (({insert, update, error}) => ({insert, update, error}))(await (await importResponse).json()),
      {insert: 0, update: 2, error: 0}
    );
    await page.waitForFunction(() => document.querySelector('#flexibleRecordPreview')?.innerText.includes('120'));
    assert.equal(await page.locator('#flexibleRecordPreview tr:has(td)').count(), 2);
    await capture('mapped_records');

    steps.push('create and render a normal report with two measures');
    const reportId = Number((await api('report', 'POST', {
      name: reportName,
      subheader: 'Stable flexible references',
      parent: 0,
      type: 2,
      dataset: datasetId,
      link: '{}',
      visualization: 'ct',
      chart: 'column',
      dimension1: '',
      dimension2: '',
      value: '',
    })).data);
    await page.goto(config.baseUrl + 'r/' + reportId, {waitUntil: 'domcontentloaded'});
    await page.waitForFunction(id => Number(OCA.Analytics.currentReportData?.options?.id) === id
      && OCA.Analytics.currentReportData?.data?.length === 2, reportId);
    assert.deepEqual(await page.evaluate(() => OCA.Analytics.currentReportData.header),
      ['Date', 'Region', 'Product', 'Revenue', 'Cost']);
    assert.deepEqual(await page.evaluate(() => OCA.Analytics.currentReportData.columnRefs),
      [refs.Date, refs.Region, refs.Product, refs.Revenue, refs.Cost]);
    const chartLabels = await page.evaluate(() => OCA.Analytics.chartObject.data.datasets.map(dataset => dataset.label));
    assert.ok(chartLabels.includes('Germany · Cloud · Revenue'));
    assert.ok(chartLabels.includes('Germany · Cloud · Cost'));

    steps.push('use the same three modes and mapping controls for a classic report');
    const legacyReportId = Number((await api('report', 'POST', {
      name: legacyName + ' report',
      subheader: 'Generic chart mapping',
      parent: 0,
      type: 2,
      dataset: legacyId,
      link: '{}',
      visualization: 'ct',
      chart: 'column',
      dimension1: 'Object',
      dimension2: 'Date',
      value: 'Value',
    })).data);
    await page.goto(config.baseUrl + 'r/' + legacyReportId, {waitUntil: 'domcontentloaded'});
    await page.waitForFunction(id => Number(OCA.Analytics.currentReportData?.options?.id) === id, legacyReportId);
    await page.evaluate(() => OCA.Analytics.Filter.openChartOptionsDialog());
    await page.locator('#chartColumnMappingSection').waitFor({state: 'visible'});
    assert.equal(await page.locator('input[name="analyticsModel"]').count(), 3);
    assert.equal(await page.locator('#chartColumnSeries .chartColumnSelection[data-column-id="0"]').count(), 1);
    await page.locator('label[for="analyticsModelOpt2"]').click();
    assert.equal(await page.locator('#chartColumnCategoryLabel').innerText(), 'Series label column');
    await page.locator('#analyticsDialogBtnGo').click();
    await page.waitForFunction(() => !OCA.Analytics.currentXhrRequest
      && OCA.Analytics.ChartOptions.getGuiState(
        OCA.Analytics.currentReportData.options.chartoptions
      ).model === 'accountModel');
    assert.deepEqual(await page.evaluate(() => OCA.Analytics.chartObject.data.datasets.map(dataset => dataset.label)),
      ['North']);
    await page.evaluate(() => OCA.Analytics.Filter.openChartOptionsDialog());
    await page.locator('#chartColumnMappingSection').waitFor({state: 'visible'});
    await page.locator('label[for="analyticsModelOpt3"]').click();
    assert.equal(await page.locator('#chartColumnCategoryLabel').innerText(), 'Time / X-axis');
    await page.locator('#analyticsDialogBtnCancel').click();
    await page.goto(config.baseUrl + 'r/' + reportId, {waitUntil: 'domcontentloaded'});
    await page.waitForFunction(id => Number(OCA.Analytics.currentReportData?.options?.id) === id, reportId);

    steps.push('customize chart-only data mapping without changing report columns');
    await page.evaluate(() => OCA.Analytics.Filter.openChartOptionsDialog());
    await page.locator('#chartColumnMappingSection').waitFor({state: 'visible'});
    assert.equal(await page.locator('#chartDataFormatSection').isVisible(), true);
    assert.equal(await page.locator('#chartColumnMappingStatus').innerText(), 'Automatic chart mapping');
    await page.locator(`#chartColumnSeries .chartColumnSelection[data-column-id="${refs.Product}"] .chartColumnRemove`).click();
    await page.locator(`#chartColumnValues .chartColumnSelection[data-column-id="${refs.Cost}"] .chartColumnRemove`).click();
    await page.locator('#analyticsDialogBtnGo').click();
    await page.waitForFunction(() => !document.getElementById('analytics_dialog_container')
      && !OCA.Analytics.currentXhrRequest
      && OCA.Analytics.chartObject?.data?.datasets?.length > 0);
    assert.deepEqual(await page.evaluate(() => OCA.Analytics.currentReportData.header),
      ['Date', 'Region', 'Product', 'Revenue', 'Cost']);
    assert.deepEqual((await page.evaluate(() => OCA.Analytics.chartObject.data.datasets
      .map(dataset => dataset.label))).sort(), ['France', 'Germany']);

    steps.push('persist a stable table layout and Region filter');
    await page.evaluate(() => OCA.Analytics.Filter.openTableOptionsDialog());
    await page.locator('#analyticsDialogContainer .tableOptionsLayoutItem').first().waitFor();
    assert.equal(await page.locator('#analyticsDialogContainer [data-column-ref]').count(), 5);
    await page.locator('#analyticsDialogBtnGo').click();
    await page.waitForFunction(() => OCA.Analytics.unsavedChanges === true);
    await page.evaluate(() => OCA.Analytics.Filter.openFilterDialog());
    await page.locator('#filterDialogDimension').selectOption(refs.Region);
    await page.locator('#filterDialogValue').fill('Germany');
    await page.locator('#analyticsDialogBtnGo').click();
    await page.waitForFunction(() => OCA.Analytics.currentReportData?.data?.length === 1);
    const saveResponse = page.waitForResponse(response => response.request().method() === 'POST'
      && response.url().endsWith('/apps/analytics/report/' + reportId + '/options'));
    await page.locator('#saveIcon').click();
    await saveResponse;

    steps.push('rename and reorder schema columns without breaking report state');
    const latestDescriptor = (await api('dataset/' + datasetId)).data;
    const byName = Object.fromEntries(latestDescriptor.columns.map(column => [column.name, column]));
    const schemaColumn = (column, name = column.name) => ({
      ref: column.ref,
      name,
      type: column.type,
      role: column.role,
      nullable: column.nullable,
      defaultAggregation: column.defaultAggregation,
    });
    const changedDescriptor = (await api('dataset/' + datasetId + '/schema', 'PUT', {
      expectedSchemaVersion: latestDescriptor.schemaVersion,
      name: datasetName,
      columns: [
        schemaColumn(byName.Revenue, 'Net Revenue'),
        schemaColumn(byName.Cost),
        schemaColumn(byName.Date),
        schemaColumn(byName.Region),
        schemaColumn(byName.Product),
      ],
    })).data;
    assert.equal(changedDescriptor.columns.find(column => column.ref === refs.Revenue).name, 'Net Revenue');
    await page.reload({waitUntil: 'domcontentloaded'});
    await page.waitForFunction(id => Number(OCA.Analytics.currentReportData?.options?.id) === id
      && OCA.Analytics.currentReportData?.data?.length === 1, reportId);
    assert.deepEqual(await page.evaluate(() => OCA.Analytics.currentReportData.header),
      ['Date', 'Region', 'Product', 'Net Revenue', 'Cost']);
    assert.equal(await page.evaluate(ref => OCA.Analytics.currentReportData.options.filteroptions.filter[0].dimension === ref, refs.Region), true);
    assert.equal(await page.evaluate(ref => OCA.Analytics.currentReportData.options.tableoptions.layout.rows.includes(ref), refs.Revenue), true);
    assert.deepEqual(await page.evaluate(() => OCA.Analytics.ChartOptions.getGuiState(
      OCA.Analytics.currentReportData.options.chartoptions
    ).columnMapping), {
      category: refs.Date,
      series: [refs.Region],
      measures: [refs.Revenue],
    });
    assert.equal(await page.evaluate(() => Object.hasOwn(OCA.Analytics.currentReportData.options.dataoptions, 'flexibleQuery')), false);
    assert.deepEqual(await page.evaluate(() => OCA.Analytics.chartObject.data.datasets.map(dataset => dataset.label)),
      ['Germany']);
    await capture('stable_report_after_schema_change');

    const result = {
      scriptId: config.scriptId,
      status: issues.length ? 'WARN' : 'PASS',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      datasetName,
      reportName,
      steps,
      issues,
    };
    console.log(JSON.stringify(result, null, 2));
    if (result.status !== 'PASS') process.exitCode = 1;
  } catch (error) {
    const result = {
      scriptId: config.scriptId,
      status: 'FAIL',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      datasetName,
      reportName,
      steps,
      issues: issues.concat([`fatal:${error.message}`]),
    };
    await capture('failure_state').catch(() => {});
    console.log(JSON.stringify(result, null, 2));
    process.exitCode = 1;
  } finally {
    if (datasetId) await api('dataset/' + datasetId, 'DELETE').catch(() => {});
    if (legacyId) await api('dataset/' + legacyId, 'DELETE').catch(() => {});
    await browser.close();
  }
})();
