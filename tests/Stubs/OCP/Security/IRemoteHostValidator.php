<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Security;

interface IRemoteHostValidator {
	public function isValid(string $host): bool;
}
