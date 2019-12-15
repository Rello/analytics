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

namespace OCA\Analytics\Activity;

use OCA\Analytics\Controller\DbController;
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

    private $manager;
    private $l10n;
    private $userId;
    private $DBController;
    private $logger;

    public function __construct(
        IManager $manager,
        IL10N $l10n,
        DbController $DBController,
        $userId,
        ILogger $logger
    )
    {
        $this->manager = $manager;
        $this->l10n = $l10n;
        $this->userId = $userId;
        $this->DBController = $DBController;
        $this->logger = $logger;
    }

    public function triggerEvent($datasetId, $eventType, $eventSubject)
    {
        try {
            $event = $this->createEvent($datasetId, $eventType, $eventSubject);
            if ($event !== null) {
                $this->sendToUsers($event);
            }
        } catch (\Exception $e) {
            // Ignore exception for undefined activities on update events
        }
    }

    private function createEvent($datasetId, $eventType, $eventSubject)
    {
        $datasetName = $datasetId !== 0 ? $this->DBController->getOwnDataset($datasetId)['name'] : '';
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
        $users = $this->DBController->getSharedReceiver($event->getObjectId());
        //$this->logger->error($users);
        foreach ($users as $user) {
            $event->setAffectedUser($user['uid_owner']);
            $this->manager->publish($event);
        }
        $event->setAffectedUser($this->userId);
        $this->manager->publish($event);
    }
}