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

use OCA\Analytics\Service\DatasetService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IL10N;
use OCP\IUser;


class ActivityManager
{

    const OBJECT_DATASET = 'analytics_dataset';
    const OBJECT_DATA = 'analytics_data';
    const SUBJECT_DATASET_ADD = 'dataset_add';
    const SUBJECT_DATASET_DELETE = 'dataset_delete';
    const SUBJECT_DATASET_SHARE = 'dataset_share';
    const SUBJECT_DATA_ADD = 'data_add';
    const SUBJECT_DATA_ADD_API = 'data_add_api';
    private $manager;
    private $l10n;
    private $userId;
    private $DatasetService;

    public function __construct(
        IManager $manager,
        IL10N $l10n,
        DatasetService $DatasetService,
        $userId
    )
    {
        $this->manager = $manager;
        $this->l10n = $l10n;
        $this->userId = $userId;
        $this->DatasetService = $DatasetService;
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

    private function createEvent($datasetId, $eventType, $eventSubject, $ownActivity = true, $author = null)
    {

        $dataset = $this->DatasetService->getOwnDataset($datasetId);
        $event = $this->manager->generateEvent();
        $event->setApp('analytics')
            ->setType($eventType)
            ->setAuthor($this->userId)
            ->setObject('report', (int)$datasetId, $dataset['name'])
            ->setSubject($eventSubject, ['author' => $this->userId])
            ->setTimestamp(time());
        return $event;
    }

    /**
     * Publish activity to all users that are part of the board of a given object
     *
     * @param IEvent $event
     */
    private function sendToUsers(IEvent $event)
    {
        $event->setAffectedUser('admin');
        $this->manager->publish($event);


        switch ($event->getObjectType()) {
            case self::DECK_OBJECT_BOARD:
                $mapper = $this->boardMapper;
                break;
            case self::DECK_OBJECT_CARD:
                $mapper = $this->cardMapper;
                break;
        }
        $boardId = $mapper->findBoardId($event->getObjectId());
        /** @var IUser $user */
        foreach ($this->permissionService->findUsers($boardId) as $user) {
            $event->setAffectedUser($user->getUID());
            /** @noinspection DisconnectedForeachInstructionInspection */
            $this->manager->publish($event);
        }


    }
}