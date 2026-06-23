<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Security;

use OCA\Analytics\Security\DatasourceResultSanitizer;
use PHPUnit\Framework\TestCase;

class DatasourceResultSanitizerTest extends TestCase {
	public function testSanitizeRemovesSensitiveDatasourceFieldsCaseInsensitively(): void {
		$result = DatasourceResultSanitizer::sanitize([
			'header' => ['Name', 'Value'],
			'data' => [['a', 1]],
			'rawData' => 'debug body',
			'rawdata' => 'debug body',
			'customHeaders' => ['Authorization: Bearer secret'],
			'Token' => 'secret',
			'URL' => 'http://example.test/private',
			'nested' => [
				'requestHeaders' => ['X-Api-Key: secret'],
				'Authorization' => 'Bearer secret',
				'keep' => 'value',
			],
		]);

		$this->assertSame(['Name', 'Value'], $result['header']);
		$this->assertSame([['a', 1]], $result['data']);
		$this->assertSame(['keep' => 'value'], $result['nested']);
		$this->assertArrayNotHasKey('rawData', $result);
		$this->assertArrayNotHasKey('rawdata', $result);
		$this->assertArrayNotHasKey('customHeaders', $result);
		$this->assertArrayNotHasKey('Token', $result);
		$this->assertArrayNotHasKey('URL', $result);
	}
}
