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
  waitForIdle,
} = require('./common');

const config = buildScenarioConfig('30');
const reportName = buildUniqueName('Playwright Chart Modal', process.env.REPORT_NAME);

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
    await waitForIdle(page, 15000);

    if (process.env.REPORT_NAME) {
      steps.push('open shared report');
      await openExistingReport(page, reportName);
    } else {
      steps.push('create chart report');
      await createStoredReport(page, reportName, 'Chart modal regression', {
        dimension1: 'Series',
        dimension2: 'Period',
        visualization: 'chart',
      });
    }

    steps.push('open chart options modal');
    await clickFirst(page, ['#optionsMenuIcon'], 'options menu');
    await clickFirst(page, ['#optionsMenuChartOptions'], 'chart options menu');
    await page.locator('#analyticsDialogContainer.analyticsDialog--enhanced').waitFor({ state: 'visible', timeout: 15000 });

    const navigationLinks = page.locator('.analyticsEnhancedDialogNavButton');
    const navigationLabels = await navigationLinks.evaluateAll(
      (links) => links.map((link) => link.textContent.trim())
    );
    const navigationTargets = await navigationLinks.evaluateAll(
      (links) => links.map((link) => ({
        tagName: link.tagName,
        href: link.getAttribute('href'),
      }))
    );
    const sectionLabels = await page.locator('.analyticsDialogSection h2').evaluateAll(
      (headings) => headings.map((heading) => heading.textContent.trim())
    );
    const sectionIds = await page.locator('.analyticsDialogSection').evaluateAll(
      (sections) => sections.map((section) => section.id)
    );

    if (navigationLabels.length !== 3) {
      throw new Error(`Expected 3 chart option nav items, got ${navigationLabels.length}`);
    }

    if (JSON.stringify(navigationLabels) !== JSON.stringify(sectionLabels)) {
      throw new Error(`Nav labels do not match section headings: ${JSON.stringify(navigationLabels)} vs ${JSON.stringify(sectionLabels)}`);
    }

    if (!navigationTargets.every((target, index) => target.tagName === 'A' && target.href === `#${sectionIds[index]}`)) {
      throw new Error(`Expected section nav links with anchor targets, got ${JSON.stringify(navigationTargets)}`);
    }

    const documentationLinks = await page.locator('.analyticsEnhancedDialogDocLink').count();
    if (documentationLinks !== 1) {
      throw new Error(`Expected one documentation link, got ${documentationLinks}`);
    }

    const feedbackLinks = await page.locator('.analyticsEnhancedDialogNav a[href*="feedback"], .analyticsEnhancedDialogNav :has-text("Feedback")').count();
    if (feedbackLinks !== 0) {
      throw new Error('Unexpected feedback link found in enhanced chart modal');
    }

    steps.push('switch navigation section');
    const targetNav = navigationLinks.nth(2);
    const targetSectionId = await targetNav.evaluate((link) => link.dataset.target || '');
    const targetLabel = (await targetNav.textContent() || '').trim();
    await targetNav.click();

    await page.waitForFunction(
      ({ sectionId }) => {
        const section = document.getElementById(sectionId);
        if (!section) {
          return false;
        }
        const rect = section.getBoundingClientRect();
        return rect.height > 0 && rect.bottom > 0 && rect.top < window.innerHeight;
      },
      { sectionId: targetSectionId },
      { timeout: 5000 }
    );

    await page.waitForFunction(
      ({ sectionId }) => {
        const activeButton = document.querySelector('.analyticsEnhancedDialogNavButton--active');
        return activeButton && activeButton.dataset.target === sectionId;
      },
      { sectionId: targetSectionId },
      { timeout: 5000 }
    );

    const activeLabel = await page.locator('.analyticsEnhancedDialogNavButton--active').textContent();
    if ((activeLabel || '').trim() !== targetLabel) {
      throw new Error(`Expected active nav label "${targetLabel}", got "${(activeLabel || '').trim()}"`);
    }

    await capture('final_chart_options_modal');

    steps.push('close modal with cancel');
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'chart options cancel');
    await page.locator('#analyticsDialogContainer').waitFor({ state: 'hidden', timeout: 10000 });

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
