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
  addReportDataRow,
  clickFirst,
  ensureAnalyticsLoaded,
  attachPageIssueListeners,
  createCapture,
  ensureStoredReportWithDefaultData,
  openReportBasicSettings,
  openReportDataTab,
  openExistingReport,
  openOptionsMenuItem,
  saveAndReloadReport,
  waitForIdle,
} = require('./common');

const config = buildScenarioConfig('26');
const reportName = buildUniqueName('Playwright Regression', process.env.REPORT_NAME);

async function openChartOptions(page, label) {
  await openOptionsMenuItem(page, 'optionsMenuChartOptions', label);
  await page.locator('#optionsYAxis0').waitFor({ state: 'visible', timeout: 15000 });
}

async function getSelectedAnalyticsModel(page) {
  return page.locator('input[name="analyticsModel"]:checked').getAttribute('id');
}

async function setAnalyticsModel(page, selector) {
  await page.locator(selector).evaluate((node) => {
    node.checked = true;
    node.dispatchEvent(new Event('change', { bubbles: true }));
  });
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
    const seedState = await ensureStoredReportWithDefaultData(page, reportName, 'Chart options regression');
    steps.push(`report ${seedState}`);
    const initialReportChart = await page.evaluate(() => OCA.Analytics.currentReportData.options.chart || '');

    steps.push('ensure a fifth chart series');
    const initialSeriesCount = await page.evaluate(() => OCA.Analytics.chartObject.data.datasets.length);
    if (initialSeriesCount < 5) {
      await openReportDataTab(page, reportName);
      await addReportDataRow(page, 'Legend Fifth Series', 'Dimension 2', '3');
      await openExistingReport(page, reportName);
    }

    steps.push('save disabled fifth legend series');
    await page.evaluate(() => {
      const legend = OCA.Analytics.chartObject.legend;
      legend.options.onClick(null, {datasetIndex: -1}, legend);
    });
    await saveAndReloadReport(page, reportName);
    const fifthDatasetIndex = 4;
    if (!(await page.evaluate((index) => OCA.Analytics.chartObject.isDatasetVisible(index), fifthDatasetIndex))) {
      throw new Error('Expected Show all to activate the fifth chart series');
    }
    await page.evaluate((index) => {
      const legend = OCA.Analytics.chartObject.legend;
      const legendItem = legend.legendItems.find((item) => item.datasetIndex === index);
      legend.options.onClick(null, legendItem, legend);
    }, fifthDatasetIndex);
    if (!(await page.locator('#saveIcon').isVisible())) {
      throw new Error('Expected disabling the fifth chart series to trigger the save report button');
    }
    await saveAndReloadReport(page, reportName);
    const savedFifthSeriesState = await page.evaluate((index) => ({
      visible: OCA.Analytics.chartObject.isDatasetVisible(index),
      hidden: OCA.Analytics.currentReportData.options.dataoptions[index]?.hidden,
    }), fifthDatasetIndex);
    if (savedFifthSeriesState.visible !== false || savedFifthSeriesState.hidden !== true) {
      throw new Error(`Expected disabled fifth chart series to survive reload: ${JSON.stringify(savedFifthSeriesState)}`);
    }
    await page.evaluate(() => {
      const legend = OCA.Analytics.chartObject.legend;
      legend.options.onClick(null, {datasetIndex: -1}, legend);
    });
    await saveAndReloadReport(page, reportName);

    steps.push('toggle chart legend item and validate save trigger');
    const initialLegendItemState = await page.evaluate(() => {
      OCA.Analytics.unsavedChanges = false;
      OCA.Analytics.Filter.toggleSaveButtonDisplay();
      const legend = OCA.Analytics.chartObject.legend;
      const legendItem = legend.legendItems.find((item) => item.datasetIndex !== -1 && item.hidden === false);
      const state = {datasetIndex: legendItem.datasetIndex, hidden: legendItem.hidden};
      legend.options.onClick(null, legendItem, legend);
      return state;
    });
    if (!(await page.locator('#saveIcon').isVisible())) {
      throw new Error('Expected a chart legend item selection to trigger the save report button');
    }
    const pendingLegendItemHidden = await page.evaluate((datasetIndex) =>
      OCA.Analytics.currentReportData.options.dataoptions[datasetIndex]?.hidden,
    initialLegendItemState.datasetIndex);
    if (pendingLegendItemHidden !== !initialLegendItemState.hidden) {
      throw new Error('Expected chart legend item visibility to update pending data options immediately');
    }
    await saveAndReloadReport(page, reportName);
    const savedLegendItemState = await page.evaluate((datasetIndex) => ({
      dataOptions: OCA.Analytics.currentReportData.options.dataoptions,
      datasetHidden: OCA.Analytics.chartObject.data.datasets[datasetIndex].hidden,
      legendHidden: OCA.Analytics.chartObject.legend.legendItems
        .find((item) => item.datasetIndex === datasetIndex)?.hidden,
    }), initialLegendItemState.datasetIndex);
    if (savedLegendItemState.legendHidden !== !initialLegendItemState.hidden) {
      throw new Error(`Expected saved chart legend item visibility to survive reload: ${JSON.stringify(savedLegendItemState)}`);
    }

    steps.push('preserve legend item visibility through chart options');
    await openChartOptions(page, 'chart options legend preservation');
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply unchanged chart options');
    await waitForIdle(page, 15000);
    const legendHiddenAfterChartOptions = await page.evaluate((datasetIndex) =>
      OCA.Analytics.chartObject.legend.legendItems
        .find((item) => item.datasetIndex === datasetIndex)?.hidden,
    initialLegendItemState.datasetIndex);
    if (legendHiddenAfterChartOptions !== !initialLegendItemState.hidden) {
      throw new Error('Expected chart options dialog to preserve saved legend item visibility');
    }

    steps.push('restore hidden series through show all');
    const showAllState = await page.evaluate((datasetIndex) => {
      const legend = OCA.Analytics.chartObject.legend;
      legend.options.onClick(null, {datasetIndex: -1}, legend);
      return {
        visible: OCA.Analytics.chartObject.isDatasetVisible(datasetIndex),
        savedHidden: OCA.Analytics.currentReportData.options.dataoptions[datasetIndex]?.hidden,
      };
    }, initialLegendItemState.datasetIndex);
    if (showAllState.visible !== true || showAllState.savedHidden !== false) {
      throw new Error(`Expected Show all to restore and synchronize the hidden series: ${JSON.stringify(showAllState)}`);
    }

    steps.push('toggle chart legend and validate save trigger');
    const initialLegendDisplay = await page.evaluate(() => OCA.Analytics.chartObject.legend.options.display);
    await clickFirst(page, ['#chartLegend'], 'chart legend toggle');
    const toggledLegendDisplay = !initialLegendDisplay;
    const pendingLegendState = await page.evaluate(() => ({
      display: OCA.Analytics.currentReportData.options.chartoptions?.plugins?.legend?.display,
      unsavedChanges: OCA.Analytics.unsavedChanges,
    }));
    if (pendingLegendState.display !== toggledLegendDisplay) {
      throw new Error('Expected chart legend visibility to be added to the pending chart options');
    }
    if (pendingLegendState.unsavedChanges !== true || !(await page.locator('#saveIcon').isVisible())) {
      throw new Error('Expected the chart legend toggle to trigger the save report button');
    }

    steps.push('configure chart options');
    await openChartOptions(page, 'chart options');
    const initialYAxis = await page.locator('#optionsYAxis0').inputValue();
    const initialChartType = await page.locator('#optionsChartType0').inputValue();
    const initialColor = await page.locator('#optionsColor0').inputValue();
    const initialModel = await getSelectedAnalyticsModel(page);
    await page.locator('#optionsYAxis0').selectOption('secondary');
    await page.locator('#optionsChartType0').selectOption('line');
    await page.locator('#optionsColor0').fill('#cec7e8');
    await setAnalyticsModel(page, '#analyticsModelOpt2');
    if ((await page.locator('#optionsYAxis0').inputValue()) !== 'secondary') {
      throw new Error('Expected unsaved Y axis selection to survive analytics model change');
    }
    await capture('first_chart_options');

    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply chart options');

    steps.push('save reload and validate chart options');
    await page.evaluate(() => {
      OCA.Analytics.chartObject.legend.legendItems = [];
    });
    await saveAndReloadReport(page, reportName);

    if (!(await page.locator('#myChart').isVisible().catch(() => false))) {
      throw new Error('Expected chart to remain visible after saving chart options');
    }
    const savedLegendDisplay = await page.evaluate(() => OCA.Analytics.chartObject.legend.options.display);
    if (savedLegendDisplay !== toggledLegendDisplay) {
      throw new Error('Expected saved chart legend visibility to survive reload');
    }
    await openChartOptions(page, 'chart options validation');
    await capture('saved_chart_options');

    if ((await page.locator('#optionsYAxis0').inputValue()) !== 'secondary') {
      throw new Error('Expected saved Y axis selection to stay on secondary');
    }
    if ((await page.locator('#optionsChartType0').inputValue()) !== 'line') {
      throw new Error('Expected saved chart type to stay on line');
    }
    if ((await page.locator('#optionsColor0').inputValue()).toLowerCase() !== '#cec7e8') {
      throw new Error('Expected saved chart color to stay on #cec7e8');
    }
    if ((await getSelectedAnalyticsModel(page)) !== 'analyticsModelOpt2') {
      throw new Error('Expected saved analytics model to stay on columns');
    }
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'cancel chart options validation');

    steps.push('revert chart options');
    await openChartOptions(page, 'chart options revert');
    await page.locator('#optionsYAxis0').selectOption(initialYAxis);
    await page.locator('#optionsChartType0').selectOption(initialChartType);
    await page.locator('#optionsColor0').fill(initialColor);
    await setAnalyticsModel(page, `#${initialModel}`);
    await clickFirst(page, ['#analyticsDialogBtnGo'], 'apply reverted chart options');
    await clickFirst(page, ['#chartLegend'], 'restore chart legend visibility');

    steps.push('save reload and validate original report');
    await saveAndReloadReport(page, reportName);
    const restoredLegendDisplay = await page.evaluate(() => OCA.Analytics.chartObject.legend.options.display);
    if (restoredLegendDisplay !== initialLegendDisplay) {
      throw new Error('Saved chart legend visibility did not return to its original state');
    }
    await openChartOptions(page, 'chart options reverted validation');
    if ((await page.locator('#optionsYAxis0').inputValue()) !== initialYAxis) {
      throw new Error('Saved Y axis selection did not return to its original state');
    }
    if ((await page.locator('#optionsChartType0').inputValue()) !== initialChartType) {
      throw new Error('Saved chart type did not return to its original state');
    }
    if ((await page.locator('#optionsColor0').inputValue()).toLowerCase() !== initialColor.toLowerCase()) {
      throw new Error('Saved chart color did not return to its original state');
    }
    if ((await getSelectedAnalyticsModel(page)) !== initialModel) {
      throw new Error('Saved analytics model did not return to its original state');
    }
    await clickFirst(page, ['#analyticsDialogBtnCancel'], 'cancel reverted chart options validation');

    steps.push('switch to doughnut chart');
    await openReportBasicSettings(page, reportName);
    await clickFirst(page, ['#reportVisualizationSectionHeaderH3'], 'visualization section');
    await page.locator('#sidebarReportChart').selectOption('doughnut');
    await clickFirst(page, ['#sidebarReportUpdateButton'], 'apply doughnut chart');
    await waitForIdle(page, 15000);
    await page.waitForFunction(() => OCA.Analytics.chartObject?.config?.type === 'doughnut', null, {timeout: 15000});

    steps.push('save disabled doughnut legend item');
    const doughnutLegendIndex = await page.evaluate(() => {
      const legend = OCA.Analytics.chartObject.legend;
      const legendItem = legend.legendItems.find((item) => item.datasetIndex !== -1);
      legend.options.onClick(null, legendItem, legend);
      return legendItem.index;
    });
    if (!(await page.locator('#saveIcon').isVisible())) {
      throw new Error('Expected a doughnut legend item selection to trigger the save report button');
    }
    await saveAndReloadReport(page, reportName);
    const savedDoughnutLegendState = await page.evaluate((dataIndex) => ({
      dataVisible: OCA.Analytics.chartObject.getDataVisibility(dataIndex),
      legendHidden: OCA.Analytics.chartObject.legend.legendItems
        .find((item) => item.index === dataIndex)?.hidden,
      savedHidden: OCA.Analytics.currentReportData.options.dataoptions[dataIndex]?.hidden,
    }), doughnutLegendIndex);
    if (savedDoughnutLegendState.dataVisible !== false
      || savedDoughnutLegendState.legendHidden !== true
      || savedDoughnutLegendState.savedHidden !== true) {
      throw new Error(`Expected disabled doughnut legend item to survive reload: ${JSON.stringify(savedDoughnutLegendState)}`);
    }

    steps.push('restore doughnut legend item and chart type');
    await page.evaluate((dataIndex) => {
      const legend = OCA.Analytics.chartObject.legend;
      const legendItem = legend.legendItems.find((item) => item.index === dataIndex);
      legend.options.onClick(null, legendItem, legend);
    }, doughnutLegendIndex);
    await saveAndReloadReport(page, reportName);
    if (!(await page.evaluate((dataIndex) => OCA.Analytics.chartObject.getDataVisibility(dataIndex), doughnutLegendIndex))) {
      throw new Error('Expected restored doughnut legend item to stay visible after reload');
    }

    await openReportBasicSettings(page, reportName);
    await clickFirst(page, ['#reportVisualizationSectionHeaderH3'], 'visualization section restore');
    await page.locator('#sidebarReportChart').selectOption(initialReportChart);
    await clickFirst(page, ['#sidebarReportUpdateButton'], 'restore original chart type');
    await waitForIdle(page, 15000);

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
