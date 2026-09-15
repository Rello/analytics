<?php
/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\AppFramework;

use OCP\IRequest;

class ApiController extends Controller {
	public function __construct(string $appName, IRequest $request, string $method = 'GET') {
		parent::__construct($appName, $request);
	}
}
