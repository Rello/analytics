<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Activity;

interface IProvider {
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null);
}
