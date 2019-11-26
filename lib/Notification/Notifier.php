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
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier
{

    /** @var IFactory */
    protected $l10nFactory;

    /** @var INotificationManager */
    protected $notificationManager;

    /** @var IUserManager */
    protected $userManager;

    /** @var IURLGenerator */
    protected $urlGenerator;

    public function __construct(IFactory $l10nFactory,
                                INotificationManager $notificationManager,
                                IUserManager $userManager,
                                IURLGenerator $urlGenerator)
    {
        $this->l10nFactory = $l10nFactory;
        $this->notificationManager = $notificationManager;
        $this->userManager = $userManager;
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
     */
    public function prepare(INotification $notification, $languageCode)
    {
        if ($notification->getApp() !== 'analytics') {
            // Not my app => throw
            throw new InvalidArgumentException('Unknown app');
        }

        // Read the language from the notification
        $l = $this->l10nFactory->get('analytics', $languageCode);

        $i = $notification->getSubject();
        if ($i !== 'alert') {
            // Unknown subject => Unknown notification => throw
            throw new InvalidArgumentException('Unknown subject');
        }

        $link = $this->urlGenerator->linkToRouteAbsolute('analytics.page.main', [
            'analytics' => $notification->getObjectId(),
        ]);

        $notification->setParsedMessage('test message')
            ->setRichSubject(
                $l->t('{user} announced '),
                [
                    'user' => [
                        'type' => 'user',
                        'id' => 1,
                        'name' => 'testuser',
                    ]
                ]
            )
            ->setLink($link)
            ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('analytics', 'app-dark.svg')));

        $notification->setParsedSubject($notification);
        return $notification;
    }
}