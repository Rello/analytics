<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Security;

class DatasourceResultSanitizer {
	private const SENSITIVE_KEYS = [
		'rawdata',
		'customheaders',
		'headers',
		'requestheaders',
		'authorization',
		'auth',
		'token',
		'accesstoken',
		'password',
		'url',
	];

	public static function sanitize(array $result): array {
		foreach ($result as $key => $value) {
			if (self::isSensitiveKey($key)) {
				unset($result[$key]);
				continue;
			}

			if (is_array($value)) {
				$result[$key] = self::sanitize($value);
			}
		}

		return $result;
	}

	private static function isSensitiveKey($key): bool {
		if (!is_string($key)) {
			return false;
		}

		$normalized = strtolower($key);
		if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
			return true;
		}

		return str_contains($normalized, 'token')
			|| str_contains($normalized, 'authorization')
			|| str_contains($normalized, 'apikey')
			|| str_contains($normalized, 'api_key');
	}
}
