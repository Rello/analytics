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
  deleteDataloadEntryIfExists,
  ensureAnalyticsLoaded,
  ensureStoredReportWithDefaultData,
  fillRequired,
  openReportAutomationTab,
  selectDataloadSource,
  waitForToastAndDismiss,
} = require('./common');

const config = buildScenarioConfig('42');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);
const dataloadName = 'json';

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

    steps.push('create json dataload');
    await selectDataloadSource(page, 'External: JSON');
    await createDataloadEntry(page);
    await fillRequired(page, '#dataloadName', dataloadName, 'dataload name');
    await fillRequired(page, '#url', 'https://raw.githubusercontent.com/Rello/analytics/master/sample_data/sample_json.json', 'json url');
    await fillRequired(page, '#path', 'json testdata', 'json path');
    await page.locator('#optionSectionHeader').first().waitFor({ state: 'visible', timeout: 10000 });
    await page.locator('#optionSectionHeader').first().click();
    await page.locator('#timestamp').first().waitFor({ state: 'visible', timeout: 10000 });
    await page.locator('#timestamp').first().selectOption({ value: 'false' });
    await clickFirst(page, ['#dataloadUpdateButton'], 'update dataload');
    await waitForToastAndDismiss(page);

    steps.push('execute json dataload simulation');
    await clickFirst(page, ['#dataloadExecuteButton'], 'execute dataload');
    await assertSimulationData(page, '[["json testdata","test 99",99],["json testdata","test 88",88],["json testdata","test 77",77]]');

    await capture('datasource_json');

    await closeAnalyticsDialog(page);

    steps.push('cleanup json dataload');
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
