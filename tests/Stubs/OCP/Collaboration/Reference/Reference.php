<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Collaboration\Reference;

class Reference implements IReference {
	private string $reference;
	private ?string $title = null;
	private ?string $description = null;
	private ?string $imageUrl = null;
	private ?string $richObjectType = null;
	private ?array $richObject = null;

	public function __construct(string $reference) {
		$this->reference = $reference;
	}

	public function getId(): string {
		return $this->reference;
	}

	public function setTitle(string $title): void {
		$this->title = $title;
	}

	public function getTitle(): string {
		return $this->title ?? '';
	}

	public function setDescription(?string $description): void {
		$this->description = $description;
	}

	public function getDescription(): ?string {
		return $this->description;
	}

	public function setImageUrl(?string $imageUrl): void {
		$this->imageUrl = $imageUrl;
	}

	public function getImageUrl(): ?string {
		return $this->imageUrl;
	}

	public function setRichObject(string $type, ?array $richObject): void {
		$this->richObjectType = $type;
		$this->richObject = $richObject;
	}

	public function getRichObjectType(): string {
		return $this->richObjectType ?? '';
	}

	public function getRichObject(): array {
		return $this->richObject ?? [];
	}
}
