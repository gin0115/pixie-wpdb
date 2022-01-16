<?php

declare(strict_types=1);

/**
 * Unit tests event handler
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use Closure;
use Exception;
use WP_UnitTestCase;
use Pixie\EventHandler;

class TestEvents extends WP_UnitTestCase
{
    /**
     * Returns a simple closure that returns whatever string is passed.
     *
     * @param string $returns
     * @return \Closure
     */
    public function createClosure(string $returns): Closure
    {
        return function () use ($returns) {
            return $returns;
        };
    }

    /** @testdox It should be possible to register and get events from the EventHandler based on the t */
    public function testCanRegisterAndGetEvents(): void
    {
        $handler = new EventHandler();
        $handler->registerEvent('for_bar_table', 'bar', $this->createClosure('bar_table'));
        $handler->registerEvent('for_any_table', null, $this->createClosure('any_table'));

        // Get all events and check the keys are used correctly.
        $events = $handler->getEvents();
        $this->assertArrayHasKey('bar', $events);
        $this->assertArrayHasKey('for_bar_table', $events['bar']);
        $this->assertEquals('bar_table', $events['bar']['for_bar_table']());

        $this->assertArrayHasKey(':any', $events);
        $this->assertArrayHasKey('for_any_table', $events[':any']);
        $this->assertEquals('any_table', $events[':any']['for_any_table']());

        // Get single events.
        $event = $handler->getEvent('for_bar_table', 'bar');
        $this->assertEquals('bar_table', $event());

        $event = $handler->getEvent('for_any_table', ':any');
        $this->assertEquals('any_table', $event());
    }

    /** @testdox It should be possible to remove an event from the event list using its name and event key. */
    public function testRemoveEvent(): void
    {
        $handler = new EventHandler();
        $handler->registerEvent('for_bar_table', 'bar', $this->createClosure('bar_table'));
        // Remove.
        $handler->removeEvent('for_bar_table', 'bar');
        // Should stil hold empty key.
        $this->assertEmpty($handler->getEvents()['bar']);
    }
}
