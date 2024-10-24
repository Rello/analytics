<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\ShareReview;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCA\ShareReview\Sources\SourceEvent;

class ShareReviewListener implements IEventListener {

	public function __construct() {
	}

	public function handle(Event $event): void {
		if (!$event instanceof SourceEvent) {
			return;
		}
		$event->registerSource(ShareReviewSource::class);
	}
}