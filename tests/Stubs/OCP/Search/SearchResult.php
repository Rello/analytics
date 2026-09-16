<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Search;

class SearchResult implements \JsonSerializable {
	private function __construct(private string $name, private array $entries) {
	}

	public static function complete(string $name, array $entries): self {
		return new self($name, $entries);
	}

	public function jsonSerialize(): array {
		return ['name' => $this->name, 'entries' => $this->entries, 'isPaginated' => false];
	}
}
