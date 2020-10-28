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
    public const EVENT_ENTITY = 'OCA\Analytics\Datasource\ICommentsManager::registerEntity';

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
     * @param string $name
     * @param \Closure $entityExistsFunction The closure should take one
     *                 argument, which is the id of the entity, that comments
     *                 should be handled for. The return should then be bool,
     *                 depending on whether comments are allowed (true) or not.
     * @throws \OutOfBoundsException when the entity name is already taken
     * @since 9.1.0
     */
    public function addEntityCollection($name, \Closure $entityExistsFunction)
    {
        if (isset($this->collections[$name])) {
            throw new \OutOfBoundsException('Duplicate entity name "' . $name . '"');
        }

        $this->collections[$name] = $entityExistsFunction;
    }

    /**
     * @return \Closure[]
     * @since 9.1.0
     */
    public function getEntityCollections()
    {
        return $this->collections;
    }
}