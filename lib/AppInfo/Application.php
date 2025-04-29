<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\AppInfo;

use OCA\Analytics\ContextChat\ContentProvider;
use OCA\Analytics\Dashboard\Widget;
use OCA\Analytics\Flow\RegisterOperationsListener;
use OCA\Analytics\Listener\UserDeletedListener;
use OCA\Analytics\ShareReview\ShareReviewListener;
use OCA\Analytics\Notification\Notifier;
use OCA\Analytics\Search\SearchProvider;
use OCA\Analytics\Listener\ReferenceListener;
use OCA\Analytics\Reference\ReferenceProvider;
use OCA\ShareReview\Sources\SourceEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\User\Events\UserDeletedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use Psr\Container\ContainerInterface;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use OCA\ContextChat\Event\ContentProviderRegisterEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'analytics';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(Widget::class);

		$context->registerSearchProvider(SearchProvider::class);

		// file actions are not working at the moment
		// $context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScripts::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(RegisterOperationsEvent::class, RegisterOperationsListener::class);
		$context->registerEventListener(ContentProviderRegisterEvent::class, ContentProvider::class);
		$context->registerEventListener(SourceEvent::class, ShareReviewListener::class);

		if (method_exists($context, 'registerReferenceProvider')) {
			$context->registerReferenceProvider(ReferenceProvider::class);
			$context->registerEventListener(RenderReferenceEvent::class, ReferenceListener::class);
		}

		$this->registerNotifications();
	}

	public function boot(IBootContext $context): void {
	}

	protected function registerNotifications(): void {
		$notificationManager = \OC::$server->getNotificationManager();
		$notificationManager->registerNotifierService(Notifier::class);
	}
}