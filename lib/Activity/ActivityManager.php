<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Activity;

use OCA\Analytics\Db\ReportMapper;
use OCA\Analytics\Db\DatasetMapper;
use OCA\Analytics\Db\PanoramaMapper;
use OCA\Analytics\Db\ShareMapper;
use OCA\Analytics\Service\ShareService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use Psr\Log\LoggerInterface;

class ActivityManager
{
    const OBJECT_REPORT = 'analytics_report';
	const OBJECT_PANORAMA = 'analytics_panorama';
	const OBJECT_DATASET = 'analytics_dataset';
	const OBJECT_DATA = 'analytics_data';

    const SUBJECT_REPORT_ADD = 'report_add';
    const SUBJECT_REPORT_DELETE = 'report_delete';
    const SUBJECT_REPORT_SHARE = 'report_share';

	const SUBJECT_PANORAMA_ADD = 'panorama_add';
	const SUBJECT_PANORAMA_DELETE = 'panorama_delete';
	const SUBJECT_PANORAMA_SHARE = 'panorama_share';

	const SUBJECT_DATASET_ADD = 'dataset_add';
	const SUBJECT_DATASET_DELETE = 'dataset_delete';
	const SUBJECT_DATASET_SHARE = 'dataset_share';

    const SUBJECT_DATA_ADD = 'data_add';
    const SUBJECT_DATA_ADD_API = 'data_add_api';
    const SUBJECT_DATA_ADD_IMPORT = 'data_add_import';
    const SUBJECT_DATA_ADD_DATALOAD = 'data_add_dataload';

    private $manager;
    private $userId;
    private $ShareMapper;
    private $logger;
    private $ReportMapper;
    private $DatasetMapper;
	private $PanoramaMapper;

    public function __construct(
        IManager $manager,
        ShareMapper $ShareMapper,
        $userId,
        ReportMapper $ReportMapper,
        DatasetMapper $DatasetMapper,
        LoggerInterface $logger,
		PanoramaMapper $PanoramaMapper
    )
    {
        $this->manager = $manager;
        $this->userId = $userId;
        $this->ShareMapper = $ShareMapper;
        $this->ReportMapper = $ReportMapper;
        $this->DatasetMapper = $DatasetMapper;
		$this->PanoramaMapper = $PanoramaMapper;
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
				// TODO
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
        if ($eventType === ActivityManager::OBJECT_REPORT) {
            $name = $objectId !== 0 ? $this->ReportMapper->readOwn($objectId)['name'] : '';
        } elseif ($eventType === ActivityManager::OBJECT_DATASET) {
            $name = $objectId !== 0 ? $this->DatasetMapper->read($objectId)['name'] : '';
            $objectId = 0;
		} elseif ($eventType === ActivityManager::OBJECT_PANORAMA) {
			$name = $objectId !== 0 ? $this->PanoramaMapper->read($objectId)['name'] : '';
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
		switch ($event->getType()) {
			case ActivityManager::OBJECT_REPORT:
				$item_type = ShareService::SHARE_ITEM_TYPE_REPORT;
				break;
			case ActivityManager::OBJECT_DATASET:
				$item_type = ShareService::SHARE_ITEM_TYPE_DATASET;
				break;
			case ActivityManager::OBJECT_PANORAMA:
				$item_type = ShareService::SHARE_ITEM_TYPE_PANORAMA;
				break;
		}

        $users = $this->ShareMapper->getSharedReceiver($item_type, $event->getObjectId());
		//$this->logger->info('share receiver: ' . $item_type . $event->getObjectId());
        foreach ($users as $user) {
            $event->setAffectedUser($user['uid_owner']);
            $this->manager->publish($event);
        }
        $event->setAffectedUser($this->userId);
        $this->manager->publish($event);
    }
}