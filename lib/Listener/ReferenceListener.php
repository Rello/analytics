<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Listener;

use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class ReferenceListener implements IEventListener
{
    public function handle(Event $event): void
    {
        if (!$event instanceof RenderReferenceEvent) {
            return;
        }

        Util::addScript('analytics', 'reference');
    }
}