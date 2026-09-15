<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Service;

use InvalidArgumentException;

/** Pure validation and merging; viewer values never modify stored report options. */
class PanoramaFilterService {
	public static function normalize(mixed $filters, array $pages): array {
		if (is_string($filters)) {
			$filters = json_decode($filters, true);
		}
		if (!is_array($filters) || !array_is_list($filters)) {
			throw new InvalidArgumentException('Invalid panorama filters');
		}
		$reports = [];
		foreach ($pages as $page) {
			foreach ($page['reports'] ?? [] as $report) {
				if ((int)($report['type'] ?? -1) === 0) $reports[(int)$report['value']] = true;
			}
		}
		$ids = $targets = [];
		$result = [];
		foreach ($filters as $filter) {
			if (!is_array($filter) || !is_string($filter['id'] ?? null)
				|| !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $filter['id']) || isset($ids[$filter['id']])
				|| !is_string($filter['label'] ?? null) || trim($filter['label']) === ''
				|| !is_bool($filter['enabled'] ?? null)
				|| !in_array($filter['operator'] ?? null, ['EQ', 'GT', 'LT', 'LIKE', 'NOTLIKE', 'IN'], true)
				|| !is_array($filter['mappings'] ?? null) || !array_is_list($filter['mappings'])
				|| (isset($filter['defaultValue']) && !is_string($filter['defaultValue']))) {
				throw new InvalidArgumentException('Invalid panorama variable');
			}
			$ids[$filter['id']] = true;
			$mappings = [];
			foreach ($filter['mappings'] as $mapping) {
				if (!is_array($mapping) || !is_int($mapping['reportId'] ?? null)
					|| !isset($reports[$mapping['reportId']])
					|| !is_string($mapping['dimension'] ?? null) || $mapping['dimension'] === ''
					|| !is_string($mapping['dimensionLabel'] ?? null)) {
					throw new InvalidArgumentException('Invalid panorama dimension mapping');
				}
				$key = $mapping['reportId'] . ':' . $mapping['dimension'];
				if ($filter['enabled'] && isset($targets[$key])) {
					throw new InvalidArgumentException('A dimension can only belong to one enabled variable');
				}
				if ($filter['enabled']) $targets[$key] = true;
				$mappings[] = array_intersect_key($mapping, array_flip(['reportId', 'dimension', 'dimensionLabel']));
			}
			if ($filter['enabled'] && !$mappings) throw new InvalidArgumentException('Select a report dimension');
			$result[] = [
				'id' => $filter['id'], 'label' => trim($filter['label']), 'enabled' => $filter['enabled'],
				'operator' => $filter['operator'], 'defaultValue' => $filter['defaultValue'] ?? null,
				'mappings' => $mappings,
			];
		}
		return $result;
	}

	public static function apply(array $metadata, array $filters, mixed $values): array {
		if (is_string($values)) $values = json_decode($values, true);
		if (!is_array($values)) throw new InvalidArgumentException('Invalid panorama values');
		$enabled = [];
		foreach ($filters as $filter) {
			if ($filter['enabled']) $enabled[$filter['id']] = $filter;
		}
		$options = json_decode($metadata['filteroptions'] ?? '', true) ?: [];
		$conditions = [];
		foreach ($options['filter'] ?? [] as $key => $condition) {
			$condition['dimension'] = $condition['dimension'] ?? $key;
			$conditions[] = $condition;
		}
		foreach ($values as $id => $value) {
			if (!isset($enabled[$id])) {
				throw new InvalidArgumentException('Unknown or disabled panorama variable');
			}
			if ($value === null || $value === '') continue;
            // Older viewers send a value alone; newer viewers send report-style conditions.
            $selections = is_string($value) ? [['option' => $enabled[$id]['operator'], 'value' => $value]] : $value;
            if (!is_array($selections) || !array_is_list($selections)) {
                throw new InvalidArgumentException('Invalid panorama conditions');
            }
            foreach ($selections as $selection) {
                if (!is_array($selection) || !in_array($selection['option'] ?? null, ['EQ', 'GT', 'LT', 'LIKE', 'NOTLIKE', 'IN'], true)
                    || !is_string($selection['value'] ?? null)) {
                    throw new InvalidArgumentException('Invalid panorama condition');
                }
            }
            $selections = array_values(array_filter($selections, static fn($selection) => $selection['value'] !== ''));
            if (!$selections) continue;
			foreach ($enabled[$id]['mappings'] as $mapping) {
				if ($mapping['reportId'] !== (int)$metadata['id']) continue;
				$dimension = $mapping['dimension'];
				$conditions = array_values(array_filter($conditions,
					static fn($condition) => (string)$condition['dimension'] !== $dimension));
				foreach ($selections as $selection) {
					$conditions[] = ['dimension' => $dimension, 'option' => $selection['option'], 'value' => $selection['value']];
				}
				$metadata['panoramaMappings'][] = $mapping;
			}
		}
		if (!empty($metadata['panoramaMappings'])) {
			$options['filter'] = $conditions;
			$metadata['filteroptions'] = json_encode($options);
		}
		return $metadata;
	}

	public static function validateDimensions(array $mappings, array $dimensions): void {
		foreach ($mappings as $mapping) {
			if (!array_key_exists($mapping['dimension'], $dimensions)
				|| $dimensions[$mapping['dimension']] !== $mapping['dimensionLabel']) {
				throw new InvalidArgumentException('A panorama filter dimension has changed. Ask the owner to configure filters again.');
			}
		}
	}
}
