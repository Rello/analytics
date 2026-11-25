<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Service;

use OCA\Analytics\Controller\DatasourceController;
use OCA\Analytics\Exception\FlexibleStorageException;
use OCP\IDBConnection;

/** Native configuration boundary for authenticated OCS clients. */
class ApiConfigurationService {
	private const CHART_TYPES = ['', 'column', 'columnSt', 'columnSt100', 'line', 'datetime', 'area', 'doughnut', 'funnel'];
	private const OPTIONS = ['chartoptions', 'dataoptions', 'filteroptions', 'tableoptions'];
	// Only these known non-credential fields are returned. Unknown provider settings are write-only.
	private const SAFE_SOURCE = ['link', 'url', 'path', 'delimiter', 'offset', 'columns', 'timestamp', 'hasHeader', 'range', 'method', 'content-type', 'user', 'repository', 'data', 'limit', 'filter', 'showAssets', 'sheet'];

	public function __construct(
		private ReportService $reports,
		private DatasetService $datasets,
		private DataloadService $dataloads,
		private PanoramaService $panoramas,
		private DatasourceController $sources,
		private IDBConnection $db,
	) {}

	public function providers(): array {
		return $this->sources->index();
	}

	public function report(int $id): array {
		$r = $this->ownReport($id);
		$result = $this->only($r, ['id', 'name', 'subheader', 'parent', 'type', 'dataset', 'visualization', 'chart', 'refresh', 'dimension1', 'dimension2', 'value', 'version']);
		foreach (self::OPTIONS as $field) $result[$field] = (is_string($r[$field] ?? null) ? json_decode($r[$field]) : ($r[$field] ?? null));
		$result['link'] = $this->safeSource($this->sourceDecode($r['link'] ?? '{}'));
		$result['url'] = '/apps/analytics/r/' . $id;
		return $result;
	}

	public function saveReport(array $input, ?int $id = null): array {
		$this->fields($input, array_merge(['name', 'subheader', 'parent', 'type', 'dataset', 'link', 'visualization', 'chart', 'refresh', 'dimension1', 'dimension2', 'value'], self::OPTIONS));
		if ($id === null) $this->check(array_key_exists('name', $input), 'name is required');
		$old = $id === null ? [] : $this->ownReport($id);
		$r = array_replace(['name' => '', 'subheader' => '', 'parent' => 0, 'type' => 2, 'dataset' => 0, 'visualization' => 'ct', 'chart' => 'column', 'refresh' => 0, 'dimension1' => 'Object', 'dimension2' => 'Date', 'value' => 'Value'], $old, $input);
		$this->textFields($r, ['name', 'subheader', 'dimension1', 'dimension2', 'value']);
		$this->name($r['name']);
		foreach (['parent', 'type', 'dataset', 'refresh'] as $key) $this->integer($r[$key], $key);
		$this->check(is_string($r['visualization']), 'Invalid visualization');
		$this->check(in_array($r['chart'], self::CHART_TYPES, true), 'Invalid chart: use column, columnSt, columnSt100, line, datetime, area, doughnut, funnel, or an empty string (default column). For pie charts use doughnut with chartoptions.cutout = 0.');
		if ($id !== null) {
			$this->check((int)$old['type'] === $r['type'] && (int)$old['dataset'] === $r['dataset'], 'Existing reports cannot change type or dataset');
		} elseif ($r['type'] !== 2) {
			$this->provider($r['type']);
		}
		$options = [];
		foreach (self::OPTIONS as $field) {
			$options[$field] = array_key_exists($field, $input) ? $this->option($input[$field], $field) : ($old[$field] ?? null);
		}
		$source = $this->sourceWrite($input, 'link', $old['link'] ?? '{}');
		return $this->transaction(function () use ($id, $r, $options, $source) {
			if ($id === null) {
				$id = $this->reports->create($r['name'], $r['subheader'], $r['parent'], $r['type'], $r['dataset'], '{}', $r['visualization'], $r['chart'], $r['dimension1'], $r['dimension2'], $r['value']);
				$this->check($id > 0, 'Invalid report parent or dataset');
				// Create the blank resource first, then apply native source settings using
				// the existing update operation. This keeps the original create service's
				// source-option loop from overwriting the value-column label.
				if ($source !== '{}') $this->check($this->reports->update($id, $r['name'], $r['subheader'], $r['parent'], $source, $r['visualization'], $r['chart'], $options['chartoptions'], $options['dataoptions'], $r['dimension1'], $r['dimension2'], $r['value']), 'Report source configuration rejected');
			} else {
				$this->check($this->reports->update($id, $r['name'], $r['subheader'], $r['parent'], $source, $r['visualization'], $r['chart'], $options['chartoptions'], $options['dataoptions'], $r['dimension1'], $r['dimension2'], $r['value']), 'Report update rejected');
			}
			$this->check($this->reports->updateOptions($id, $options['chartoptions'], $options['dataoptions'], $options['filteroptions'], $options['tableoptions']), 'Report options rejected');
			$this->check($this->reports->updateRefresh($id, $r['refresh']), 'Report refresh rejected');
			return $this->report($id);
		});
	}

	public function dataset(int $id): array {
		$d = $this->datasets->readOwn($id);
		if (empty($d) || (int)($d['type'] ?? 2) === 0) $this->missing();
		return $this->only($d, ['id', 'name', 'subheader', 'dimension1', 'dimension2', 'value', 'ai_index', 'storageMode', 'schemaVersion', 'columns']);
	}

	public function saveDataset(array $input, ?int $id = null): array {
		$this->fields($input, ['name', 'subheader', 'dimension1', 'dimension2', 'value', 'storageMode', 'columns', 'expectedSchemaVersion']);
		if ($id === null) $this->check(array_key_exists('name', $input), 'name is required');
		$old = $id === null ? [] : $this->dataset($id);
		$d = array_replace(['name' => '', 'subheader' => '', 'dimension1' => 'Object', 'dimension2' => 'Date', 'value' => 'Value', 'storageMode' => 'legacy'], $old, $input);
		$this->name($d['name']);
		$this->textFields($d, ['subheader', 'dimension1', 'dimension2', 'value']);
		$this->check(in_array($d['storageMode'], ['legacy', 'flexible_shared'], true), 'Invalid storageMode');
		$this->check($id === null || $d['storageMode'] === ($old['storageMode'] ?? 'legacy'), 'Cannot change storageMode');
		if ($d['storageMode'] === 'flexible_shared') {
			$this->check(!array_intersect(array_keys($input), ['subheader', 'dimension1', 'dimension2', 'value']), 'Flexible datasets use columns');
			$this->check(is_array($d['columns'] ?? null) && array_is_list($d['columns']), 'columns must be a list');
			if ($id === null) $id = (int)$this->datasets->createFlexible($d['name'], $d['columns'])['id'];
			else {
				$this->check(isset($input['expectedSchemaVersion']), 'expectedSchemaVersion required for flexible updates');
				$this->integer($input['expectedSchemaVersion'], 'expectedSchemaVersion', 1);
				$this->datasets->updateFlexibleSchema($id, $input['expectedSchemaVersion'], $d['columns'], $d['name']);
			}
		} else {
			$this->check(!array_intersect(array_keys($input), ['columns', 'expectedSchemaVersion']), 'Legacy datasets use column labels');
			if ($id === null) {
				return $this->transaction(function () use ($d, $input) {
					$id = $this->datasets->create($d['name'], $d['dimension1'], $d['dimension2'], $d['value']);
					$this->check($id > 0, 'Dataset creation rejected');
					if (array_key_exists('subheader', $input)) $this->check($this->datasets->update($id, $d['name'], $d['subheader'], $d['dimension1'], $d['dimension2'], $d['value'], 0), 'Dataset metadata rejected');
					return $this->dataset($id);
				});
			} else $this->check($this->datasets->update($id, $d['name'], $d['subheader'], $d['dimension1'], $d['dimension2'], $d['value'], (int)($old['ai_index'] ?? 0)), 'Dataset update rejected');
		}
		return $this->dataset($id);
	}

	public function loads(int $datasetId): array {
		$this->dataset($datasetId);
		return array_map(fn($d) => $this->loadMetadata($d), $this->dataloads->read($datasetId));
	}

	public function load(int $id): array {
		return $this->loadMetadata($this->ownLoad($id));
	}

	public function saveLoad(array $input, ?int $id = null): array {
		$this->fields($input, ['name', 'dataset', 'datasource', 'option', 'schedule', 'storageMapping']);
		$old = $id === null ? [] : $this->ownLoad($id);
		$d = array_replace(['name' => 'New', 'dataset' => 0, 'datasource' => 0, 'schedule' => ''], $old ? array_replace($old, ['dataset' => (int)$old['dataset'], 'datasource' => (int)$old['datasource']]) : [], $input);
		$this->name($d['name']);
		$this->integer($d['dataset'], 'dataset', 1);
		$this->integer($d['datasource'], 'datasource', 1);
		$this->dataset($d['dataset']);
		$this->provider($d['datasource']);
		$this->check($id === null || ((int)$old['dataset'] === $d['dataset'] && (int)$old['datasource'] === $d['datasource']), 'Dataload dataset and provider are immutable');
		$this->check($d['schedule'] === null || is_string($d['schedule']), 'Invalid schedule');
		$option = $this->sourceWrite($input, 'option', $old['option'] ?? '{}');
		$mapping = array_key_exists('storageMapping', $input) ? $this->option($input['storageMapping'], 'storageMapping') : ($old['storage_mapping'] ?? null);
		// The existing service uses null to preserve mappings; {} explicitly clears them.
		if (array_key_exists('storageMapping', $input) && $mapping === null) $mapping = '{}';
		return $this->transaction(function () use ($id, $d, $option, $mapping) {
			if ($id === null) $id = $this->dataloads->create($d['dataset'], $d['datasource']);
			$this->check($id > 0 && $this->dataloads->update($id, $d['name'], $option, $d['schedule'], $mapping), 'Dataload configuration rejected');
			return $this->load($id);
		});
	}

	public function panoramaIndex(): array {
		return array_values(array_map(fn($p) => $this->only($p, ['id', 'name', 'parent', 'permissions']), array_filter($this->panoramas->index(), fn($p) => (int)$p['type'] !== 0)));
	}

	public function panorama(int $id): array {
		$p = $this->panoramas->read($id);
		if (empty($p) || (int)$p['type'] === 0) $this->missing();
		$result = $this->only($p, ['id', 'name', 'parent', 'permissions']);
		$result['parent'] = (int)($result['parent'] ?? 0);
		$result['pages'] = $this->decode($p['pages'] ?? '[]') ?? [];
		$result['filters'] = $this->decode($p['filters'] ?? '[]') ?? [];
		$result['url'] = '/apps/analytics/pa/' . $id;
		return $result;
	}

	public function savePanorama(array $input, ?int $id = null): array {
		$this->fields($input, ['name', 'parent', 'pages', 'filters']);
		if ($id === null) $this->check(array_key_exists('name', $input), 'name is required');
		if ($id !== null && !$this->panoramas->isOwn($id)) $this->missing();
		$old = $id === null ? [] : $this->panorama($id);
		$p = array_replace(['name' => '', 'parent' => 0, 'pages' => [], 'filters' => []], $old, $input);
		$this->name($p['name']);
		$this->integer($p['parent'], 'parent');
		$this->check(is_array($p['pages']), 'pages must be an array');
		$filters = array_key_exists('filters', $input) ? ($input['filters'] ?? []) : ($id === null ? [] : null);
		return $this->transaction(function () use ($id, $p, $filters) {
			if ($id === null) $id = $this->panoramas->create(1, $p['parent']);
			$this->check($id > 0 && $this->panoramas->update($id, $p['name'], 1, $p['parent'], $p['pages'], $filters), 'Panorama configuration rejected');
			return $this->panorama($id);
		});
	}

	private function ownReport(int $id): array {
		$r = $this->reports->read($id, false);
		if (empty($r) || (int)$r['type'] === 0) $this->missing();
		return $r;
	}

	private function ownLoad(int $id): array {
		$d = $this->dataloads->readOwnConfiguration($id);
		if (empty($d)) $this->missing();
		$this->dataset((int)$d['dataset']);
		return $d;
	}

	private function loadMetadata(array $d): array {
		$r = $this->only($d, ['id', 'name', 'dataset', 'datasource', 'schedule']);
		foreach (['id', 'dataset', 'datasource'] as $field) $r[$field] = (int)$r[$field];
		$r['option'] = $this->safeSource($this->sourceDecode($d['option'] ?? '{}'));
		$r['storageMapping'] = $this->decode($d['storage_mapping'] ?? null);
		return $r;
	}

	private function provider(int $id): void {
		$this->check(isset($this->providers()['datasources'][$id]), 'Datasource provider unavailable');
	}

	private function safeSource(array $source): object {
		return (object)array_filter($this->only($source, self::SAFE_SOURCE), static function ($value) {
			if (!is_scalar($value)) return false;
			if (!str_contains((string)$value, '://')) return true;
			$url = parse_url((string)$value);
			return is_array($url) && !isset($url['user']) && !isset($url['pass']) && !isset($url['query']) && !isset($url['fragment']);
		});
	}

	private function sourceDecode(mixed $saved): array {
		$source = $this->decode($saved);
		if (!is_array($source)) return is_string($saved) && $saved !== '' ? ['link' => $saved] : [];
		return array_map(static fn($v) => is_string($v) ? htmlspecialchars_decode($v, ENT_NOQUOTES) : $v, $source);
	}

	private function sourceWrite(array $input, string $field, mixed $saved): string {
		$old = $this->sourceDecode($saved);
		if (!array_key_exists($field, $input)) return json_encode((object)$old, JSON_THROW_ON_ERROR);
		$value = $input[$field];
		$this->option($value, $field);
		$this->check($value === null || (is_array($value) && (!array_is_list($value) || $value === [])), $field . ' must be an object');
		// Source options replace visible settings; omitted write-only settings survive read/edit cycles.
		$hidden = array_diff_key($old, (array)$this->safeSource($old));
		$value = $value === null ? [] : array_replace($hidden, $value);
		foreach ($value as $v) $this->check(is_scalar($v) || $v === null, 'Source settings must be scalar');
		$value = array_map(static fn($v) => $v === null ? '' : (is_bool($v) ? ($v ? 'true' : 'false') : (string)$v), $value);
		return json_encode((object)$value, JSON_THROW_ON_ERROR);
	}

	private function option(mixed $value, string $field): ?string {
		$this->check($value === null || is_array($value), $field . ' must be a JSON object or array, or null');
		return $value === null ? null : json_encode($value === [] && $field !== 'dataoptions' ? (object)[] : $value, JSON_THROW_ON_ERROR);
	}

	private function decode(mixed $value): mixed {
		return is_string($value) ? json_decode($value, true) : $value;
	}

	private function fields(array $input, array $allowed): void {
		$this->check(!array_diff(array_keys($input), $allowed), 'Unknown configuration field');
	}

	private function textFields(array $input, array $fields): void {
		foreach ($fields as $key) $this->check(($key === 'subheader' && $input[$key] === null) || (is_string($input[$key])), 'Invalid ' . $key);
	}

	private function name(mixed $name): void {
		$this->check(is_string($name), 'name must be a string');
	}

	private function integer(mixed $value, string $field, int $min = 0): void {
		$this->check(is_int($value) && $value >= $min, 'Invalid integer: ' . $field);
	}

	private function only(array $value, array $keys): array {
		return array_intersect_key($value, array_flip($keys));
	}

	private function check(bool $ok, string $message): void {
		if (!$ok) throw new \InvalidArgumentException($message);
	}

	private function missing(): never {
		throw new FlexibleStorageException('NOT_FOUND', 'Object not found', [], 404);
	}

	private function transaction(callable $operation): array {
		$this->db->beginTransaction();
		try {
			$result = $operation();
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
