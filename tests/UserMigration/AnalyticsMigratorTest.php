<?php
namespace OCA\Analytics\Tests\UserMigration;

use OCA\Analytics\Tests\Stubs\FakeL10N;
use OCA\Analytics\UserMigration\AnalyticsMigrator;
use PHPUnit\Framework\TestCase;

class AnalyticsMigratorTest extends TestCase {
	public function testNormalizeNullableIntPreservesNull(): void {
		$migrator = new AnalyticsMigrator(
			$this->createMock(\OCP\IDBConnection::class),
			new FakeL10N()
		);

		$method = new \ReflectionMethod(AnalyticsMigrator::class, 'normalizeNullableInt');
		$method->setAccessible(true);

		$this->assertNull($method->invoke($migrator, null));
		$this->assertNull($method->invoke($migrator, ''));
		$this->assertSame(2, $method->invoke($migrator, 2));
		$this->assertSame(2, $method->invoke($migrator, '2'));
	}
}
