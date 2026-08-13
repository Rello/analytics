<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\Collaboration\Reference;

// the app imports the internal ReferenceManager, which is not autoloaded in unit tests
class ReferenceManager {
	public function invalidateCache(string $cachePrefix, ?string $cacheKey = null): void {
	}
}
