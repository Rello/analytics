<?php
/**
 * Analytics
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <audioplayer@scherello.de>
 * @copyright 2020 Marcel Scherello
 */

namespace OCA\Analytics\Activity;

use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\ShareMapper;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IL10N;
use OCP\ILogger;

class ActivityManager
{
    const OBJECT_DATASET = 'analytics_dataset';
    const OBJECT_DATA = 'analytics_data';
    const SUBJECT_DATASET_ADD = 'dataset_add';
    const SUBJECT_DATASET_DELETE = 'dataset_delete';
    const SUBJECT_DATASET_SHARE = 'dataset_share';
    const SUBJECT_DATA_ADD = 'data_add';
    const SUBJECT_DATA_ADD_API = 'data_add_api';
    const SUBJECT_DATA_ADD_IMPORT = 'data_add_import';
    const SUBJECT_DATA_ADD_DATALOAD = 'data_add_dataload';

    private $manager;
    private $l10n;
    private $userId;
    private $ShareMapper;
    private $logger;
    private $DatasetMapper;

    public function __construct(
        IManager $manager,
        IL10N $l10n,
        ShareMapper $ShareMapper,
        $userId,
        DatasetMapper $DatasetMapper,
        ILogger $logger
    )
    {
        $this->manager = $manager;
        $this->l10n = $l10n;
        $this->userId = $userId;
        $this->ShareMapper = $ShareMapper;
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
     * @param $datasetId
     * @param $eventType
     * @param $eventSubject
     * @param string|null $user_id
     * @return IEvent
     */
    private function createEvent($datasetId, $eventType, $eventSubject, string $user_id = null)
    {
        $datasetName = $datasetId !== 0 ? $this->DatasetMapper->getOwnDataset($datasetId)['name'] : '';
        if ($user_id) $this->userId = $user_id;
        $event = $this->manager->generateEvent();
        $event->setApp('analytics')
            ->setType($eventType)
            ->setAuthor($this->userId)
            ->setObject('report', (int)$datasetId, $datasetName)
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
        //$this->logger->error($users);
        foreach ($users as $user) {
            $event->setAffectedUser($user['uid_owner']);
            $this->manager->publish($event);
        }
        $event->setAffectedUser($this->userId);
        $this->manager->publish($event);
    }
}