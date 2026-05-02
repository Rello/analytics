/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const { chromium } = require('playwright');
const {
  assertSimulationData,
  attachPageIssueListeners,
  buildScenarioConfig,
  buildUniqueName,
  chooseLocalFile,
  clickFirst,
  closeAnalyticsDialog,
  createCapture,
  createDataloadEntry,
  deleteDataloadEntryIfExists,
  ensureAnalyticsLoaded,
  ensureStoredReportWithDefaultData,
  fillRequired,
  openReportAutomationTab,
  openDataloadEntry,
  selectDataloadSource,
  waitForToastAndDismiss,
} = require('./common');

const config = buildScenarioConfig('44');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);
const dataloadName = 'Test Dataload';
const customColumnValues = ['Solar', 'Manual month'];

(async () => {
  const browser = await chromium.launch({ headless: config.headless });
  const page = await browser.newPage({ viewport: config.viewport });
  const issues = [];
  const steps = [];
  const capture = createCapture(page, config);

  attachPageIssueListeners(page, issues);

  try {
    steps.push('open analytics');
    await ensureAnalyticsLoaded(page, config);

    steps.push('prepare stored dataset report');
    await ensureStoredReportWithDefaultData(page, reportName, 'Automation datasource regression');

    steps.push('open automation tab');
    await openReportAutomationTab(page, reportName);
    await deleteDataloadEntryIfExists(page, dataloadName);

    steps.push('create local csv dataload');
    await selectDataloadSource(page, 'Local: csv');
    await createDataloadEntry(page);
    await fillRequired(page, '#dataloadName', dataloadName, 'dataload name');
    await clickFirst(page, ['#link'], 'local file picker');
    await chooseLocalFile(page, 'analytics_testdatacsv.csv');
    await fillRequired(page, '#offset', '1', 'csv offset');

    steps.push('configure column picker');
    await clickFirst(page, ['#columns'], 'columns dialog');
    await page.locator('.analyticsDialogHeader, #analyticsDialogContainer h2').filter({ hasText: 'Column Picker' }).first()
      .waitFor({ state: 'visible', timeout: 10000 });
    await page.locator('[id="1"]').first().check();
    for (const value of customColumnValues) {
      await clickFirst(page, ['#addColumnButton'], 'add custom column');
      const customEditor = page.locator('#sortable-list > li').filter({ hasText: 'Custom Column' }).locator('span[contenteditable]').last();
      await customEditor.fill(value);
    }
    await clickFirst(page, ['#analyticsDialogBtnGo', '.oc-dialog-buttonrow > .primary'], 'column picker confirm');

    const columnsValue = await page.locator('#columns').first().inputValue();
    if (columnsValue !== `1,${customColumnValues.join(',')}`) {
      throw new Error(`Expected selected columns "1,${customColumnValues.join(',')}", got "${columnsValue}"`);
    }

    await capture('dataload_csv_column_picker');

    steps.push('update local csv dataload');
    await clickFirst(page, ['#dataloadUpdateButton'], 'update dataload');
    await waitForToastAndDismiss(page);

    steps.push('reopen custom columns in column picker');
    await openReportAutomationTab(page, reportName);
    await openDataloadEntry(page, dataloadName);
    await clickFirst(page, ['#columns'], 'columns dialog with custom columns');
    await page.locator('.analyticsDialogHeader, #analyticsDialogContainer h2').filter({ hasText: 'Column Picker' }).first()
      .waitFor({ state: 'visible', timeout: 10000 });

    const customRows = page.locator('#sortable-list > li').filter({ hasText: 'Custom Column' });
    await customRows.first().waitFor({ state: 'visible', timeout: 10000 });
    const customRowCount = await customRows.count();
    if (customRowCount !== customColumnValues.length) {
      throw new Error(`Expected ${customColumnValues.length} custom column rows, got ${customRowCount}`);
    }
    for (let index = 0; index < customColumnValues.length; index += 1) {
      const row = customRows.nth(index);
      const editor = row.locator('span[contenteditable]').first();
      const editorText = await editor.textContent();
      if (editorText !== customColumnValues[index]) {
        throw new Error(`Expected custom column value "${customColumnValues[index]}", got "${editorText}"`);
      }
      const backgroundColor = await row.evaluate((node) => getComputedStyle(node).backgroundColor);
      const expectedColor = await row.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-warning-hover').trim());
      const expectedColorProbe = await page.evaluate((color) => {
        const probe = document.createElement('div');
        probe.style.backgroundColor = color;
        document.body.appendChild(probe);
        const computedColor = getComputedStyle(probe).backgroundColor;
        probe.remove();
        return computedColor;
      }, expectedColor);
      if (backgroundColor !== expectedColorProbe) {
        throw new Error(`Expected custom column warning background "${expectedColorProbe}", got "${backgroundColor}"`);
      }
    }
    const obsoleteHintIdCount = await page.locator('#addColumnHint').count();
    const obsoleteHintTextCount = await page.getByText(/Previous Values|Vorherige Werte/).count();
    if (obsoleteHintIdCount !== 0 || obsoleteHintTextCount !== 0) {
      throw new Error('Previous Values hint should not be shown for editable custom columns');
    }
    await capture('dataload_csv_column_picker_custom_columns');
    await customRows.first().locator('span[contenteditable]').first().fill('Solar edited');
    await clickFirst(page, ['#analyticsDialogBtnGo', '.oc-dialog-buttonrow > .primary'], 'column picker custom confirm');
    const editedColumnsValue = await page.locator('#columns').first().inputValue();
    if (editedColumnsValue !== '1,Solar edited,Manual month') {
      throw new Error(`Expected edited custom columns "1,Solar edited,Manual month", got "${editedColumnsValue}"`);
    }
    await clickFirst(page, ['#dataloadUpdateButton'], 'update edited custom dataload');
    await waitForToastAndDismiss(page);

    steps.push('execute local csv dataload');
    await clickFirst(page, ['#dataloadExecuteButton'], 'execute dataload');
    await assertSimulationData(page, '[["2022-09-06","Solar edited","Manual month"],["2022-09-07","Solar edited","Manual month"]]');
    await closeAnalyticsDialog(page);

    steps.push('persist schedule and reopen entry');
    await page.locator('#dataloadSchedule').first().selectOption({ label: 'Daily' });
    await waitForToastAndDismiss(page);
    await openReportAutomationTab(page, reportName);
    await openDataloadEntry(page, dataloadName);

    steps.push('cleanup local csv dataload');
    await deleteDataloadEntryIfExists(page, dataloadName);

    const result = {
      scriptId: config.scriptId,
      status: issues.length ? 'WARN' : 'PASS',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      reportName,
      steps,
      issues,
    };
    console.log(JSON.stringify(result, null, 2));
    if (result.status !== 'PASS') {
      process.exitCode = 1;
    }
  } catch (error) {
    const result = {
      scriptId: config.scriptId,
      status: 'FAIL',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      reportName,
      steps,
      issues: issues.concat([`fatal:${error.message}`]),
    };
    await capture('failure_state').catch(() => {});
    console.log(JSON.stringify(result, null, 2));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
