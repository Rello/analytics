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

namespace OCA\Analytics\Notification;

use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class NotificationManager
{
    const OBJECT_REPORT = 'analytics_report';
    const SUBJECT_THRESHOLD = 'data_threshold';
    const DATALOAD_ERROR = 'dataload_error';
    /** @var INotificationManager */
    protected $notificationManager;
    private $logger;

    public function __construct(
        LoggerInterface      $logger,
        INotificationManager $notificationManager
    )
    {
        $this->logger = $logger;
        $this->notificationManager = $notificationManager;
    }

    /**
     * @param string $object_type
     * @param int $object_id
     * @param $subject
     * @param array $subject_parameter
     * @param $user_id
     */
    public function triggerNotification($object_type, $object_id, $subject, $subject_parameter, $user_id)
    {
        $notification = $this->notificationManager->createNotification();
        $notification->setApp('analytics')
            ->setObject($object_type, $object_id)
            ->setSubject($subject)
            ->setUser($user_id);
        $this->notificationManager->markProcessed($notification);

        $notification = $this->notificationManager->createNotification();
        $notification->setApp('analytics')
            ->setDateTime(new \DateTime())
            ->setObject($object_type, $object_id)
            ->setSubject($subject, $subject_parameter)
            ->setUser($user_id);
        $this->notificationManager->notify($notification);
    }
}