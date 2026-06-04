<?php

declare(strict_types=1);

/**
 * Tests for the object-based events introduced in #50: the Event value object,
 * its lazy static constructors and EventHandler::register()/getRegisteredEvents().
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Pixie\Event;
use WP_UnitTestCase;
use Pixie\EventHandler;

class TestEventObject extends WP_UnitTestCase
{
    /** @testdox [#50] An Event object exposes its name, table and action. */
    public function testEventExposesProperties(): void
    {
        $action = function () {
            return 'x';
        };
        $event = new Event(Event::BEFORE_SELECT, 'foo', $action);

        $this->assertEquals(Event::BEFORE_SELECT, $event->getName());
        $this->assertEquals('foo', $event->getTable());
        $this->assertSame($action, $event->getAction());
    }

    /** @testdox [#50] A null table resolves to the :any sentinel. */
    public function testNullTableBecomesAny(): void
    {
        $event = new Event(Event::AFTER_SELECT, null, function () {
        });
        $this->assertEquals(Event::ANY_TABLE, $event->getTable());
        $this->assertEquals(':any', $event->getTable());
    }

    /** @testdox [#50] Event::on() is a lazy static constructor. */
    public function testOnFactory(): void
    {
        $event = Event::on('custom-event', 'foo', function () {
        });
        $this->assertEquals('custom-event', $event->getName());
        $this->assertEquals('foo', $event->getTable());
    }

    /**
     * @testdox [#50] Each typed lazy static constructor maps to its event constant.
     * @dataProvider typedConstructorProvider
     */
    public function testTypedConstructors(string $method, string $expectedName): void
    {
        $event = Event::{$method}('foo', function () {
        });
        $this->assertEquals($expectedName, $event->getName());
        $this->assertEquals('foo', $event->getTable());
    }

    /** @return array<string, array{0:string,1:string}> */
    public function typedConstructorProvider(): array
    {
        return [
            'beforeSelect' => ['beforeSelect', Event::BEFORE_SELECT],
            'afterSelect'  => ['afterSelect', Event::AFTER_SELECT],
            'beforeInsert' => ['beforeInsert', Event::BEFORE_INSERT],
            'afterInsert'  => ['afterInsert', Event::AFTER_INSERT],
            'beforeUpdate' => ['beforeUpdate', Event::BEFORE_UPDATE],
            'afterUpdate'  => ['afterUpdate', Event::AFTER_UPDATE],
            'beforeDelete' => ['beforeDelete', Event::BEFORE_DELETE],
            'afterDelete'  => ['afterDelete', Event::AFTER_DELETE],
        ];
    }

    /** @testdox [#50] EventHandler::register() accepts an Event object. */
    public function testRegisterEventObject(): void
    {
        $handler = new EventHandler();
        $event   = Event::beforeSelect('foo', function () {
            return 'fired';
        });

        $handler->register($event);

        // Retrievable as a closure (BC) ...
        $closure = $handler->getEvent(Event::BEFORE_SELECT, 'foo');
        $this->assertIsCallable($closure);
        $this->assertEquals('fired', $closure());

        // ... and as the Event object via the new accessor.
        $registered = $handler->getRegisteredEvents();
        $this->assertInstanceOf(Event::class, $registered['foo'][Event::BEFORE_SELECT]);
        $this->assertSame($event, $registered['foo'][Event::BEFORE_SELECT]);
    }

    /** @testdox [#50][BC] registerEvent() still works and stores an Event internally. */
    public function testRegisterEventLegacyStillWorks(): void
    {
        $handler = new EventHandler();
        $handler->registerEvent(Event::AFTER_DELETE, 'bar', function () {
            return 1;
        });

        $registered = $handler->getRegisteredEvents();
        $this->assertInstanceOf(Event::class, $registered['bar'][Event::AFTER_DELETE]);
        $this->assertEquals(1, $handler->getEvent(Event::AFTER_DELETE, 'bar')());
    }
}
