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
  createStoredReport,
  openExistingReport,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  openReportBasicSettings,
  fillRequired,
  waitForIdle,
} = require('./common');

const config = buildScenarioConfig('14');
const reportName = buildUniqueName('Playwright Sidebar Data', process.env.REPORT_NAME);

async function addRow(page, dimension1, dimension2, value) {
  await fillRequired(page, '#DataDimension1', dimension1, 'data dimension 1');
  await fillRequired(page, '#DataDimension2', dimension2, 'data dimension 2');
  await fillRequired(page, '#DataValue', value, 'data value');
  await clickFirst(page, ['#updateDataButton'], 'update data button');
  await page.locator('#updateDataButton').evaluate((button) => button.disabled === false).catch(() => {});
  await waitForIdle(page, 15000);
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

    if (process.env.REPORT_NAME) {
      steps.push('open shared report');
      await openExistingReport(page, reportName);
    } else {
      steps.push('create report');
      await createStoredReport(page, reportName, 'Sidebar data regression');
    }

    steps.push('open report sidebar');
    await openReportBasicSettings(page, reportName);
    await clickFirst(page, ['#tabHeaderData', '.tabHeader#tabHeaderData'], 'data tab');
    await page.locator('#DataDimension1').waitFor({ state: 'visible', timeout: 15000 });

    steps.push('insert sidebar data rows');
    await addRow(page, 'Dimension 1', 'Dimension 2', '1.1');
    await addRow(page, 'Dimension 11', 'Threshold Test', '2');
    await addRow(page, 'Year format check', '2024', '2024');
    await addRow(page, 'Locale format check', '1234567', '1');
    await addRow(page, 'komma, string', '123', '1');
    await addRow(page, 'Top N', 'Dimension 2', '5');

    steps.push('verify inserted data in table');
    await page.locator('#reportSubHeader').waitFor({ state: 'visible', timeout: 15000 });
    const rows = page.locator('#tableContainer tbody tr');
    await page.waitForFunction(() => document.querySelectorAll('#tableContainer tbody tr').length >= 6, { timeout: 15000 });

    const rowTexts = await rows.evaluateAll((nodes) =>
      nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim())
    );
    if (!rowTexts.some((text) => text.includes('Dimension 1') && text.includes('Dimension 2') && /(1,1|1\.1)\b/.test(text))) {
      throw new Error(`Inserted base row not found in table: ${JSON.stringify(rowTexts)}`);
    }
    if (!rowTexts.some((text) => text.includes('Year format check') && text.includes('2024') && /(2\.024|2,024)\b/.test(text))) {
      throw new Error(`Formatted year row not found in table: ${JSON.stringify(rowTexts)}`);
    }

    steps.push('delete matching data row');
    await fillRequired(page, '#DataDimension1', '*', 'delete dimension 1');
    await fillRequired(page, '#DataDimension2', '2024', 'delete dimension 2');
    await fillRequired(page, '#DataValue', '*', 'delete value');
    await clickFirst(page, ['#deleteDataButton'], 'delete data button');
    await page.locator('#analyticsDialogHeader').waitFor({ state: 'visible', timeout: 10000 });
    const dialogHeader = (await page.locator('#analyticsDialogHeader').innerText()).trim();
    if (dialogHeader !== 'Delete data') {
      throw new Error(`Unexpected delete dialog header: "${dialogHeader}"`);
    }
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'confirm delete data');
    await waitForIdle(page, 15000);
    await page.locator('#reportSubHeader').waitFor({ state: 'visible', timeout: 15000 });

    const rowsAfterDelete = await page
      .locator('#tableContainer tbody tr')
      .evaluateAll((nodes) => nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim()));
    if (rowsAfterDelete.some((text) => text.includes('Year format check'))) {
      throw new Error(`Deleted row still present after deletion: ${JSON.stringify(rowsAfterDelete)}`);
    }

    await capture('data_added');

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
