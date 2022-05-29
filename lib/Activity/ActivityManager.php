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

use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\ShareMapper;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use Psr\Log\LoggerInterface;

class ActivityManager
{
    const OBJECT_REPORT = 'analytics_report';
    const OBJECT_DATA = 'analytics_data';
    const SUBJECT_REPORT_ADD = 'report_add';
    const SUBJECT_REPORT_DELETE = 'report_delete';
    const SUBJECT_REPORT_SHARE = 'report_share';
    const SUBJECT_DATA_ADD = 'data_add';
    const SUBJECT_DATA_ADD_API = 'data_add_api';
    const SUBJECT_DATA_ADD_IMPORT = 'data_add_import';
    const SUBJECT_DATA_ADD_DATALOAD = 'data_add_dataload';

    const SUBJECT_REPORT_ADD_depr = 'dataset_add';
    const SUBJECT_REPORT_DELETE_depr = 'dataset_delete';
    const SUBJECT_REPORT_SHARE_depr = 'dataset_share';

    private $manager;
    private $userId;
    private $ShareMapper;
    private $logger;
    private $ReportMapper;
    private $DatasetMapper;

    public function __construct(
        IManager $manager,
        ShareMapper $ShareMapper,
        $userId,
        ReportMapper $ReportMapper,
        DatasetMapper $DatasetMapper,
        LoggerInterface $logger
    )
    {
        $this->manager = $manager;
        $this->userId = $userId;
        $this->ShareMapper = $ShareMapper;
        $this->ReportMapper = $ReportMapper;
        $this->DatasetMapper = $DatasetMapper;
        $this->logger = $logger;
    }

    /**
     * @param $datasetId
     * @param $eventType
     * @param $eventSubject
     * @param string|null $user_id
     */
    public function triggerEvent($datasetId, $eventType, $eventSubject, string $user_id = null)
    {
        try {
            $event = $this->createEvent($datasetId, $eventType, $eventSubject, $user_id);
            if ($event !== null) {
                $this->sendToUsers($event);
            }
        } catch (\Exception $e) {
            // Ignore exception for undefined activities on update events
        }
    }

    /**
     * @param $reportId
     * @param $eventType
     * @param $eventSubject
     * @param string|null $user_id
     * @return IEvent
     * @throws \OCP\DB\Exception
     */
    private function createEvent($objectId, $eventType, $eventSubject, string $user_id = null)
    {
        if ($eventSubject === ActivityManager::SUBJECT_REPORT_ADD || $eventSubject === ActivityManager::SUBJECT_REPORT_DELETE || $eventSubject === ActivityManager::SUBJECT_REPORT_SHARE) {
            $name = $objectId !== 0 ? $this->ReportMapper->readOwn($objectId)['name'] : '';
        } else {
            $name = $objectId !== 0 ? $this->DatasetMapper->read($objectId)['name'] : '';
            $objectId = 0;
        }

        if ($user_id) $this->userId = $user_id;
        $event = $this->manager->generateEvent();
        $event->setApp('analytics')
            ->setType($eventType)
            ->setAuthor($this->userId)
            ->setObject('report', (int)$objectId, $name)
            ->setSubject($eventSubject, ['author' => $this->userId])
            ->setTimestamp(time());
        return $event;
    }

    /**
     * Publish activity to all users that are part of the dataset
     *
     * @param IEvent $event
     */
    private function sendToUsers(IEvent $event)
    {
        $users = $this->ShareMapper->getSharedReceiver($event->getObjectId());
        foreach ($users as $user) {
            $event->setAffectedUser($user['uid_owner']);
            $this->manager->publish($event);
        }
        $event->setAffectedUser($this->userId);
        $this->manager->publish($event);
    }
}