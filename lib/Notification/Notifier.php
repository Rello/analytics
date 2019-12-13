<?php
declare(strict_types=1);
/**
 * Data Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2019 Marcel Scherello
 */

namespace OCA\Analytics\Notification;

use InvalidArgumentException;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier
{

    /** @var IFactory */
    protected $l10nFactory;

    /** @var IUserManager */
    protected $userManager;

    /** @var IURLGenerator */
    protected $urlGenerator;

    private $logger;

    public function __construct(IFactory $l10nFactory,
                                IUserManager $userManager,
                                ILogger $logger,
                                IURLGenerator $urlGenerator)
    {
        $this->l10nFactory = $l10nFactory;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Identifier of the notifier, only use [a-z0-9_]
     *
     * @return string
     * @since 17.0.0
     */
    public function getID(): string
    {
        return 'analytics';
    }

    /**
     * Human readable name describing the notifier
     *
     * @return string
     * @since 17.0.0
     */
    public function getName(): string
    {
        return $this->l10nFactory->get('analytics')->t('Announcements');
    }

    /**
     * @param INotification $notification
     * @param string $languageCode The code of the language that should be used to prepare the notification
     * @return INotification
     * @throws InvalidArgumentException When the notification was not prepared by a notifier
     * @throws AlreadyProcessedException When the notification is not needed anymore and should be deleted
     * @since 9.0.0
     */
    public function prepare(INotification $notification, string $languageCode): INotification
    {
        if ($notification->getApp() !== 'analytics') {
            // Not my app => throw
            throw new InvalidArgumentException('Unknown app');
        }

        // Read the language from the notification
        $l = $this->l10nFactory->get('analytics', $languageCode);
        $parsedSubject = '';

        //$this->logger->error('Notifier 89: '.$notification->getSubject());
        switch ($notification->getSubject()) {
            case NotificationManager::SUBJECT_THRESHOLD:
                //$notification->setParsedMessage('parsed message');
                $notification->setParsedSubject('parsed subject');
                $parsedSubject = $l->t("Exception in Report '{report}'. The value of '{subject}' reached the threshold of '{rule} {value}'");
                break;
        }
        $link = $this->urlGenerator->linkToRouteAbsolute('analytics.page.index') . '#/r/' . $notification->getObjectId();

        $parameters = $notification->getSubjectParameters();
        $notification->setRichSubject(
            $parsedSubject,
            [
                'report' => [
                    'type' => 'highlight',
                    'id' => $notification->getObjectId(),
                    'name' => $parameters['report'],
                    'link' => $link,
                ],
                'subject' => [
                    'type' => 'highlight',
                    'id' => $notification->getObjectId(),
                    'name' => $parameters['subject'],
                ],
                'rule' => [
                    'type' => 'highlight',
                    'id' => $notification->getObjectId(),
                    'name' => $parameters['rule'],
                ],
                'value' => [
                    'type' => 'highlight',
                    'id' => $notification->getObjectId(),
                    'name' => $parameters['value'],
                ]
            ]
        );
        $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', 'app-dark.svg')));

        $notification->setParsedSubject('test');
        return $notification;
    }
}