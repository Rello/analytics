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

declare(strict_types=1);

namespace OCA\Analytics\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use OCA\Analytics\Service\UserService;

class UserDeletedListener implements IEventListener
{

    /** @var UserService */
    private $service;

    public function __construct(UserService $service) {
        $this->service = $service;
    }
    public function handle(Event $event): void
    {
        if(!$event instanceof UserDeletedEvent) {
            return;
        }

        $uid = $event->getUser()->getUID();
        $this->service->deleteUserData($uid);
    }
}