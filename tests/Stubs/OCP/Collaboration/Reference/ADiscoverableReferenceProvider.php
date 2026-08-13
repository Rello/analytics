<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Collaboration\Reference;

abstract class ADiscoverableReferenceProvider {
	abstract public function getId(): string;

	abstract public function getTitle(): string;

	abstract public function getOrder(): int;

	abstract public function getIconUrl(): string;

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'icon_url' => $this->getIconUrl(),
			'order' => $this->getOrder(),
		];
	}
}
