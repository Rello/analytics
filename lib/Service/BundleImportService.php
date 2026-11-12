<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Analytics\Service;

use OCA\Analytics\Db\StorageMapper;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IConfig;
use OCP\Lock\ILockingProvider;

/** Dispatches old report files and portable bundles through the normal import endpoint. */
class BundleImportService {
	public function __construct(
		private $userId,
		private ReportService $reports,
		private DatasetService $datasets,
		private PanoramaService $panoramas,
		private ThresholdService $thresholds,
		private StorageMapper $storage,
		private IRootFolder $rootFolder,
		private IDBConnection $db,
		private IConfig $config,
		private ILockingProvider $locking,
	) {}

	public function import(?string $path = null, ?string $raw = null): int|array {
		if ($path !== null && $path !== '') $raw = $this->rootFolder->getUserFolder($this->userId)->get($path)->getContent();
		if ($raw === null) return 0;
		$data = json_decode($raw, true);
		if (is_array($data) && array_key_exists('format', $data)) return $this->importBundle($data);
		return $this->reports->import(null, $raw);
	}

	public function installDemo(): array {
		$key = 'analytics-demo-' . $this->userId;
		$this->locking->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$id = (int)$this->config->getUserValue($this->userId, 'analytics', 'business-demo-panorama', '0');
			if ($id > 0 && $this->panoramas->isOwn($id)) return ['kind' => 'bundle', 'panoramaId' => $id, 'reused' => true];
			$raw = file_get_contents(__DIR__ . '/../../resources/demo.json');
			if ($raw === false) throw new \RuntimeException('Demo bundle unavailable');
			return $this->importBundle(json_decode($raw, true, 512, JSON_THROW_ON_ERROR), true);
		} finally {
			$this->locking->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}

	public function importBundle(array $bundle, bool $demo = false): array {
		BundleDefinition::validate($bundle);
		$folders = $datasets = $reports = $panoramas = [];
		$this->db->beginTransaction();
		try {
			foreach ($bundle['folders'] ?? [] as $folder) {
				$id = match ($folder['scope']) {
					'datasets' => $this->datasets->createGroup(),
					'panoramas' => $this->panoramas->createGroup(),
					'reports' => $this->reports->create($folder['name'], '', 0, 0, 0, '{}', 'table', '', '', '', ''),
				};
				$this->requireId($id);
				if ($folder['scope'] === 'datasets') $this->requireSuccess($this->datasets->rename($id, $folder['name']));
				if ($folder['scope'] === 'panoramas') $this->requireSuccess($this->panoramas->rename($id, $folder['name']));
				$folders[$folder['key']] = $id;
			}
			$definitions = [];
			foreach ($bundle['datasets'] ?? [] as $dataset) {
				$id = $this->datasets->create($dataset['name'], $dataset['dimension1'], $dataset['dimension2'], $dataset['value']);
				$this->requireId($id);
				if (isset($dataset['folder'])) $this->requireSuccess($this->datasets->updateGroup($id, $folders[$dataset['folder']]));
				foreach ($dataset['data'] ?? [] as $row) $this->storage->create($id, $row[0], $row[1], $row[2]);
				$datasets[$dataset['key']] = $id;
				$definitions[$dataset['key']] = $dataset;
			}
			foreach ($bundle['reports'] as $report) {
				$dataset = $definitions[$report['dataset']];
				$id = $this->reports->create($report['name'], $report['subheader'] ?? '', $folders[$report['folder'] ?? ''] ?? 0,
					2, $datasets[$report['dataset']], '{}', $report['visualization'], $report['chart'],
					$dataset['dimension1'], $dataset['dimension2'], $dataset['value']);
				$this->requireId($id);
				$json = static fn(string $field) => isset($report[$field]) ? json_encode($report[$field], JSON_THROW_ON_ERROR) : null;
				$this->requireSuccess($this->reports->updateOptions($id, $json('chartoptions'), $json('dataoptions'), $json('filteroptions'), $json('tableoptions')));
				foreach ($report['thresholds'] ?? [] as $threshold) {
					$this->requireId($this->thresholds->create($id, $threshold['dimension'], $threshold['option'], $threshold['value'], $threshold['severity'], $threshold['coloring']));
				}
				$reports[$report['key']] = $id;
			}
			foreach ($bundle['panoramas'] ?? [] as $panorama) {
				$parent = $folders[$panorama['folder'] ?? ''] ?? 0;
				$id = $this->panoramas->create(1, $parent);
				$this->requireId($id);
				$pages = $panorama['pages'];
				foreach ($pages as $index => &$page) {
					$page['page'] = $index;
					foreach ($page['reports'] as &$tile) {
						if ($tile['type'] === 0) $tile['value'] = $reports[$tile['value']];
					}
					unset($tile);
				}
				unset($page);
				$this->requireSuccess($this->panoramas->update($id, $panorama['name'], 1, $parent, $pages, BundleDefinition::mapFilters($panorama['filters'] ?? [], $reports)));
				$panoramas[$panorama['key']] = $id;
			}
			$entry = $panoramas[$bundle['entrypoint'] ?? ''] ?? null;
			if ($demo) {
				$this->requireId($entry ?? 0);
				$this->config->setUserValue($this->userId, 'analytics', 'business-demo-panorama', (string)$entry);
			}
			$this->db->commit();
			return ['kind' => 'bundle', 'folders' => $folders, 'datasets' => $datasets, 'reports' => $reports, 'panoramas' => $panoramas, 'panoramaId' => $entry];
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function requireId(int $id): void {
		if ($id <= 0) throw new \RuntimeException('Could not create bundle object');
	}

	private function requireSuccess(mixed $result): void {
		if ($result !== true) throw new \RuntimeException('Could not configure bundle object');
	}
}
