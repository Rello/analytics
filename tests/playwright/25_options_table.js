/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const { chromium } = require('playwright');
const {
  buildScenarioConfig,
  buildUniqueName,
  clickFirst,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  ensureStoredReportWithDefaultData,
  openOptionsMenuItem,
  saveAndReloadReport,
} = require('./common');

const config = buildScenarioConfig('25');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function collectTableRows(page) {
  return page.locator('#tableContainer tbody tr').evaluateAll((nodes) =>
    nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim())
  );
}

async function setSwitch(page, selector, checked) {
  await page.locator(selector).evaluate((node, nextChecked) => {
    node.checked = nextChecked;
    node.dispatchEvent(new Event('change', { bubbles: true }));
  }, checked);
}

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

    steps.push('open seeded report');
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Table options regression');
    steps.push(`report ${seedState}`);

    steps.push('configure table options');
    await openOptionsMenuItem(page, 'optionsMenuTableOptions', 'table options');
    await page.locator('#totalOption').waitFor({ state: 'visible', timeout: 15000 });
    const initialTotals = await page.locator('#totalOption').isChecked();
    const initialFormatLocales = await page.locator('#formatLocalesOption').isChecked();
    await setSwitch(page, '#totalOption', true);
    await setSwitch(page, '#formatLocalesOption', false);
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply table options');

    steps.push('save reload and validate table options');
    await saveAndReloadReport(page, reportName);

    await capture('show_totals');

    const footerValue = (await page.locator('#tableContainer tfoot td').last().innerText()).trim();
    if (!footerValue.includes('10.1')) {
      throw new Error(`Expected footer total to contain "10.1", got "${footerValue}"`);
    }

    const rows = await collectTableRows(page);
    if (rows.length < 4) {
      throw new Error(`Expected seeded rows after saved table options, got ${JSON.stringify(rows)}`);
    }
    await openOptionsMenuItem(page, 'optionsMenuTableOptions', 'table options validation');
    if (!(await page.locator('#totalOption').isChecked())) {
      throw new Error('Expected totals option to stay enabled after reload');
    }
    if (await page.locator('#formatLocalesOption').isChecked()) {
      throw new Error('Expected locale formatting to stay disabled after reload');
    }
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'cancel table options validation');

    steps.push('revert table options');
    await openOptionsMenuItem(page, 'optionsMenuTableOptions', 'table options revert');
    await setSwitch(page, '#totalOption', initialTotals);
    await setSwitch(page, '#formatLocalesOption', initialFormatLocales);
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply reverted table options');

    steps.push('save reload and validate original report');
    await saveAndReloadReport(page, reportName);
    if (await page.locator('#tableContainer tfoot td').count()) {
      throw new Error('Expected no footer totals after reverting table options');
    }
    await openOptionsMenuItem(page, 'optionsMenuTableOptions', 'table options reverted validation');
    if ((await page.locator('#totalOption').isChecked()) !== initialTotals) {
      throw new Error('Totals option did not restore to its original state');
    }
    if ((await page.locator('#formatLocalesOption').isChecked()) !== initialFormatLocales) {
      throw new Error('Locale formatting option did not restore to its original state');
    }
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'cancel reverted table options validation');

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
