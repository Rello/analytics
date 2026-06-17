<?php
namespace OCA\Analytics\Tests\UserMigration;

use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCA\Analytics\UserMigration\AnalyticsMigrator;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\UserMigrationException;
use PHPUnit\Framework\TestCase;

class AnalyticsMigratorTest extends TestCase {
	public function testCanImportAllowsMissingOptionalMigratorVersion(): void {
		$importSource = $this->createImportSourceWithVersion(null);

		$this->assertTrue($this->createMigrator()->canImport($importSource));
	}

	/**
	 * @dataProvider migratorVersions
	 */
	public function testCanImportChecksMigratorVersion(?int $version, bool $expected): void {
		$importSource = $this->createImportSourceWithVersion($version);

		$this->assertSame($expected, $this->createMigrator()->canImport($importSource));
	}

	public function testCanImportRejectsUnreadableMigratorVersion(): void {
		$importSource = $this->createMock(IImportSource::class);
		$importSource->expects($this->once())
			->method('getMigratorVersion')
			->with('analytics')
			->willThrowException(new UserMigrationException('Unreadable migration metadata'));

		$this->assertFalse($this->createMigrator()->canImport($importSource));
	}

	public function testNormalizeNullableIntPreservesNull(): void {
		$migrator = $this->createMigrator();

		$method = new \ReflectionMethod(AnalyticsMigrator::class, 'normalizeNullableInt');
		$method->setAccessible(true);

		$this->assertNull($method->invoke($migrator, null));
		$this->assertNull($method->invoke($migrator, ''));
		$this->assertSame(2, $method->invoke($migrator, 2));
		$this->assertSame(2, $method->invoke($migrator, '2'));
	}

	public function migratorVersions(): array {
		return [
			'older version' => [0, true],
			'current version' => [1, true],
			'newer version' => [2, false],
		];
	}

	private function createMigrator(): AnalyticsMigrator {
		return new AnalyticsMigrator(
			$this->createMock(\OCP\IDBConnection::class),
			new FakeL10N()
		);
	}

	private function createImportSourceWithVersion(?int $version): IImportSource {
		$importSource = $this->createMock(IImportSource::class);
		$importSource->expects($this->once())
			->method('getMigratorVersion')
			->with('analytics')
			->willReturn($version);

		return $importSource;
	}
}
