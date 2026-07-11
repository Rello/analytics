<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Security;

use OCA\Analytics\Security\ExternalHttpClient;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\TestCase;

class ExternalHttpClientTest extends TestCase {
	public function testRequestUsesSecurityOptionsAndReturnsBody(): void {
		$captured = [];
		$response = new class() {
			public function getStatusCode(): int {
				return 200;
			}

			public function getBody(): string {
				return '{"ok":true}';
			}
		};
		$client = new class($response, $captured) {
			public array $captured;
			private object $response;

			public function __construct(object $response, array &$captured) {
				$this->response = $response;
				$this->captured =& $captured;
			}

			public function request(string $method, string $url, array $options): object {
				$this->captured = compact('method', 'url', 'options');
				return $this->response;
			}
		};
		$service = new class($client) implements IClientService {
			public function __construct(private object $client) {
			}

			public function newClient(): object {
				return $this->client;
			}
		};

		$result = (new ExternalHttpClient($service))->request('https://93.184.216.34/data');

		$this->assertSame(200, $result['status']);
		$this->assertSame('{"ok":true}', $result['body']);
		$this->assertNull($result['error']);
		$this->assertFalse($captured['options']['allow_redirects']);
		$this->assertTrue($captured['options']['verify']);
		$this->assertSame(10, $captured['options']['connect_timeout']);
		$this->assertSame(60, $captured['options']['timeout']);
		$this->assertArrayNotHasKey('stream', $captured['options']);
	}
}
