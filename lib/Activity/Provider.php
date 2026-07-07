<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Activity;

use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;

class Provider implements IProvider {

	private $l10n;
	private $userId;
	private $urlGenerator;
	private $appManager;
	private $userManager;

	public function __construct(
		IURLGenerator $urlGenerator,
		IL10N         $l10n,
		IAppManager  $appManager,
		IUserManager $userManager,
					  $userId
	) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->appManager = $appManager;
		$this->userManager = $userManager;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws UnknownActivityException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null) {
		if ($event->getApp() !== 'analytics') {
			throw new UnknownActivityException();
		}
		if (!$this->isAnalyticsEnabledForCurrentUser()) {
			throw new UnknownActivityException();
		}

		$parsedSubject = '';
		$ownActivity = ($event->getAuthor() === $this->userId);

		switch ($event->getSubject()) {
			case ActivityManager::SUBJECT_REPORT_ADD:
				$parsedSubject = $this->l10n->t('You created a new report: {report}');
				break;
			case ActivityManager::SUBJECT_REPORT_DELETE:
				$parsedSubject = $this->l10n->t('You deleted the report {report}');
				break;
			case ActivityManager::SUBJECT_REPORT_SHARE:
				if ($ownActivity) {
					$parsedSubject = $this->l10n->t('You shared the report {report}');
				} else {
					$parsedSubject = $event->getSubjectParameters()['author'] . ' ' . $this->l10n->t('shared the report {report} with you');
				}
				break;

			case ActivityManager::SUBJECT_DATASET_ADD:
				$parsedSubject = $this->l10n->t('You created a new dataset: {report}');
				break;
			case ActivityManager::SUBJECT_DATASET_DELETE:
				$parsedSubject = $this->l10n->t('You deleted the dataset {report}');
				break;
			case ActivityManager::SUBJECT_DATASET_SHARE:
				if ($ownActivity) {
					$parsedSubject = $this->l10n->t('You shared the dataset {report}');
				} else {
					$parsedSubject = $event->getSubjectParameters()['author'] . ' ' . $this->l10n->t('shared the dataset {report} with you');
				}
				break;

			case ActivityManager::SUBJECT_PANORAMA_ADD:
				$parsedSubject = $this->l10n->t('You created a new panorama: {report}');
				break;
			case ActivityManager::SUBJECT_PANORAMA_DELETE:
				$parsedSubject = $this->l10n->t('You deleted the panorama {report}');
				break;
			case ActivityManager::SUBJECT_PANORAMA_SHARE:
				if ($ownActivity) {
					$parsedSubject = $this->l10n->t('You shared the panorama {report}');
				} else {
					$parsedSubject = $event->getSubjectParameters()['author'] . ' ' . $this->l10n->t('shared the panorama {report} with you');
				}
				break;

			case ActivityManager::SUBJECT_DATA_ADD:
				if ($ownActivity) {
					$parsedSubject = $this->l10n->t('You have added new data to dataset {report}');
				} else {
					$parsedSubject = $event->getSubjectParameters()['author'] . ' ' . $this->l10n->t('has added new data to dataset {report}');
				}
				break;
			case ActivityManager::SUBJECT_DATA_ADD_IMPORT:
				if ($ownActivity) {
					$parsedSubject = $this->l10n->t('You have imported data in dataset {report}');
				} else {
					$parsedSubject = $event->getSubjectParameters()['author'] . ' ' . $this->l10n->t('has imported data in dataset {report}');
				}
				break;
			case ActivityManager::SUBJECT_DATA_ADD_API:
				$parsedSubject = $this->l10n->t('New data has been added to dataset {report} via API');
				break;
			case ActivityManager::SUBJECT_DATA_ADD_DATALOAD:
				$parsedSubject = $this->l10n->t('New data has been added to dataset {report} via data load');
				break;
			default:
				throw new UnknownActivityException();
		}

		$event->setRichSubject($parsedSubject, [
			'report' => [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => '"' . basename($event->getObjectName()) . '"',
				'link' => $this->Url($event->getObjectId()),
			]
		])
			  ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($event->getApp(), 'app-dark.svg')));

		return $event;
	}

	public function Url($endpoint) {
		return $this->urlGenerator->linkToRouteAbsolute('analytics.page.main') . 'r/' . $endpoint;
	}

	private function isAnalyticsEnabledForCurrentUser(): bool {
		if ($this->userId === null || $this->userId === '') {
			return $this->appManager->isEnabledForUser('analytics');
		}

		$user = $this->userManager->get($this->userId);
		if ($user === null) {
			return false;
		}

		return $this->appManager->isEnabledForUser('analytics', $user);
	}
}
