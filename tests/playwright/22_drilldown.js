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

const config = buildScenarioConfig('22');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Drilldown regression');
    steps.push(`report ${seedState}`);

    steps.push('remove first column through drilldown');
    await openOptionsMenuItem(page, 'optionsMenuColumnSelection', 'column selection');
    const firstDrilldownColumn = page.locator('#drilldownColumn0');
    await firstDrilldownColumn.waitFor({ state: 'visible', timeout: 15000 });
    await firstDrilldownColumn.click({ force: true });
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply drilldown');
    steps.push('save reload and validate drilldown');
    await saveAndReloadReport(page, reportName);

    let rowTexts = await page.locator('#tableContainer tbody tr').evaluateAll((nodes) =>
      nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim())
    );
    if (!rowTexts.some((text) => text.includes('Dimension 2') && /(6,1|6\.1)\b/.test(text))) {
      throw new Error(`Expected aggregated row with "Dimension 2" and "6,1", got ${JSON.stringify(rowTexts)}`);
    }

    await capture('column_removed');

    steps.push('restore removed first column');
    await openOptionsMenuItem(page, 'optionsMenuColumnSelection', 'column selection restore');
    const checked = await page.locator('#drilldownColumn0').isChecked().catch(() => true);
    if (checked) {
      throw new Error('Expected drilldownColumn0 to be unchecked before restoring');
    }
    await page.locator('#drilldownColumn0').click({ force: true });
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply restored drilldown');
    steps.push('save reload and validate original report');
    await saveAndReloadReport(page, reportName);

    const firstHeader = ((await page.locator('#tableContainer thead tr th').nth(0).innerText()).trim());
    const secondHeader = ((await page.locator('#tableContainer thead tr th').nth(1).innerText()).trim());
    if (firstHeader !== 'Column 1' || secondHeader !== 'Column 2') {
      throw new Error(`Expected headers to be restored to "Column 1" and "Column 2", got "${firstHeader}" and "${secondHeader}"`);
    }
    rowTexts = await page.locator('#tableContainer tbody tr').evaluateAll((nodes) =>
      nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim())
    );
    if (!rowTexts.some((text) => text.includes('Dimension 1') && text.includes('Dimension 2'))) {
      throw new Error(`Expected original unaggregated rows after restoring drilldown, got ${JSON.stringify(rowTexts)}`);
    }

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
