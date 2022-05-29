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