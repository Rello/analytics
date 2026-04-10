/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const fs = require('fs/promises');
const path = require('path');
const { spawn } = require('child_process');

const reportName = process.env.REPORT_NAME || 'Playwright Regression';
const groupName = process.env.GROUP_NAME || 'Playwright Regression Group';
const artifactRoot = process.env.ARTIFACT_DIR || path.join(process.cwd(), 'tests', 'ui-artifacts');
const startAt = (process.env.PLAYWRIGHT_START || '').trim();

const scenarios = [
  { id: '10', title: 'navigation', script: 'tests/playwright/10_navigation.js' },
  { id: '11', title: 'report_create', script: 'tests/playwright/11_report.js' },
  { id: '12', title: 'group_create', script: 'tests/playwright/12_group.js' },
  { id: '14', title: 'add_del_data', script: 'tests/playwright/14_add_del_data.js' },
  { id: '16', title: 'sidebar_report_options', script: 'tests/playwright/16_report_options.js' },
  { id: '21', title: 'filter', script: 'tests/playwright/21_filter.js' },
  { id: '22', title: 'options_drilldown', script: 'tests/playwright/22_drilldown.js' },
  { id: '23', title: 'options_sort', script: 'tests/playwright/23_sort.js' },
  { id: '25', title: 'options_table', script: 'tests/playwright/25_options_table.js' },
  { id: '26', title: 'options_chart', script: 'tests/playwright/26_options_chart.js' },
  { id: '27', title: 'options_refresh', script: 'tests/playwright/27_options_refresh.js' },
  { id: '28', title: 'options_translate', script: 'tests/playwright/28_options_translate.js' },
  { id: '29', title: 'options_group_top_n', script: 'tests/playwright/29_group_top_n.js' },
  { id: '30', title: 'chart_options_modal', script: 'tests/playwright/30_chart_options_modal.js' },
  { id: '31', title: 'options_thresholds', script: 'tests/playwright/31_options_thresholds.js' },
  { id: '41', title: 'datasource_git', script: 'tests/playwright/41_datasource_git.js' },
  { id: '42', title: 'datasource_json', script: 'tests/playwright/42_datasource_json.js' },
  { id: '43', title: 'datasource_csv', script: 'tests/playwright/43_datasource_csv.js' },
  { id: '44', title: 'automation_dataload_csv_column_picker', script: 'tests/playwright/44_automation_dataload_csv_column_picker.js' },
  { id: '45', title: 'automation_deletion', script: 'tests/playwright/45_automation_deletion.js' },
  { id: '50', title: 'navigation_share', script: 'tests/playwright/50_navigation_share.js' },
  { id: '51', title: 'navigation_favorites', script: 'tests/playwright/51_navigation_favorites.js' },
  { id: '91', title: 'report_delete', script: 'tests/playwright/91_report_delete.js' },
  { id: '92', title: 'group_delete', script: 'tests/playwright/92_group_delete.js' },
];

function resolveScenariosToRun() {
  if (!startAt) {
    return scenarios;
  }

  const startIndex = scenarios.findIndex((scenario) => scenario.id === startAt);
  if (startIndex === -1) {
    throw new Error(`Unknown PLAYWRIGHT_START scenario: ${startAt}`);
  }

  return scenarios.slice(startIndex);
}

function parseScenarioOutput(stdout, fallbackScenario) {
  const trimmed = stdout.trim();
  if (!trimmed) {
    return {
      scriptId: fallbackScenario.id,
      status: 'FAIL',
      issues: ['fatal:missing scenario output'],
    };
  }

  try {
    return JSON.parse(trimmed);
  } catch (error) {
    return {
      scriptId: fallbackScenario.id,
      status: 'FAIL',
      issues: [`fatal:could not parse scenario output: ${error.message}`],
      rawOutput: trimmed,
    };
  }
}

function runScenario(scenario) {
  return new Promise((resolve) => {
    const env = {
      ...process.env,
      REPORT_NAME: reportName,
      GROUP_NAME: groupName,
      ARTIFACT_DIR: artifactRoot,
      ARTIFACT_FLAT: '1',
      ARTIFACT_PREFIX: `${scenario.id}_${scenario.title}`,
    };

    const child = spawn(process.execPath, [path.join(process.cwd(), scenario.script)], {
      cwd: process.cwd(),
      env,
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    let stdout = '';
    let stderr = '';
    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
    });
    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
    });
    child.on('close', (code) => {
      const json = parseScenarioOutput(stdout, scenario);
      resolve({
        code,
        stdout,
        stderr,
        json,
        scenario,
      });
    });
  });
}

(async () => {
  await fs.mkdir(artifactRoot, { recursive: true });

  const results = [];
  let overallStatus = 'PASS';
  let finalUrl = '';
  const scenariosToRun = resolveScenariosToRun();

  for (const scenario of scenariosToRun) {
    const result = await runScenario(scenario);
    results.push({
      id: scenario.id,
      title: scenario.title,
      status: result.json.status || (result.code === 0 ? 'PASS' : 'FAIL'),
      finalUrl: result.json.finalUrl || '',
      issues: result.json.issues || [],
    });
    finalUrl = result.json.finalUrl || finalUrl;

    if (result.code !== 0 || result.json.status === 'FAIL') {
      overallStatus = 'FAIL';
      break;
    }
    if (result.json.status === 'WARN' && overallStatus === 'PASS') {
      overallStatus = 'WARN';
    }
  }

  const output = {
    scriptId: 'full',
    status: overallStatus,
    baseUrl: process.env.BASE_URL || 'http://host.docker.internal:8032/apps/analytics/',
    finalUrl,
    reportName,
    groupName,
    artifactDir: artifactRoot,
    startAt: startAt || undefined,
    scenarios: results,
  };

  console.log(JSON.stringify(output, null, 2));
  if (overallStatus === 'FAIL') {
    process.exitCode = 1;
  }
})();
