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

const config = buildScenarioConfig('27');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function closeToastIfPresent(page) {
  const closeButton = page.locator('.toast-close').first();
  const isVisible = await closeButton.isVisible().catch(() => false);
  if (isVisible) {
    await closeButton.click().catch(() => {});
  }
}

async function waitForRefreshSelection(page, selector) {
  await page.waitForFunction((targetSelector) => {
    const field = document.querySelector(targetSelector);
    return !!field && field.checked === true;
  }, selector, { timeout: 15000 });
}

async function openRefreshMenu(page, label) {
  if (await page.locator('#refresh0').isVisible().catch(() => false)) {
    return;
  }
  await openOptionsMenuItem(page, 'optionsMenuRefresh', label);
  await page.locator('#refresh0').waitFor({ state: 'visible', timeout: 15000 });
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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Refresh regression');
    steps.push(`report ${seedState}`);

    steps.push('set auto refresh to 1 minute');
    await openRefreshMenu(page, 'refresh menu');
    await page.locator('label[for="refresh1"]').click();
    await waitForRefreshSelection(page, '#refresh1');
    await closeToastIfPresent(page);

    steps.push('reload and validate refresh option');
    await saveAndReloadReport(page, reportName, { clickSave: false });
    await openRefreshMenu(page, 'refresh menu validation');
    if (!(await page.locator('#refresh1').isChecked())) {
      throw new Error('Expected auto refresh to remain on 1 minute after reload');
    }
    await capture('applied');

    steps.push('revert auto refresh to none');
    await page.locator('label[for="refresh0"]').click();
    await waitForRefreshSelection(page, '#refresh0');
    await closeToastIfPresent(page);

    steps.push('reload and validate original report');
    await saveAndReloadReport(page, reportName, { clickSave: false });
    await openRefreshMenu(page, 'refresh menu reverted validation');
    if (!(await page.locator('#refresh0').isChecked())) {
      throw new Error('Expected auto refresh to return to none after reload');
    }
    await clickFirst(page, ['#backIcon2 button', '#backIcon2'], 'refresh back final');

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
