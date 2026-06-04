<?php

namespace Pixie;

use Closure;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Pixie\QueryBuilder\Raw;

class EventHandler
{
    /**
     * Registered events as Event objects, keyed by table then event name (#50).
     *
     * @var array<string, array<string, Event>>
     */
    protected $events = [];

    /**
     * @var string[]
     */
    protected $firedEvents = [];

    /**
     * Returns the registered event handlers as closures, keyed by table then
     * event name. Kept in this (closure leaf) shape for backwards compatibility.
     *
     * @return array<string, array<string, Closure>>
     */
    public function getEvents()
    {
        $events = [];
        foreach ($this->events as $table => $tableEvents) {
            // Preserve table keys even when empty (e.g. after removeEvent()).
            $events[$table] = [];
            foreach ($tableEvents as $name => $event) {
                $events[$table][$name] = $event->getAction();
            }
        }

        return $events;
    }

    /**
     * Returns the registered Event objects, keyed by table then event name.
     *
     * @return array<string, array<string, Event>>
     */
    public function getRegisteredEvents(): array
    {
        return $this->events;
    }

    /**
     * @param string     $event
     * @param string|Raw $table
     *
     * @return Closure|null
     */
    public function getEvent(string $event, $table = ':any'): ?Closure
    {
        if ($table instanceof Raw) {
            return null;
        }

        $registered = $this->events[$table][$event] ?? null;

        return $registered instanceof Event ? $registered->getAction() : null;
    }

    /**
     * Register an event from an Event object (#50).
     *
     * @param Event $event
     *
     * @return void
     */
    public function register(Event $event): void
    {
        $this->events[$event->getTable()][$event->getName()] = $event;
    }

    /**
     * @param string      $event
     * @param string|null $table
     * @param Closure     $action
     *
     * @return void
     */
    public function registerEvent(string $event, ?string $table, Closure $action)
    {
        $this->register(new Event($event, $table, $action));
    }

    /**
     * @param string $event
     * @param string $table
     *
     * @return void
     */
    public function removeEvent($event, $table = ':any')
    {
        unset($this->events[$table][$event]);
    }

    /**
     * @param QueryBuilderHandler $queryBuilder
     * @param string              $event
     *
     * @return mixed
     */
    public function fireEvents(QueryBuilderHandler $queryBuilder, string $event)
    {
        $statements = $queryBuilder->getStatements();
        $tables     = $statements['tables'] ?? [];

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
                $result              = call_user_func_array($action, $handlerParams);
                if (!is_null($result)) {
                    return $result;
                }
            }
        }
    }
}
