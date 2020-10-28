<?php

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
    protected $collections;

    /**
     * DispatcherEvent constructor.
     *
     * @param string $event
     * @since 9.1.0
     */
    public function __construct($event)
    {
        $this->event = $event;
        $this->collections = [];
    }

    /**
     * @param OCA\Analytics\Datasource\IDatasource $datasource
     * @since 9.1.0
     */
    public function registerDatasource(OCA\Analytics\Datasource\IDatasource $datasource)
    {
        //
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