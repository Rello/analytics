<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Collaboration\Reference;

interface ISearchableReferenceProvider {
	public function getSupportedSearchProviderIds(): array;
}
