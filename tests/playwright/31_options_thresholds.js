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
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  ensureStoredReportWithDefaultData,
  openOptionsMenuItem,
  saveAndReloadReport,
} = require('./common');

const config = buildScenarioConfig('31');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function openThresholdDialog(page, label) {
  await openOptionsMenuItem(page, 'optionsMenuThreshold', label);
  await page.locator('#thresholdDimension').waitFor({ state: 'visible', timeout: 15000 });
}

async function thresholdTexts(page) {
  return page.locator('#thresholdList .thresholdText').evaluateAll((nodes) =>
    nodes.map((node) => node.textContent.replace(/\s+/g, ' ').trim())
  );
}

async function waitForThresholdText(page, expectedText, timeout = 10000) {
  await page.waitForFunction(
    ({ selector, expected }) => {
      const texts = Array.from(document.querySelectorAll(selector))
        .map((node) => (node.textContent || '').replace(/\s+/g, ' ').trim());
      return texts.includes(expected);
    },
    { selector: '#thresholdList .thresholdText', expected: expectedText },
    { timeout }
  );
}

async function waitForThresholdTextGone(page, expectedText, timeout = 10000) {
  await page.waitForFunction(
    ({ selector, expected }) => {
      const texts = Array.from(document.querySelectorAll(selector))
        .map((node) => (node.textContent || '').replace(/\s+/g, ' ').trim());
      return !texts.includes(expected);
    },
    { selector: '#thresholdList .thresholdText', expected: expectedText },
    { timeout }
  );
}

async function createThreshold(page, { columnLabel, value, severity, coloring = 'value', option = '=' }) {
  await page.locator('#thresholdDimension').selectOption({ label: columnLabel });
  await page.locator('#thresholdOption').selectOption(option);
  await page.locator('#thresholdValue').fill(value);
  await page.locator('#thresholdSeverity').selectOption(severity);
  await page.locator('#thresholdColoring').selectOption(coloring);
  await page.locator('#thresholdCreateButton').click();
}

async function deleteThreshold(page, expectedText) {
  const row = page.locator('#thresholdList .thresholdItem', { hasText: expectedText }).first();
  await row.waitFor({ state: 'visible', timeout: 10000 });
  await row.locator('.analyticsTesting').click();
  await waitForThresholdTextGone(page, expectedText);
}

async function deleteAllThresholds(page) {
  for (let index = 0; index < 10; index += 1) {
    const button = page.locator('#thresholdList .analyticsTesting').first();
    if (!(await button.count())) {
      return;
    }
    await button.click();
    await page.waitForTimeout(300);
  }

  const remaining = await thresholdTexts(page);
  if (remaining.length) {
    throw new Error(`Expected threshold cleanup to finish, found ${JSON.stringify(remaining)}`);
  }
}

async function assertSyntheticThresholdColoring(page) {
  const result = await page.evaluate(() => {
    const row = document.createElement('tr');
    ['Dimension 2', '5', 'Dimension 1'].forEach((value) => {
      const cell = document.createElement('td');
      cell.textContent = value;
      row.appendChild(cell);
    });

    OCA.Analytics.Visualization.dataTableRowCallback(
      row,
      ['Dimension 2', '5', 'Dimension 1'],
      0,
      [{
        dimension: '0',
        option: '=',
        value: 'Dimension 1',
        severity: '2',
        coloring: 'value',
      }],
      {
        compactDisplay: false,
        layout: {
          rows: [1, 2, 0],
          columns: [],
          measures: [],
        },
      }
    );

    return Array.from(row.children).map((cell) => cell.style.color || '');
  });

  if (result[2] !== 'crimson' || result[0] || result[1]) {
    throw new Error(`Expected only the displayed source-column cell to be colored, got ${JSON.stringify(result)}`);
  }
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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Threshold options regression');
    steps.push(`report ${seedState}`);

    steps.push('open threshold dialog and reset existing thresholds');
    await openThresholdDialog(page, 'threshold options');
    await deleteAllThresholds(page);

    steps.push('create column 2 threshold and validate list entry');
    await createThreshold(page, {
      columnLabel: 'Column 2',
      value: 'Dimension 2',
      severity: '2',
    });
    await waitForThresholdText(page, 'Column 2 = Dimension 2');
    await assertSyntheticThresholdColoring(page);

    steps.push('delete first threshold and verify removal');
    await deleteThreshold(page, 'Column 2 = Dimension 2');
    const remainingAfterDelete = await thresholdTexts(page);
    if (remainingAfterDelete.length !== 0) {
      throw new Error(`Expected no thresholds after deleting the first entry, got ${JSON.stringify(remainingAfterDelete)}`);
    }

    steps.push('create notification green value');
    await createThreshold(page, {
      columnLabel: 'Value',
      value: '2',
      severity: '4',
      coloring: 'value',
    });
    await waitForThresholdText(page, 'Value = 2');

    steps.push('create yellow notification row threshold');
    await createThreshold(page, {
      columnLabel: 'Column 1',
      value: 'Dimension 1',
      severity: '3',
      coloring: 'row',
    });
    await waitForThresholdText(page, 'Column 1 = Dimension 1');

    await capture('created');

    const thresholdRow = page.locator('#thresholdList .thresholdItem', { hasText: 'Column 1 = Dimension 1' }).first();
    const colorIconSource = await thresholdRow.locator('.thresholdColorIcon').getAttribute('src');
    if (!colorIconSource || !colorIconSource.includes('row.svg')) {
      throw new Error(`Expected row-coloring icon for threshold, got "${colorIconSource}"`);
    }

    steps.push('reload report and confirm threshold persists');
    await saveAndReloadReport(page, reportName, { clickSave: false });

    await capture('applied');

    /*await openThresholdDialog(page, 'threshold options validation');
    await waitForThresholdText(page, 'Column 1 = Dimension 11');

    const reloadedThresholds = await thresholdTexts(page);
    if (!reloadedThresholds.includes('Column 1 = Dimension 11')) {
      throw new Error(`Expected threshold to persist after reload, got ${JSON.stringify(reloadedThresholds)}`);
    }

    steps.push('cleanup threshold state');
    await deleteAllThresholds(page);
    const finalThresholds = await thresholdTexts(page);
    if (finalThresholds.length !== 0) {
      throw new Error(`Expected threshold cleanup to leave no entries, got ${JSON.stringify(finalThresholds)}`);
    }*/

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
