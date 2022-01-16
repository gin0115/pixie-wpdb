<?php

namespace Pixie;

use Pixie\QueryBuilder\Raw;
use Pixie\QueryBuilder\QueryBuilderHandler;

class EventHandler
{
    /**
     * @var array<string, array<string, \Closure>>
     */
    protected $events = array();

    /**
     * @var string[]
     */
    protected $firedEvents = array();

    /**
     * @return array<string, array<string, \Closure>>
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @param string $event
     * @param string|Raw $table
     *
     * @return \Closure|null
     */
    public function getEvent(string $event, $table = ':any'): ?\Closure
    {
        if ($table instanceof Raw) {
            return null;
        }
        return isset($this->events[$table][$event]) ? $this->events[$table][$event] : null;
    }

    /**
     * @param string $event
     * @param string|null $table
     * @param \Closure $action
     *
     * @return void
     */
    public function registerEvent(string $event, ?string $table, \Closure $action)
    {
        $table = $table ?? ':any';

        $this->events[$table][$event] = $action;
    }

    /**
     * @param string $event
     * @param string  $table
     *
     * @return void
     */
    public function removeEvent($event, $table = ':any')
    {
        unset($this->events[$table][$event]);
    }

    /**
     * @param QueryBuilderHandler $queryBuilder
     * @param string $event
     * @return mixed
     */
    public function fireEvents(QueryBuilderHandler $queryBuilder, string $event)
    {
        dump($this);
        $statements = $queryBuilder->getStatements();
        $tables = isset($statements['tables']) ? $statements['tables'] : array();

        // Events added with :any will be fired in case of any table,
        // we are adding :any as a fake table at the beginning.
        array_unshift($tables, ':any');

        // Fire all events
        foreach ($tables as $table) {
            // Fire before events for :any table
            if ($action = $this->getEvent($event, $table)) {
                // Make an event id, with event type and table
                $eventId = $event . $table;

                // Fire event
                $handlerParams = func_get_args();
                unset($handlerParams[1]); // we do not need $event
                // Add to fired list
                $this->firedEvents[] = $eventId;
                $result = call_user_func_array($action, $handlerParams);
                if (!is_null($result)) {
                    return $result;
                };
            }
        }
    }
}
