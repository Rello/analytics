<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP;

interface IURLGenerator {
	public function getAbsoluteURL($url);

	public function imagePath($appName, $file);

	public function linkToRouteAbsolute($routeName, $arguments = []);
}
