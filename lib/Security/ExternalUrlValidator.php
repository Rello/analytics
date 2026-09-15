<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Security;

use OCP\Security\IRemoteHostValidator;

class ExternalUrlValidator {
	public function __construct(private IRemoteHostValidator $remoteHostValidator) {
	}

	public function validate(string $url): ?string {
		$url = trim($url);
		if ($url === '') {
			return 'External URL is empty';
		}

		$parts = parse_url($url);
		if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
			return 'External URL is invalid';
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			return 'Credentials in external URLs are not allowed';
		}

		$scheme = strtolower((string)$parts['scheme']);
		if (!in_array($scheme, ['http', 'https'], true)) {
			return 'External URL scheme is not allowed';
		}

		$host = strtolower(rtrim((string)$parts['host'], '.'));
		if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
			$host = substr($host, 1, -1);
		}
		if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
			return 'Internal URL is not allowed';
		}

		if (!$this->remoteHostValidator->isValid($host)) {
			return 'Internal URL is not allowed by server configuration';
		}

		return null;
	}

	public function isAllowed(string $url): bool {
		return $this->validate($url) === null;
	}
}
