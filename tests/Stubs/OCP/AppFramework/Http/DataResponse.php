<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCP\AppFramework\Http;

class DataResponse {
	/** @var mixed */
	private $data;

	/** @var int */
	private $status;

	public function __construct($data = null, int $status = 200) {
		$this->data = $data;
		$this->status = $status;
	}

    private array $headers = [];

    public function addHeader(string $name, string $value): void {
        $this->headers[$name] = $value;
    }

    public function getHeaders(): array {
        return $this->headers;
    }

	public function getData() {
		return $this->data;
	}

	public function getStatus(): int {
		return $this->status;
	}
}
