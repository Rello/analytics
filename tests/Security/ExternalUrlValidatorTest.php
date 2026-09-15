<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Security;

use OCA\Analytics\Security\ExternalUrlValidator;
use OCP\Security\IRemoteHostValidator;
use PHPUnit\Framework\TestCase;

class ExternalUrlValidatorTest extends TestCase {
	private IRemoteHostValidator $remoteHostValidator;

	protected function setUp(): void {
		parent::setUp();
		$this->remoteHostValidator = $this->createMock(IRemoteHostValidator::class);
		$this->remoteHostValidator->method('isValid')->willReturn(false);
	}

	/**
	 * @dataProvider blockedUrls
	 */
	public function testValidateRejectsPrivateAndReservedTargets(string $url): void {
		$this->assertNotNull((new ExternalUrlValidator($this->remoteHostValidator))->validate($url));
	}

	public function blockedUrls(): array {
		return [
			'localhost' => ['http://localhost/status'],
			'loopback' => ['http://127.0.0.1/status'],
			'private ipv4' => ['http://192.168.1.10/status'],
			'link local' => ['http://169.254.169.254/latest/meta-data'],
			'ipv6 loopback' => ['http://[::1]/status'],
			'ipv4-mapped ipv6 loopback' => ['http://[::ffff:127.0.0.1]/status'],
			'ipv4-mapped ipv6 metadata' => ['http://[::ffff:169.254.169.254]/latest/meta-data'],
			'userinfo' => ['https://user:password@example.com/data'],
			'file scheme' => ['file:///etc/passwd'],
		];
	}

	public function testValidateUsesNextcloudRemoteHostPolicy(): void {
		$remoteHostValidator = $this->createMock(IRemoteHostValidator::class);
		$remoteHostValidator
			->expects($this->once())
			->method('isValid')
			->with('10.0.0.150')
			->willReturn(true);

		$this->assertNull((new ExternalUrlValidator($remoteHostValidator))->validate('http://10.0.0.150/em1data/0/data.csv'));
	}

	public function testValidateDescribesBlockedInternalUrl(): void {
		$this->assertSame(
			'Internal URL is not allowed by server configuration',
			(new ExternalUrlValidator($this->remoteHostValidator))->validate('http://10.0.0.150/em1data/0/data.csv')
		);
	}
}
