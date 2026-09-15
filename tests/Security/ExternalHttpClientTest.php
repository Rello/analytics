<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Tests\Security;

use OCA\Analytics\Security\ExternalHttpClient;
use OCA\Analytics\Security\ExternalUrlValidator;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

		$urlValidator = $this->createMock(ExternalUrlValidator::class);
		$urlValidator->method('validate')->willReturn(null);

		$result = (new ExternalHttpClient($service, $urlValidator, new NullLogger()))->request('https://93.184.216.34/data');

		$this->assertSame(200, $result['status']);
		$this->assertSame('{"ok":true}', $result['body']);
		$this->assertNull($result['error']);
		$this->assertFalse($captured['options']['allow_redirects']);
		$this->assertTrue($captured['options']['verify']);
		$this->assertSame(10, $captured['options']['connect_timeout']);
		$this->assertSame(60, $captured['options']['timeout']);
		$this->assertArrayNotHasKey('stream', $captured['options']);
	}

	public function testRequestRejectsBasicAuthenticationOverHttp(): void {
		$service = $this->createMock(IClientService::class);
		$service->expects($this->never())->method('newClient');

		$urlValidator = $this->createMock(ExternalUrlValidator::class);
		$urlValidator->method('validate')->willReturn(null);

		$result = (new ExternalHttpClient($service, $urlValidator, new NullLogger()))
			->request('http://example.test/data', 'GET', [], null, 'user:password');

		$this->assertSame(0, $result['status']);
		$this->assertSame('', $result['body']);
		$this->assertSame('Basic Authentication requires an HTTPS URL', $result['error']);
	}

	public function testRequestLogsSanitizedTransportFailure(): void {
		$client = new class() {
			public function request(string $method, string $url, array $options): object {
				throw new \RuntimeException('cURL error 28: Connection timed out for ' . $url);
			}
		};
		$service = new class($client) implements IClientService {
			public function __construct(private object $client) {
			}

			public function newClient(): object {
				return $this->client;
			}
		};
		$urlValidator = $this->createMock(ExternalUrlValidator::class);
		$urlValidator->method('validate')->willReturn(null);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('error')
			->with(
				$this->callback(function (string $message): bool {
					$this->assertStringContainsString('Analytics external request failed: cURL error 28', $message);
					$this->assertStringContainsString('https://example.test:8443', $message);
					$this->assertStringNotContainsString('secret-token', $message);
					$this->assertStringNotContainsString('/private/path', $message);
					return true;
				}),
				$this->callback(function (array $context): bool {
					$this->assertSame('GET', $context['method']);
					$this->assertSame('https://example.test:8443', $context['url']);
					$this->assertSame(\RuntimeException::class, $context['exceptionClass']);
					$this->assertStringContainsString('Connection timed out', $context['exceptionMessage']);
					$this->assertStringNotContainsString('secret-token', $context['exceptionMessage']);
					$this->assertStringNotContainsString('/private/path', $context['exceptionMessage']);
					return true;
				})
			);

		$result = (new ExternalHttpClient($service, $urlValidator, $logger))
			->request('https://example.test:8443/private/path?token=secret-token');

		$this->assertSame(0, $result['status']);
		$this->assertSame('', $result['body']);
		$this->assertSame('External request failed', $result['error']);
	}
}
