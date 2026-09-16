<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Search;

use OCP\IUser;

interface IProvider {
	public function getId(): string;
	public function getName(): string;
	public function getOrder(string $route, array $routeParameters): ?int;
	public function search(IUser $user, ISearchQuery $query): SearchResult;
}
