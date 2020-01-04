<?php
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

use OCP\IL10N;
use OCP\ILogger;
use OCP\Notification\IManager as INotificationManager;

class NotificationManager
{
    const OBJECT_DATASET = 'analytics_dataset';
    const SUBJECT_THRESHOLD = 'data_threshold';
    /** @var INotificationManager */
    protected $notificationManager;
    private $l10n;
    private $userId;
    private $logger;

    public function __construct(
        IL10N $l10n,
        $userId,
        ILogger $logger,
        INotificationManager $notificationManager
    )
    {
        $this->l10n = $l10n;
        $this->userId = $userId;
        $this->logger = $logger;
        $this->notificationManager = $notificationManager;
    }

    /**
     * @param $object
     * @param int $object_id
     * @param $subject
     * @param array $subject_parameter
     * @throws \Exception
     */
    public function triggerNotification($object, $object_id, $subject, $subject_parameter)
    {
        $notification = $this->notificationManager->createNotification();
        $notification->setApp('analytics')
            ->setDateTime(new \DateTime())
            ->setObject($object, $object_id)
            ->setSubject($subject, $subject_parameter)
            ->setUser($this->userId);
        $this->notificationManager->notify($notification);
    }
}