<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Exception;

class FlexibleStorageException extends \RuntimeException {
	/**
	 * @param array<string,mixed> $details
	 */
	public function __construct(
		private readonly string $errorCode,
		string $message,
		private readonly array $details = [],
		private readonly int $httpStatus = 400,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	/** @return array<string,mixed> */
	public function getDetails(): array {
		return $this->details;
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}

	/** @return array{error:array{code:string,message:string,details:array<string,mixed>}} */
	public function toResponse(): array {
		return [
			'error' => [
				'code' => $this->errorCode,
				'message' => $this->getMessage(),
				'details' => $this->details,
			],
		];
	}
}
