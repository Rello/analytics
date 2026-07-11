<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Security;

use OCP\Http\Client\IClientService;

class ExternalHttpClient {
	public const CONNECT_TIMEOUT_SECONDS = 10;
	public const TIMEOUT_SECONDS = 60;

	public function __construct(private IClientService $clientService) {
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
		$urlError = ExternalUrlValidator::validate($url);
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
			return ['status' => 0, 'body' => '', 'error' => 'External request failed'];
		}
	}
}
