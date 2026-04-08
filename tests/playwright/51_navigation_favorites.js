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
  createCapture,
  ensureAnalyticsLoaded,
  openOrCreateStoredReport,
  openNavigationEntryMenu,
  attachPageIssueListeners,
  waitForIdle,
} = require('./common');

const config = buildScenarioConfig('51');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function ensureFavoritesSectionOpen(page) {
  const section = page.locator('#navigationDatasets li[data-section-id="section-favorites"]').first();
  await section.waitFor({ state: 'attached', timeout: 10000 });
  const isOpen = await section.evaluate((node) => node.classList.contains('open')).catch(() => false);
  if (!isOpen) {
    await section.locator('.app-navigation-entry > a').first().click();
    await page.waitForFunction((element) => element.classList.contains('open'), await section.elementHandle(), { timeout: 5000 });
  }
}

async function favoriteMenuState(menu) {
  const favoriteAction = menu.locator('#navigationMenueFavorite').first();
  await favoriteAction.waitFor({ state: 'visible', timeout: 10000 });
  const iconClass = (await favoriteAction.locator('span').first().getAttribute('class')) || '';
  return iconClass.includes('icon-starred') ? 'favorite' : 'not-favorite';
}

async function setReportFavorite(page, name, shouldBeFavorite) {
  for (let attempt = 0; attempt < 3; attempt += 1) {
    const menu = await openNavigationEntryMenu(page, name);
    const currentState = await favoriteMenuState(menu);
    const targetState = shouldBeFavorite ? 'favorite' : 'not-favorite';

    if (currentState === targetState) {
      await page.keyboard.press('Escape').catch(() => {});
      return currentState;
    }

    await menu.evaluate((node) => node.classList.add('open')).catch(() => {});
    await menu.locator('#navigationMenueFavorite').first().click();
    await waitForIdle(page, 15000);

    const selector = `#section-favorites a[data-name="${name.replace(/"/g, '\\"')}"]`;
    if (shouldBeFavorite) {
      await ensureFavoritesSectionOpen(page);
      await page.locator(selector).first().waitFor({ state: 'visible', timeout: 10000 });
    } else {
      await page.waitForFunction(
        (targetName) => !document.querySelector(`#section-favorites a[data-name="${targetName}"]`),
        name,
        { timeout: 10000 }
      );
    }
  }

  throw new Error(`Could not set favorite state for "${name}" to ${shouldBeFavorite}`);
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

    steps.push(process.env.REPORT_NAME ? 'open or reuse shared report' : 'create report for favorites regression');
    const reportAction = await openOrCreateStoredReport(page, reportName, 'Navigation favorites regression');
    steps.push(`report ${reportAction}`);

    steps.push('ensure report starts as non-favorite');
    await setReportFavorite(page, reportName, false);

    steps.push('favorite report from navigation menu');
    await setReportFavorite(page, reportName, true);

    await ensureFavoritesSectionOpen(page);
    const favoriteNavEntry = page.locator(`#section-favorites a[data-name="${reportName.replace(/"/g, '\\"')}"]`).first();
    await favoriteNavEntry.waitFor({ state: 'visible', timeout: 10000 });

    steps.push('open overview and verify favorite widget');
    await clickFirst(page, ['#overviewButton', 'a:has-text("Overview")'], 'overview button');
    await page.locator('.analyticsWidgetReport', { hasText: reportName }).first().waitFor({ state: 'visible', timeout: 15000 });

    await capture('enabled');

    steps.push('remove favorite from navigation menu');
    await ensureFavoritesSectionOpen(page);
    await clickFirst(page, [`#section-favorites a[data-name="${reportName.replace(/"/g, '\\"')}"]`], 'favorite navigation entry');
    await setReportFavorite(page, reportName, false);

    await clickFirst(page, ['#overviewButton', 'a:has-text("Overview")'], 'overview button refresh');
    await page.waitForFunction(
      (targetName) => {
        return !Array.from(document.querySelectorAll('.analyticsWidgetReport')).some((node) =>
          (node.textContent || '').includes(targetName)
        );
      },
      reportName,
      { timeout: 15000 }
    );

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
