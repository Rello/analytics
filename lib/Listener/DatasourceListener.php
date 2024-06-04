<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Analytics\Listener;

use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Analytics\Datasource\ExternalFile;
use OCA\Analytics\Datasource\File;
use OCA\Analytics\Datasource\Github;
use OCA\Analytics\Datasource\Json;
use OCA\Analytics\Datasource\Regex;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class DatasourceListener implements IEventListener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof DatasourceEvent)) {
            // Unrelated
            return;
        }
        $event->registerDatasource(ExternalFile::class);
        $event->registerDatasource(File::class);
        $event->registerDatasource(Github::class);
        $event->registerDatasource(Json::class);
        $event->registerDatasource(Regex::class);
    }
}