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