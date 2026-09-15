<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Security;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class ExternalHttpClient {
	public const CONNECT_TIMEOUT_SECONDS = 10;
	public const TIMEOUT_SECONDS = 60;

	public function __construct(
		private IClientService $clientService,
		private ExternalUrlValidator $urlValidator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<string, string> $headers
	 * @return array{status: int, body: string, error: string|null}
	 */
	public function request(
		string $url,
		string $method = 'GET',
		array $headers = [],
		?string $body = null,
		?string $basicAuth = null,
	): array {
		$urlError = $this->urlValidator->validate($url);
		if ($urlError !== null) {
			return ['status' => 0, 'body' => '', 'error' => $urlError];
		}

		foreach ($headers as $name => $value) {
			if (!is_string($name) || preg_match('/[\r\n:]/', $name) || preg_match('/[\r\n]/', $value)) {
				return ['status' => 0, 'body' => '', 'error' => 'External request headers are invalid'];
			}
		}

		$options = [
			'allow_redirects' => false,
			'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
			'timeout' => self::TIMEOUT_SECONDS,
			'verify' => true,
			'headers' => $headers,
		];
		if ($body !== null) {
			$options['body'] = $body;
		}
		if ($basicAuth !== null && $basicAuth !== '') {
			if (strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
				return ['status' => 0, 'body' => '', 'error' => 'Basic Authentication requires an HTTPS URL'];
			}
			[$username, $password] = array_pad(explode(':', $basicAuth, 2), 2, '');
			$options['auth'] = [$username, $password];
		}

		try {
			$response = $this->clientService->newClient()->request(strtoupper($method), $url, $options);
			return [
				'status' => $response->getStatusCode(),
				'body' => (string)$response->getBody(),
				'error' => null,
			];
		} catch (\Throwable $e) {
			$safeUrl = $this->getSafeUrlForLogging($url);
			$exceptionMessage = str_replace($url, $safeUrl, $e->getMessage());
			$this->logger->error('Analytics external request failed: ' . $exceptionMessage, [
				'method' => strtoupper($method),
				'url' => $safeUrl,
				'exceptionClass' => $e::class,
				'exceptionCode' => $e->getCode(),
				'exceptionMessage' => $exceptionMessage,
			]);
			return ['status' => 0, 'body' => '', 'error' => 'External request failed'];
		}
	}

	private function getSafeUrlForLogging(string $url): string {
		$parts = parse_url($url);
		if (!is_array($parts) || !isset($parts['host'])) {
			return '[invalid URL]';
		}

		$scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
		$port = isset($parts['port']) ? ':' . $parts['port'] : '';
		return $scheme . $parts['host'] . $port;
	}
}
