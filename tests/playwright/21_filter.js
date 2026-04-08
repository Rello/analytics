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
  saveAndReloadReport,
  waitForIdle,
} = require('./common');

const config = buildScenarioConfig('21-filter');
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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Filter regression');
    steps.push(`report ${seedState}`);

    steps.push('apply combined column-2 filters');
    await clickFirst(page, ['#addFilterIcon'], 'add filter');
    await page.locator('#filterDialogValue').waitFor({ state: 'visible', timeout: 15000 });
    await page.locator('#filterDialogDimension').selectOption({ label: 'Column 2' });
    const selectedOperatorText = (await page.locator('#filterDialogOption').locator('option:checked').innerText()).trim();
    if (selectedOperatorText !== 'equal to') {
      throw new Error(`Expected default operator "equal to", got "${selectedOperatorText}"`);
    }
    await page.locator('#filterDialogValue').fill('Dimension 2');
    await clickFirst(page, ['#addFilterRowButton'], 'add second filter row');
    await page.locator('#filterDialogValue1').waitFor({ state: 'visible', timeout: 15000 });
    await page.locator('#filterDialogDimension1').selectOption({ label: 'Column 1' });
    await page.locator('#filterDialogOption1').selectOption({ label: 'contains' });
    await page.locator('#filterDialogValue1').fill('N');
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'confirm filter');
    await page.locator('#reportSubHeader').waitFor({ state: 'visible', timeout: 15000 });

    steps.push('save reload and validate combined filters');
    await saveAndReloadReport(page, reportName);
    const activeFilterChips = page.locator('.filterVisualizationRemove');
    const filterChipCountAfterReload = await activeFilterChips.count();
    if (filterChipCountAfterReload !== 2) {
      throw new Error(`Expected 2 active filter chips after reload, got ${filterChipCountAfterReload}`);
    }
    const dimensionFilterVisible = await page.locator('#dimension2').first().isVisible().catch(() => false);
    if (!dimensionFilterVisible) {
      throw new Error('Expected dimension2 filter chip after reloading the saved filter');
    }

    let rowTexts = await page.locator('#tableContainer tbody tr').evaluateAll((nodes) =>
      nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim())
    );
    if (rowTexts.length !== 1 || rowTexts[0] !== 'Top N Dimension 2 5') {
      throw new Error(`Unexpected filtered rows after combined Column 2 filters: ${JSON.stringify(rowTexts)}`);
    }
    await capture('applied');

    steps.push('remove combined filters');
    const filterRemoveButtons = page.locator('.filterVisualizationRemove');
    const filterChipCount = await filterRemoveButtons.count();
    if (filterChipCount !== 2) {
      throw new Error(`Expected 2 active filter chips before removal, got ${filterChipCount}`);
    }
    await filterRemoveButtons.nth(0).click();
    await page.waitForTimeout(300);
    await filterRemoveButtons.nth(0).click();
    await page.waitForTimeout(500);
    if (await page.locator('.filterVisualizationRemove').count()) {
      throw new Error('Filter chip remove buttons still visible after removing both filters');
    }

    steps.push('save reload and validate original report');
    await saveAndReloadReport(page, reportName);
    await waitForIdle(page, 10000);
    rowTexts = await page.locator('#tableContainer tbody tr').evaluateAll((nodes) =>
      nodes.map((node) => node.innerText.replace(/\s+/g, ' ').trim())
    );
    if (await page.locator('.filterVisualizationRemove').count()) {
      throw new Error('Filter chip remove buttons still visible after reloading the cleared report');
    }
    if (rowTexts.length < 4) {
      throw new Error(`Expected unfiltered seeded rows after removing filter, got: ${JSON.stringify(rowTexts)}`);
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
