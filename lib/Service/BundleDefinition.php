<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Service;

use InvalidArgumentException;

/** Portable v1 bundles contain classic stored datasets, reports and report/text Panorama tiles. */
class BundleDefinition {
	public static function validate(array $bundle): void {
		self::check(($bundle['format'] ?? null) === 'analytics-bundle' && ($bundle['version'] ?? null) === 1, 'Unsupported bundle format or version');
		$maps = [];
		foreach (['folders', 'datasets', 'reports', 'panoramas'] as $kind) {
			$items = $bundle[$kind] ?? [];
			self::check(is_array($items) && array_is_list($items) && count($items) <= 500, 'Invalid bundle collection: ' . $kind);
			$maps[$kind] = [];
			foreach ($items as $item) {
				self::check(is_array($item), 'Invalid bundle object');
				$key = $item['key'] ?? null;
				self::check(is_string($key) && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/D', $key) && !isset($maps[$kind][$key]), 'Invalid or duplicate bundle key');
				self::check(is_string($item['name'] ?? null) && trim($item['name']) !== '' && strlen($item['name']) <= 64, 'Invalid object name');
				$maps[$kind][$key] = $item;
			}
		}
		self::check(count($maps['reports']) > 0, 'A bundle needs reports');
		foreach ($maps['folders'] as $key => $folder) {
			self::check(in_array($folder['scope'] ?? null, ['datasets', 'reports', 'panoramas'], true), 'Invalid folder scope');
			// v1 supports root folders only; never silently flatten a hierarchy.
			self::check(!isset($folder['parent']), 'Nested bundle folders are not supported in version 1');
		}
		foreach (['datasets', 'reports', 'panoramas'] as $kind) {
			foreach ($maps[$kind] as $item) {
				if (isset($item['folder'])) {
					self::check(is_string($item['folder']) && isset($maps['folders'][$item['folder']]) && $maps['folders'][$item['folder']]['scope'] === $kind, 'Unknown or incompatible folder');
				}
			}
		}
		foreach ($maps['datasets'] as $dataset) {
			self::check(($dataset['storageMode'] ?? 'legacy') === 'legacy' && !isset($dataset['columns']) && !isset($dataset['flexible']), 'Unsupported bundle dataset storage');
			foreach (['dimension1', 'dimension2', 'value'] as $field) self::check(is_string($dataset[$field] ?? null), 'Missing dataset column label');
			$rows = $dataset['data'] ?? [];
			self::check(is_array($rows) && array_is_list($rows) && count($rows) <= 100000, 'Invalid dataset rows');
			$seen = [];
			foreach ($rows as $row) {
				self::check(is_array($row) && array_is_list($row) && count($row) === 3, 'A classic dataset row needs three values');
				self::check(is_string($row[0]) && is_string($row[1]) && strlen($row[0]) <= 256 && strlen($row[1]) <= 256 && !str_contains($row[0] . $row[1], '*'), 'Invalid row dimensions');
				self::check((is_string($row[2]) || is_int($row[2]) || is_float($row[2])) && preg_match('/^-?\d{1,13}(?:\.\d{1,2})?$/D', (string)$row[2]), 'Classic dataset values need at most two decimal places');
				$key = json_encode([$row[0], $row[1]]);
				self::check(!isset($seen[$key]), 'Duplicate dataset row');
				$seen[$key] = true;
			}
		}
		foreach ($maps['reports'] as $report) {
			self::check(is_string($report['dataset'] ?? null) && isset($maps['datasets'][$report['dataset']]), 'Unknown bundle dataset');
			self::check(!isset($report['type']) || $report['type'] === 2, 'Bundle v1 supports stored reports only');
			self::check(!isset($report['link']) && !isset($report['dataload']), 'External sources and dataloads are not supported in bundle v1');
			self::check(in_array($report['visualization'] ?? null, ['table', 'chart', 'ct'], true), 'Invalid visualization');
			self::check(is_string($report['chart'] ?? null) && in_array($report['chart'], ['', 'column', 'columnSt', 'columnSt100', 'line', 'datetime', 'area', 'doughnut', 'funnel'], true), 'Invalid chart');
			self::check(!isset($report['subheader']) || is_string($report['subheader']), 'Invalid report subtitle');
			foreach (['chartoptions', 'dataoptions', 'filteroptions', 'tableoptions'] as $field) self::check(!isset($report[$field]) || is_array($report[$field]), 'Bundle report options must be JSON objects or arrays');
			$thresholds = $report['thresholds'] ?? [];
			self::check(is_array($thresholds) && array_is_list($thresholds), 'Invalid thresholds');
			foreach ($thresholds as $threshold) {
				self::check(is_array($threshold) && is_int($threshold['dimension'] ?? null) && $threshold['dimension'] >= 0 && $threshold['dimension'] <= 100, 'Invalid threshold dimension');
				self::check(in_array($threshold['severity'] ?? null, [2, 3, 4], true) && in_array($threshold['coloring'] ?? null, ['value', 'row'], true), 'Only display thresholds are supported');
				self::check(in_array($threshold['option'] ?? null, ['EQ', 'NE', 'GT', 'LT', 'GE', 'LE', '=', '!=', '>', '<', '>=', '<='], true) && is_numeric($threshold['value'] ?? null), 'Invalid threshold comparison');
			}
		}
		// Validate Panorama filters using temporary IDs; no source installation IDs are accepted.
		$reportIds = array_combine(array_keys($maps['reports']), range(1, count($maps['reports'])));
		foreach ($maps['panoramas'] as $panorama) {
			$pages = $panorama['pages'] ?? null;
			self::check(is_array($pages) && array_is_list($pages) && count($pages) > 0 && count($pages) <= 50, 'Invalid Panorama pages');
			foreach ($pages as &$page) {
				self::check(is_array($page) && is_string($page['name'] ?? null) && is_int($page['layoutId'] ?? null) && $page['layoutId'] >= 1 && $page['layoutId'] <= 6, 'Invalid Panorama layout');
				self::check(is_array($page['reports'] ?? null) && array_is_list($page['reports']) && count($page['reports']) <= [1 => 3, 2 => 3, 3 => 4, 4 => 6, 5 => 2, 6 => 1][$page['layoutId']], 'Invalid Panorama tiles');
				foreach ($page['reports'] as &$tile) {
					self::check(is_array($tile) && in_array($tile['type'] ?? null, [0, 1], true) && is_string($tile['value'] ?? null), 'Only portable report and text tiles are supported');
					if ($tile['type'] === 0) {
						self::check(isset($reportIds[$tile['value']]), 'Unknown Panorama report');
						$tile['value'] = $reportIds[$tile['value']];
					}
				}
				unset($tile);
			}
			unset($page);
			$filters = self::mapFilters($panorama['filters'] ?? [], $reportIds);
			PanoramaFilterService::normalize($filters, $pages);
		}
		if (isset($bundle['entrypoint'])) self::check(is_string($bundle['entrypoint']) && isset($maps['panoramas'][$bundle['entrypoint']]), 'Unknown entry Panorama');
	}

	public static function mapFilters(mixed $filters, array $reportIds): array {
		self::check(is_array($filters) && array_is_list($filters), 'Invalid Panorama filters');
		foreach ($filters as &$filter) {
			self::check(is_array($filter) && is_array($filter['mappings'] ?? null), 'Invalid filter mappings');
			foreach ($filter['mappings'] as &$mapping) {
				self::check(is_array($mapping) && is_string($mapping['reportId'] ?? null) && isset($reportIds[$mapping['reportId']]), 'Unknown filter report');
				$mapping['reportId'] = $reportIds[$mapping['reportId']];
			}
			unset($mapping);
		}
		unset($filter);
		return $filters;
	}

	private static function check(bool $condition, string $message): void {
		if (!$condition) throw new InvalidArgumentException($message);
	}
}
