<?php
namespace OCP\UserMigration;

interface IExportDestination {
	public function addFileContents(string $path, string $contents): void;
}
