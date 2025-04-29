<?php
declare(strict_types=1);
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Flow;

use OCA\Analytics\Controller\DataloadController;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IManager as FlowManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use Psr\Log\LoggerInterface;

class Operation implements IOperation {
	public function __construct(
		protected IL10N              $l,
		protected IURLGenerator      $urlGenerator,
		protected LoggerInterface    $logger,
		protected DataloadController $DataloadController,
		protected IEventDispatcher   $eventDispatcher
	) {
	}

	public function getDisplayName(): string {
		return $this->l->t('Analytics');
	}

	public function getDescription(): string {
		return $this->l->t('Read file and add its data to an existing dataset');
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('analytics', 'app.svg');
	}

	public function isAvailableForScope(int $scope): bool {
		return $scope === FlowManager::SCOPE_USER;
	}

	/**
	 * Validates whether a configured workflow rule is valid. If it is not,
	 * an `\UnexpectedValueException` is supposed to be thrown.
	 *
	 * @since 9.1
	 */
	public function validateOperation($name, array $checks, $operation): void {
	}

	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		$flow = $ruleMatcher->getFlows();
		if ($flow === []) {
			return;
		}

		$datasetId = (int)$flow['operation'];

		if ($eventName === '\OCP\Files::postRename') {
			/** @var Node $oldNode */
			list(, $node) = $event->getSubject();
		} else {
			$node = $event->getSubject();
		}

		list(, , $folder, $file) = explode('/', $node->getPath(), 4);
		if ($folder !== 'files' || $node instanceof Folder) {
			return;
		}
		$file = '/' . $file;

		try {
			$this->DataloadController->importFile($datasetId, $file, true);
		} catch (NotFoundException $e) {
			return;
		} catch (\Exception $e) {
			return;
		}
	}
}