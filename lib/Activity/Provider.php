<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 * @copyright 2019-2022 Marcel Scherello
 */

namespace OCA\Analytics\Activity;

use InvalidArgumentException;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

class Provider implements IProvider
{

    private $l10n;
    private $userId;
    private $urlGenerator;

    public function __construct(IURLGenerator $urlGenerator,
                                IL10N $l10n,
                                $userId)
    {
        $this->userId = $userId;
        $this->urlGenerator = $urlGenerator;
        $this->l10n = $l10n;
    }

    /**
     * @param string $language
     * @param IEvent $event
     * @param IEvent|null $previousEvent
     * @return IEvent
     * @throws InvalidArgumentException
     * @since 11.0.0
     */
    public function parse($language, IEvent $event, IEvent $previousEvent = null)
    {
        if ($event->getApp() !== 'analytics') {
            throw new InvalidArgumentException();
        }

        $parsedSubject = '';
        $ownActivity = ($event->getAuthor() === $this->userId);

        switch ($event->getSubject()) {
            case ActivityManager::SUBJECT_REPORT_ADD:
            case ActivityManager::SUBJECT_REPORT_ADD_depr:
                $parsedSubject = $this->l10n->t('You created a new report: {report}');
                break;
            case ActivityManager::SUBJECT_REPORT_DELETE:
            case ActivityManager::SUBJECT_REPORT_DELETE_depr:
                $parsedSubject = $this->l10n->t('You deleted report {report}');
                break;
            case ActivityManager::SUBJECT_REPORT_SHARE:
            case ActivityManager::SUBJECT_REPORT_SHARE_depr:
                if ($ownActivity) {
                    $parsedSubject = $this->l10n->t('You shared report {report}');
                } else {
                    $parsedSubject = $event->getSubjectParameters()['author'] . ' ' .$this->l10n->t('shared report {report} with you');
                }
                break;
            case ActivityManager::SUBJECT_DATA_ADD:
                if ($ownActivity) {
                    $parsedSubject = $this->l10n->t('You have added new data to dataset {report}');
                } else {
                    $parsedSubject = $event->getSubjectParameters()['author'] . ' ' .$this->l10n->t('has added new data to dataset {report}');
                }
                break;
            case ActivityManager::SUBJECT_DATA_ADD_IMPORT:
                if ($ownActivity) {
                    $parsedSubject = $this->l10n->t('You have imported data in dataset {report}');
                } else {
                    $parsedSubject = $event->getSubjectParameters()['author'] . ' ' .$this->l10n->t('has imported data in dataset {report}');
                }
                break;
            case ActivityManager::SUBJECT_DATA_ADD_API:
                $parsedSubject = $this->l10n->t('New data has been added to dataset {report} via API');
                break;
            case ActivityManager::SUBJECT_DATA_ADD_DATALOAD:
                $parsedSubject = $this->l10n->t('New data has been added to dataset {report} via data load');
                break;
        }

        $event->setRichSubject(
            $parsedSubject,
            ['report' => [
                'type' => 'highlight',
                'id' => $event->getObjectId(),
                'name' => '"'.basename($event->getObjectName()).'"',
                'link' => $this->Url($event->getObjectId()),
            ]]
        );

        $event->setParsedSubject($parsedSubject)
            ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($event->getApp(), 'app-dark.svg')));
        return $event;
    }

    public function Url($endpoint)
    {
        return $this->urlGenerator->linkToRouteAbsolute('analytics.page.index') . '#/r/' . $endpoint;
    }
}
