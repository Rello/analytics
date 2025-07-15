<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2024 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\ContextChat;

use OCA\ContextChat\Public\IContentProvider;
use OCP\IURLGenerator;
use OCA\ContextChat\Event\ContentProviderRegisterEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * This interface defines methods to implement a content provider
 * @since 1.1.0
 */
class ContentProvider implements IContentProvider, IEventListener {
	private $logger;

	public function __construct(
		private IURLGenerator $urlGenerator,
		LoggerInterface       $logger,
	) {
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (!$event instanceof ContentProviderRegisterEvent) {
			return;
		}
		$event->registerContentProvider('analytics', 'report', ContentProvider::class);
	}

	/**
	 * The ID of the provider
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function getId(): string {
		return 'report';
	}

	/**
	 * The ID of the app making the provider avaialble
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function getAppId(): string {
		return 'analytics';
	}

	/**
	 * The absolute URL to the content item
	 *
	 * @param string $id
	 * @return string
	 * @since 1.1.0
	 */
	public function getItemUrl(string $id): string {
		return $this->urlGenerator->linkToRouteAbsolute('analytics.page.main') . 'r/' . $id;
	}

	/**
	 * Starts the initial import of content items into content chat
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public function triggerInitialImport(): void {
		return;
	}
}
