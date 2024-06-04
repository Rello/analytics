<?php
/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2019-2022 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Datasource;

use OCP\EventDispatcher\Event;

/**
 * Class CommentsEntityEvent
 *
 * @since 9.1.0
 */
class DatasourceEvent extends Event
{

    /** @var string */
    protected $event;
    /** @var \Closure[] */
    protected $collections = [];

    /**
     * @param string $datasource
     * @since 9.1.0
     */
    public function registerDatasource(string $datasource)
    {
        $this->collections[] = $datasource;
    }

    /**
     * @return \Closure[]
     * @since 9.1.0
     */
    public function getDataSources()
    {
        return $this->collections;
    }
}