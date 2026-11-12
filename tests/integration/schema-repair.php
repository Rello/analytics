<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Run in a development Nextcloud container as www-data. Only in-memory schemas
 * are changed; no migration is executed against the live database.
 */
declare(strict_types=1);

require '/var/www/html/lib/base.php';
set_exception_handler(static function (Throwable $e): void {
	fwrite(STDERR, (string)$e . PHP_EOL);
	exit(1);
});

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use OC\DB\Connection;
use OC\DB\MigrationService;
use OC\DB\SchemaWrapper;
use OC\Migration\NullOutput;
use OCA\Analytics\Migration\Version7200Date20261108100000;
use OCP\DB\Types;
use OCP\Server;

$connection = Server::get(Connection::class);
$output = new NullOutput();
$schema = new Schema();
$wrapper = new SchemaWrapper($connection, $schema);
$closure = static fn () => $wrapper;
$check = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$textType = Type::getType(Types::TEXT);
$optionType = static fn () => $schema->getTable($connection->getPrefix() . 'analytics_dataload')->getColumn('option')->getType();

// Replay the schema-only migration chain as the schema checker does.
$service = new MigrationService('analytics', $connection, $output);
foreach ($service->getAvailableVersions() as $version) {
	$service->createInstance($version)->changeSchema($output, $closure, []);
	if ($version === '4013Date20240408180000') {
		$check($optionType() === $textType, 'Historical migration must expect text on this Nextcloud version');
	}
}
$check($optionType() === $textType, 'Fresh schema must use text');
$share = $wrapper->getTable('analytics_share');
$check(!$share->hasColumn('parent'), 'Fresh schema must not contain a share parent');

// Simulate upgraded installations with either string or text dataload options.
$repair = new Version7200Date20261108100000();
foreach ([Types::STRING, Types::TEXT] as $legacyType) {
	$share->addColumn('parent', Types::INTEGER, ['notnull' => false]);
	$wrapper->getTable('analytics_dataload')->getColumn('option')->setType(Type::getType($legacyType));
	$before = clone $schema->getTable($connection->getPrefix() . 'analytics_dataload')->getColumn('option');
	$repair->changeSchema($output, $closure, []);
	$check(!$share->hasColumn('parent'), 'Repair must remove the legacy share parent');
	$check($optionType() === $textType, 'Repair must ensure text options');
	$after = $schema->getTable($connection->getPrefix() . 'analytics_dataload')->getColumn('option');
	$check($before->getNotnull() === $after->getNotnull() && $before->getDefault() === $after->getDefault(), 'Repair must preserve nullability and default');
	$once = clone $schema;
	$repair->changeSchema($output, $closure, []);
	$diff = $connection->createSchemaManager()->createComparator()->compareSchemas($once, $schema);
	$check($diff->isEmpty(), 'Repair must be idempotent');
}

echo "Schema replay and legacy repair passed. No database changes made.\n";
