<?php
namespace OCP\UserMigration;

interface IImportSource {
	public function pathExists(string $path): bool;

	public function getFileContents(string $path): string;

	public function getMigratorVersion(string $id): ?int;
}
