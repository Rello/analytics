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

    steps.push('validate layout-aware calculated-column pipeline');
    const pipelineResult = await page.evaluate(() => {
      const visualization = OCA.Analytics.Visualization;
      const encodeCalculations = (items) => items.map((item) => JSON.stringify(item)).join(',');

      const nonPivotOptions = {
        layout: { rows: [0, 1], columns: [], measures: [], notRequired: [2] },
      };
      const nonPivotData = [
        ['2', '5', 'ignored'],
        ['3', '7', 'ignored'],
      ];
      const nonPivotHeader = ['Quantity', 'Price', 'Hidden'];
      const nonPivotSources = visualization.getTableCalculatedColumnSources(
        nonPivotData,
        nonPivotHeader,
        nonPivotOptions
      );
      nonPivotOptions.calculatedColumns = encodeCalculations([{
        version: 2,
        operation: 'formula',
        references: [nonPivotSources[0].reference, nonPivotSources[1].reference],
        expression: 'ROUND(ref1 * ref2, 2)',
        title: 'Revenue',
      }]);
      let nonPivot = visualization.convertDataToDataTableFormat(
        nonPivotData,
        nonPivotOptions,
        nonPivotHeader
      );
      nonPivot = visualization.dataTableCalculatedColumns(
        nonPivot.data,
        nonPivot.columns,
        nonPivotOptions
      );
      if (nonPivot.columns.map((column) => column.analyticsLabel).join('|') !== 'Quantity|Price|Revenue') {
        throw new Error('Non-pivot calculated columns did not follow the displayed row layout');
      }
      if (nonPivot.data[0][2] !== 10 || nonPivot.data[1][2] !== 21) {
        throw new Error(`Unexpected non-pivot calculation values: ${JSON.stringify(nonPivot.data)}`);
      }

      const pivotOptions = {
        layout: { rows: [1], columns: [0], measures: [2], notRequired: [] },
      };
      const pivotHeader = ['Type', 'Week', '€'];
      const pivotData = [
        ['Achived', 'W1', '80'],
        ['Goal', 'W1', '100'],
        ['unweighted', 'W1', '60'],
        ['weighted', 'W1', '70'],
        ['Achived', 'W2', '100'],
        ['Goal', 'W2', '200'],
      ];
      const pivotSources = visualization.getTableCalculatedColumnSources(pivotData, pivotHeader, pivotOptions);
      const sourceByLabel = Object.fromEntries(pivotSources.map((source) => [source.label, source]));
      pivotOptions.calculatedColumns = encodeCalculations([
        {
          version: 2,
          operation: 'substract',
          references: [sourceByLabel.Goal.reference, sourceByLabel.Achived.reference],
          title: 'todo',
        },
        {
          version: 2,
          operation: 'percentage',
          references: [sourceByLabel.Achived.reference, sourceByLabel.Goal.reference],
          title: 'Achievement',
        },
      ]);
      let pivot = visualization.convertDataToDataTableFormat(pivotData, pivotOptions, pivotHeader);
      pivot = visualization.dataTableCalculatedColumns(pivot.data, pivot.columns, pivotOptions);
      const pivotLabels = pivot.columns.map((column) => column.analyticsLabel);
      const todoIndex = pivotLabels.indexOf('todo');
      const achievementIndex = pivotLabels.indexOf('Achievement');
      if (todoIndex === -1 || achievementIndex === -1) {
        throw new Error(`Missing pivot calculations: ${JSON.stringify(pivotLabels)}`);
      }
      if (pivot.data[0][todoIndex] !== 20 || pivot.data[0][achievementIndex] !== 80) {
        throw new Error(`Unexpected W1 pivot calculations: ${JSON.stringify(pivot.data[0])}`);
      }
      if (pivot.data[1][todoIndex] !== 100 || pivot.data[1][achievementIndex] !== 50) {
        throw new Error(`Unexpected W2 pivot calculations: ${JSON.stringify(pivot.data[1])}`);
      }

      const legacyOptions = {
        layout: pivotOptions.layout,
        calculatedColumns: JSON.stringify({
          operation: 'substract',
          columns: [pivotSources.indexOf(sourceByLabel.Goal), pivotSources.indexOf(sourceByLabel.Achived)],
          title: 'legacy todo',
        }),
      };
      let legacy = visualization.convertDataToDataTableFormat(pivotData, legacyOptions, pivotHeader);
      legacy = visualization.dataTableCalculatedColumns(legacy.data, legacy.columns, legacyOptions);
      if (!legacyOptions.calculatedColumns.includes('"version":2') || legacy.data[0].at(-1) !== 20) {
        throw new Error('Legacy post-pivot references were not upgraded correctly');
      }

      const missingOptions = {
        layout: pivotOptions.layout,
        calculatedColumns: encodeCalculations([{
          version: 2,
          operation: 'add',
          references: ['pivot:0:Type:Missing'],
          title: 'Unavailable',
        }]),
      };
      let missing = visualization.convertDataToDataTableFormat(pivotData, missingOptions, pivotHeader);
      missing = visualization.dataTableCalculatedColumns(missing.data, missing.columns, missingOptions);
      if (missing.data.some((row) => row.at(-1) !== '')) {
        throw new Error('A globally missing pivot reference should render blank values');
      }

      if (visualization.getSafeColReorder({ colReorder: { order: [0, 1] } }, 3, true) !== true) {
        throw new Error('Invalid saved column order was not discarded');
      }
      const chartData = visualization.getChartDataWithCalculatedColumns({
        header: nonPivotHeader,
        data: nonPivotData,
        options: { tableoptions: nonPivotOptions },
      }, 'timeSeriesModel');
      if (chartData.header.length !== nonPivotHeader.length || chartData.data[0].length !== nonPivotData[0].length) {
        throw new Error('Table calculated columns leaked into chart data');
      }

      return { nonPivotRows: nonPivot.data.length, pivotRows: pivot.data.length };
    });
    steps.push(`calculated pipeline ${pipelineResult.nonPivotRows}/${pipelineResult.pivotRows}`);

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
    if (!footerValue.includes('9.1')) {
      throw new Error(`Expected footer total to contain "9.1", got "${footerValue}"`);
    }

    const reorderedFooter = await page.evaluate(() => {
      const key = Object.keys(OCA.Analytics.tableObject)[0];
      const table = OCA.Analytics.tableObject[key];
      const originalOrder = table.colReorder.order();
      const reordered = [...originalOrder];
      if (reordered.length >= 3) {
        [reordered[1], reordered[2]] = [reordered[2], reordered[1]];
        table.colReorder.order(reordered);
        table.draw(false);
      }

      const headers = Array.from(document.querySelectorAll('#tableContainer thead th'))
        .map((cell) => cell.textContent.trim());
      const footers = Array.from(document.querySelectorAll('#tableContainer tfoot td'))
        .map((cell) => cell.textContent.trim());
      const actual = footers.map((value, displayIndex) => displayIndex === 0
        ? value
        : OCA.Analytics.Visualization.parseCalculatedColumnNumber(value));
      const expected = headers.map((header, displayIndex) => {
        if (displayIndex === 0) {
          return 'Total';
        }
        return table.column(`${displayIndex}:visible`).data().toArray()
          .reduce((sum, value) => sum + OCA.Analytics.Visualization.parseCalculatedColumnNumber(value), 0);
      });

      table.colReorder.order(originalOrder);
      table.draw(false);
      return { headers, footers, actual, expected };
    });
    reorderedFooter.expected.forEach((expected, index) => {
      if (index === 0) {
        if (reorderedFooter.footers[index] !== expected) {
          throw new Error(`Expected Total in the first reordered footer cell, got ${JSON.stringify(reorderedFooter)}`);
        }
        return;
      }
      if (Math.abs(reorderedFooter.actual[index] - expected) > 0.001) {
        throw new Error(`Footer moved away from its reordered column: ${JSON.stringify(reorderedFooter)}`);
      }
    });

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
