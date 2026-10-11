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

async function clearFiltersAndReload(page) {
  const cancel = page.locator('#analyticsDialogBtnCancel');
  if (await cancel.isVisible()) await cancel.click();
  const buttons = page.locator('.filterVisualizationRemove');
  while (await buttons.count()) {
    const count = await buttons.count();
    await buttons.first().click();
    await page.waitForFunction(previousCount =>
      document.querySelectorAll('.filterVisualizationRemove').length < previousCount
      && !OCA.Analytics.currentXhrRequest, count);
  }
  await saveAndReloadReport(page, reportName);
  const savedFilterCount = await page.evaluate(() =>
    Object.keys(OCA.Analytics.currentReportData.options.filteroptions?.filter || {}).length);
  if (savedFilterCount !== 0 || await buttons.count()) {
    throw new Error('Filter cleanup did not persist after Save and reload');
  }
}

(async () => {
  const browser = await chromium.launch({ headless: config.headless });
  const page = await browser.newPage({ viewport: config.viewport });
  const issues = [];
  const steps = [];
  const capture = createCapture(page, config);
  let cleanupRequired = false;
  let failed = false;

  attachPageIssueListeners(page, issues);

  try {
    steps.push('open analytics');
    await ensureAnalyticsLoaded(page, config);

    steps.push('open seeded report');
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Filter regression');
    steps.push(`report ${seedState}`);
    const existingFilterCount = await page.evaluate(() =>
      Object.keys(OCA.Analytics.currentReportData.options.filteroptions?.filter || {}).length);
    if (existingFilterCount !== 0) {
      throw new Error('Filter test requires an unfiltered report; start a new run without REPORT_NAME');
    }
    cleanupRequired = true;

    steps.push('apply combined same-dimension filters');
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
    await page.locator('#filterDialogDimension1').selectOption({ label: 'Column 2' });
    await page.locator('#filterDialogValue1').fill('Threshold Test');
    await clickFirst(page, ['#addFilterRowButton'], 'add third filter row');
    await page.locator('#filterDialogValue2').waitFor({ state: 'visible', timeout: 15000 });
    await page.locator('#filterDialogDimension2').selectOption({ label: 'Column 1' });
    await page.locator('#filterDialogOption2').selectOption({ label: 'contains' });
    await page.locator('#filterDialogValue2').fill('N');
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'confirm filter');
    await page.locator('#reportSubHeader').waitFor({ state: 'visible', timeout: 15000 });

    steps.push('save reload and validate combined filters');
    await saveAndReloadReport(page, reportName);
    const activeFilterChips = page.locator('.filterVisualizationRemove');
    const filterChipCountAfterReload = await activeFilterChips.count();
    if (filterChipCountAfterReload !== 3) {
      throw new Error(`Expected 3 active filter chips after reload, got ${filterChipCountAfterReload}`);
    }
    const dimensionFilterVisible = await page.locator('.filterVisualizationItem').first().isVisible().catch(() => false);
    if (!dimensionFilterVisible) {
      throw new Error('Expected filter chip after reloading the saved filter');
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
    if (filterChipCount !== 3) {
      throw new Error(`Expected 3 active filter chips before removal, got ${filterChipCount}`);
    }
    await clearFiltersAndReload(page);
    cleanupRequired = false;

    steps.push('validate original report after filter cleanup');
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

    await capture('cleared');
  } catch (error) {
    failed = true;
    issues.push(`fatal:${error.message}`);
    await capture('failure_state').catch(() => {});
  } finally {
    if (cleanupRequired) {
      try {
        await clearFiltersAndReload(page);
        steps.push('filters removed and saved after failure');
        await capture('cleared_after_failure');
      } catch (error) {
        failed = true;
        issues.push(`fatal:filter cleanup failed: ${error.message}`);
      }
    }
    const result = {
      scriptId: config.scriptId,
      status: failed ? 'FAIL' : issues.length ? 'WARN' : 'PASS',
      baseUrl: config.baseUrl,
      finalUrl: page.url(),
      reportName,
      steps,
      issues,
    };
    console.log(JSON.stringify(result, null, 2));
    if (result.status !== 'PASS') process.exitCode = 1;
    await browser.close();
  }
})();
