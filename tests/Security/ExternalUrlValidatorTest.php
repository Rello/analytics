<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Security;

use OCA\Analytics\Security\ExternalUrlValidator;
use PHPUnit\Framework\TestCase;

class ExternalUrlValidatorTest extends TestCase {
	/**
	 * @dataProvider blockedUrls
	 */
	public function testValidateRejectsPrivateAndReservedTargets(string $url): void {
		$this->assertNotNull(ExternalUrlValidator::validate($url));
	}

	public function blockedUrls(): array {
		return [
			'localhost' => ['http://localhost/status'],
			'loopback' => ['http://127.0.0.1/status'],
			'private ipv4' => ['http://192.168.1.10/status'],
			'link local' => ['http://169.254.169.254/latest/meta-data'],
			'ipv6 loopback' => ['http://[::1]/status'],
			'file scheme' => ['file:///etc/passwd'],
		];
	}
}
