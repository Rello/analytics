<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\UserMigration;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\UserMigrationException;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyticsMigrator implements IMigrator {
	private const EXPORT_FILE = 'analytics/user-data.json';
	private const VERSION = 1;

	public function __construct(
		private IDBConnection $db,
		private IL10N $l10n,
	) {
	}

	public function export(IUser $user, IExportDestination $exportDestination, OutputInterface $output): void {
		$uid = $user->getUID();
		$output->writeln('<info>Exporting Analytics data</info>');

		$datasets = $this->fetchAllByUser('analytics_dataset', $uid);
		$datasetIds = array_map(static fn (array $row): int => (int)$row['id'], $datasets);

		$reports = $this->fetchAllByUser('analytics_report', $uid);
		$reportIds = array_map(static fn (array $row): int => (int)$row['id'], $reports);

		$payload = [
			'datasets' => $datasets,
			'reports' => $reports,
			'dataloads' => $this->fetchAllByIds('analytics_dataload', 'dataset', $datasetIds),
			'facts' => $this->fetchAllByIds('analytics_facts', 'dataset', $datasetIds),
			'thresholds' => $this->fetchAllByIds('analytics_threshold', 'report', $reportIds),
			'panoramas' => $this->fetchAllByUser('analytics_panorama', $uid),
		];

		$encoded = json_encode($payload, JSON_THROW_ON_ERROR);
		$exportDestination->addFileContents(self::EXPORT_FILE, $encoded);
	}

	public function import(IUser $user, IImportSource $importSource, OutputInterface $output): void {
		if (!$importSource->pathExists(self::EXPORT_FILE)) {
			$output->writeln('<comment>No Analytics data found in archive</comment>');
			return;
		}

		$raw = $importSource->getFileContents(self::EXPORT_FILE);
		$payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($payload)) {
			throw new UserMigrationException('Invalid Analytics migration payload');
		}

		$uid = $user->getUID();
		$datasetMap = [];
		$reportMap = [];

		$this->db->beginTransaction();
		try {
			$datasetMap = $this->importDatasets($uid, $payload['datasets'] ?? []);
			$reportMap = $this->importReports($uid, $payload['reports'] ?? [], $datasetMap);
			$this->importDataloads($uid, $payload['dataloads'] ?? [], $datasetMap);
			$this->importFacts($uid, $payload['facts'] ?? [], $datasetMap);
			$this->importThresholds($uid, $payload['thresholds'] ?? [], $reportMap);
			$this->importPanoramas($uid, $payload['panoramas'] ?? [], $reportMap);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw new UserMigrationException('Unable to import Analytics data', 0, $e);
		}
	}

	public function getId(): string {
		return 'analytics';
	}

	public function getDisplayName(): string {
		return $this->l10n->t('Analytics');
	}

	public function getDescription(): string {
		return $this->l10n->t('Reports, datasets and panoramas');
	}

	public function getVersion(): int {
		return self::VERSION;
	}

	public function canImport(IImportSource $importSource): bool {
		try {
			$version = $importSource->getMigratorVersion($this->getId());
		} catch (UserMigrationException) {
			return false;
		}

		if ($version === null) {
			return false;
		}

		return $version <= self::VERSION;
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	private function fetchAllByUser(string $table, string $uid): array {
		$sql = $this->db->getQueryBuilder();
		$sql->from($table)
			->select('*')
			->where($sql->expr()->eq('user_id', $sql->createNamedParameter($uid)));

		$statement = $sql->executeQuery();
		$result = $statement->fetchAll();
		$statement->closeCursor();

		return is_array($result) ? $result : [];
	}

	/**
	 * @param list<int> $ids
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	private function fetchAllByIds(string $table, string $idColumn, array $ids): array {
		if ($ids === []) {
			return [];
		}

		$sql = $this->db->getQueryBuilder();
		$sql->from($table)
			->select('*')
			->where($sql->expr()->in($idColumn, $sql->createParameter('ids')))
			->setParameter('ids', $ids, IQueryBuilder::PARAM_INT_ARRAY);

		$statement = $sql->executeQuery();
		$result = $statement->fetchAll();
		$statement->closeCursor();

		return is_array($result) ? $result : [];
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return array<int,int>
	 * @throws Exception
	 */
	private function importDatasets(string $uid, array $rows): array {
		$idMap = [];

		foreach ($rows as $row) {
			if (!isset($row['id'])) {
				continue;
			}

			$oldId = (int)$row['id'];
			$newId = $this->insertAndReturnId('analytics_dataset', [
				'user_id' => $uid,
				'name' => $row['name'] ?? '',
				'subheader' => $row['subheader'] ?? null,
				'dimension1' => $row['dimension1'] ?? '',
				'dimension2' => $row['dimension2'] ?? '',
				'value' => $row['value'] ?? '',
				'type' => isset($row['type']) ? (int)$row['type'] : 0,
				'parent' => 0,
				'ai_index' => isset($row['ai_index']) ? (int)$row['ai_index'] : 0,
			]);
			$idMap[$oldId] = $newId;
		}

		foreach ($rows as $row) {
			if (!isset($row['id']) || !isset($idMap[(int)$row['id']])) {
				continue;
			}

			$newId = $idMap[(int)$row['id']];
			$oldParent = isset($row['parent']) ? (int)$row['parent'] : 0;
			$newParent = $oldParent === 0 ? 0 : ($idMap[$oldParent] ?? 0);
			$this->updateById('analytics_dataset', $newId, ['parent' => $newParent]);
		}

		return $idMap;
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @param array<int,int> $datasetMap
	 * @return array<int,int>
	 * @throws Exception
	 */
	private function importReports(string $uid, array $rows, array $datasetMap): array {
		$idMap = [];

		foreach ($rows as $row) {
			if (!isset($row['id'])) {
				continue;
			}

			$oldId = (int)$row['id'];
			$oldDataset = isset($row['dataset']) ? (int)$row['dataset'] : 0;
			$newDataset = $oldDataset === 0 ? 0 : ($datasetMap[$oldDataset] ?? 0);

			$newId = $this->insertAndReturnId('analytics_report', [
				'user_id' => $uid,
				'dataset' => $newDataset,
				'name' => $row['name'] ?? '',
				'subheader' => $row['subheader'] ?? null,
				'link' => $row['link'] ?? '',
				'type' => isset($row['type']) ? (int)$row['type'] : 0,
				'parent' => 0,
				'dimension1' => $row['dimension1'] ?? '',
				'dimension2' => $row['dimension2'] ?? '',
				'value' => $row['value'] ?? '',
				'chart' => $row['chart'] ?? 'line',
				'visualization' => $row['visualization'] ?? 'table',
				'chartoptions' => $row['chartoptions'] ?? null,
				'dataoptions' => $row['dataoptions'] ?? null,
				'filteroptions' => $row['filteroptions'] ?? null,
				'tableoptions' => $row['tableoptions'] ?? null,
				'refresh' => isset($row['refresh']) ? (int)$row['refresh'] : 0,
				'version' => isset($row['version']) ? (int)$row['version'] : 0,
			]);
			$idMap[$oldId] = $newId;
		}

		foreach ($rows as $row) {
			if (!isset($row['id']) || !isset($idMap[(int)$row['id']])) {
				continue;
			}

			$newId = $idMap[(int)$row['id']];
			$oldParent = isset($row['parent']) ? (int)$row['parent'] : 0;
			$newParent = $oldParent === 0 ? 0 : ($idMap[$oldParent] ?? 0);
			$this->updateById('analytics_report', $newId, ['parent' => $newParent]);
		}

		return $idMap;
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @param array<int,int> $datasetMap
	 * @throws Exception
	 */
	private function importDataloads(string $uid, array $rows, array $datasetMap): void {
		foreach ($rows as $row) {
			$oldDataset = isset($row['dataset']) ? (int)$row['dataset'] : 0;
			$newDataset = $datasetMap[$oldDataset] ?? null;
			if ($newDataset === null) {
				continue;
			}

			$this->insertAndReturnId('analytics_dataload', [
				'user_id' => $uid,
				'name' => $row['name'] ?? '',
				'dataset' => $newDataset,
				'datasource' => isset($row['datasource']) ? (int)$row['datasource'] : 0,
				'option' => $row['option'] ?? '{}',
				'schedule' => $row['schedule'] ?? null,
			]);
		}
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @param array<int,int> $datasetMap
	 * @throws Exception
	 */
	private function importFacts(string $uid, array $rows, array $datasetMap): void {
		foreach ($rows as $row) {
			$oldDataset = isset($row['dataset']) ? (int)$row['dataset'] : 0;
			$newDataset = $datasetMap[$oldDataset] ?? null;
			if ($newDataset === null) {
				continue;
			}

			$this->insertAndReturnId('analytics_facts', [
				'user_id' => $uid,
				'dataset' => $newDataset,
				'dimension1' => $row['dimension1'] ?? null,
				'dimension2' => $row['dimension2'] ?? null,
				'value' => isset($row['value']) ? (float)$row['value'] : 0.0,
				'timestamp' => isset($row['timestamp']) ? (int)$row['timestamp'] : time(),
			]);
		}
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @param array<int,int> $reportMap
	 * @throws Exception
	 */
	private function importThresholds(string $uid, array $rows, array $reportMap): void {
		foreach ($rows as $row) {
			$oldReport = isset($row['report']) ? (int)$row['report'] : 0;
			$newReport = $reportMap[$oldReport] ?? null;
			if ($newReport === null) {
				continue;
			}

			$this->insertAndReturnId('analytics_threshold', [
				'user_id' => $uid,
				'report' => $newReport,
				'dimension' => $this->normalizeNullableInt($row['dimension'] ?? null),
				'target' => $row['target'] ?? 0,
				'option' => $row['option'] ?? '',
				'severity' => isset($row['severity']) ? (int)$row['severity'] : 0,
				'coloring' => $row['coloring'] ?? 'row',
				'sequence' => isset($row['sequence']) ? (int)$row['sequence'] : 1,
			]);
		}
	}

	/**
	 * Keep nullable integer fields nullable during import instead of coercing them to empty strings.
	 */
	private function normalizeNullableInt(mixed $value): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		return (int)$value;
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @param array<int,int> $reportMap
	 * @throws Exception
	 */
	private function importPanoramas(string $uid, array $rows, array $reportMap): void {
		$idMap = [];

		foreach ($rows as $row) {
			if (!isset($row['id'])) {
				continue;
			}

			$oldId = (int)$row['id'];
			$pages = $this->remapPanoramaPages((string)($row['pages'] ?? '[]'), $reportMap);

			$newId = $this->insertAndReturnId('analytics_panorama', [
				'user_id' => $uid,
				'name' => $row['name'] ?? '',
				'type' => isset($row['type']) ? (int)$row['type'] : 0,
				'parent' => 0,
				'pages' => $pages,
			]);
			$idMap[$oldId] = $newId;
		}

		foreach ($rows as $row) {
			if (!isset($row['id']) || !isset($idMap[(int)$row['id']])) {
				continue;
			}

			$newId = $idMap[(int)$row['id']];
			$oldParent = isset($row['parent']) ? (int)$row['parent'] : 0;
			$newParent = $oldParent === 0 ? 0 : ($idMap[$oldParent] ?? 0);
			$this->updateById('analytics_panorama', $newId, ['parent' => $newParent]);
		}
	}

	/**
	 * @param array<int,int> $reportMap
	 */
	private function remapPanoramaPages(string $pagesJson, array $reportMap): string {
		$pages = json_decode($pagesJson, true);
		if (!is_array($pages)) {
			return '[]';
		}

		foreach ($pages as &$page) {
			if (!isset($page['reports']) || !is_array($page['reports'])) {
				continue;
			}

			foreach ($page['reports'] as &$reportElement) {
				if (
					!is_array($reportElement) ||
					!isset($reportElement['type']) ||
					(int)$reportElement['type'] !== 0 ||
					!isset($reportElement['value'])
				) {
					continue;
				}

				$oldReportId = (int)$reportElement['value'];
				if (isset($reportMap[$oldReportId])) {
					$reportElement['value'] = $reportMap[$oldReportId];
				}
			}
			unset($reportElement);
		}
		unset($page);

		$encoded = json_encode($pages);
		return is_string($encoded) ? $encoded : '[]';
	}

	/**
	 * @param array<string,mixed> $values
	 * @throws Exception
	 */
	private function updateById(string $table, int $id, array $values): void {
		if ($values === []) {
			return;
		}

		$sql = $this->db->getQueryBuilder();
		$sql->update($table);

		foreach ($values as $column => $value) {
			$sql->set($column, $sql->createNamedParameter($value));
		}

		$sql->where($sql->expr()->eq('id', $sql->createNamedParameter($id)));
		$sql->executeStatement();
	}

	/**
	 * @param array<string,mixed> $values
	 * @throws Exception
	 */
	private function insertAndReturnId(string $table, array $values): int {
		$sql = $this->db->getQueryBuilder();
		$sql->insert($table);

		$preparedValues = [];
		foreach ($values as $column => $value) {
			$preparedValues[$column] = $sql->createNamedParameter($value);
		}
		$sql->values($preparedValues);
		$sql->executeStatement();

		return (int)$sql->getLastInsertId();
	}
}
