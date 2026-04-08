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
    await page.locator('[id="2"]').first().check();
    await page.locator('[id="4"]').first().check();
    await page.locator('#sortable-list > li:nth-child(2)').dragTo(page.locator('#sortable-list > li:nth-child(1)'));
    await clickFirst(page, ['#analyticsDialogBtnGo', '.oc-dialog-buttonrow > .primary'], 'column picker confirm');

    const columnsValue = await page.locator('#columns').first().inputValue();
    if (columnsValue !== '2,1,4') {
      throw new Error(`Expected selected columns "2,1,4", got "${columnsValue}"`);
    }

    await capture('dataload_csv_column_picker');

    steps.push('update and execute local csv dataload');
    await clickFirst(page, ['#dataloadUpdateButton'], 'update dataload');
    await waitForToastAndDismiss(page);
    await clickFirst(page, ['#dataloadExecuteButton'], 'execute dataload');
    await assertSimulationData(page, '[["import 44","2022-09-06","44b"],["import 55","2022-09-07","55c"]]');
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
