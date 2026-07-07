<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Security;

class ExternalUrlValidator {
	public static function validate(string $url): ?string {
		$url = trim($url);
		if ($url === '') {
			return 'External URL is empty';
		}

		$parts = parse_url($url);
		if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
			return 'External URL is invalid';
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
			return 'External URL host is not allowed';
		}

		$addresses = self::resolveHost($host);
		if ($addresses === []) {
			return 'External URL host could not be resolved';
		}

		foreach ($addresses as $address) {
			if (!self::isPublicIp($address)) {
				return 'External URL resolves to a private or reserved address';
			}
		}

		return null;
	}

	public static function isAllowed(string $url): bool {
		return self::validate($url) === null;
	}

	/**
	 * @return string[]
	 */
	private static function resolveHost(string $host): array {
		if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
			return [$host];
		}

		$addresses = [];
		$ipv4 = @gethostbynamel($host);
		if (is_array($ipv4)) {
			$addresses = array_merge($addresses, $ipv4);
		}

		$ipv6 = @dns_get_record($host, DNS_AAAA);
		if (is_array($ipv6)) {
			foreach ($ipv6 as $record) {
				if (isset($record['ipv6'])) {
					$addresses[] = $record['ipv6'];
				}
			}
		}

		return array_values(array_unique($addresses));
	}

	private static function isPublicIp(string $address): bool {
		if (filter_var($address, FILTER_VALIDATE_IP) === false) {
			return false;
		}

		return filter_var(
			$address,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) !== false;
	}
}
