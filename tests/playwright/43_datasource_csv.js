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
  clickFirst,
  closeAnalyticsDialog,
  createCapture,
  createDataloadEntry,
  deleteCurrentDataloadEntry,
  deleteDataloadEntryIfExists,
  ensureAnalyticsLoaded,
  ensureStoredReportWithDefaultData,
  fillRequired,
  openReportAutomationTab,
  selectDataloadSource,
} = require('./common');

const config = buildScenarioConfig('43');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);
const dataloadName = 'csv';

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

    steps.push('create external csv dataload');
    await selectDataloadSource(page, 'External: csv');
    await createDataloadEntry(page);
    await fillRequired(page, '#dataloadName', dataloadName, 'dataload name');
    await fillRequired(page, '#link, #url', 'https://raw.githubusercontent.com/Rello/analytics/master/sample_data/sample1.csv', 'csv link');
    await fillRequired(page, '#columns', '1,3', 'csv columns');
    await fillRequired(page, '#offset', '59', 'csv offset');
    await clickFirst(page, ['#dataloadUpdateButton'], 'update dataload');

    steps.push('execute csv dataload simulation');
    await clickFirst(page, ['#dataloadExecuteButton'], 'execute dataload');
    await assertSimulationData(page, '[["Germany",83073100]]');

    await capture('datasource_csv');

    await closeAnalyticsDialog(page);

    steps.push('cleanup csv dataload');
    await deleteCurrentDataloadEntry(page);

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
