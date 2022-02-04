<?php

declare(strict_types=1);

/**
 * Unit tests event handler
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Closure;
use Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\EventHandler;
use Pixie\Tests\Logable_WPDB;
use Gin0115\WPUnit_Helpers\Objects;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestEvents extends WP_UnitTestCase
{

    /** Mocked WPDB instance.
     * @var Logable_WPDB
     */
    private $wpdb;

    /** @var Connection */
    private $connection;

    public function setUp(): void
    {
        $this->wpdb = new Logable_WPDB();
        parent::setUp();

        $this->connection = new Connection($this->wpdb);
    }

    /**
     * Generates a query builder helper.
     *
     * @param string|null $prefix
     * @return \Pixie\QueryBuilder\QueryBuilderHandler
     */
    public function queryBuilderProvider(): QueryBuilderHandler
    {
        return new QueryBuilderHandler($this->connection);
    }

    /**
     * Returns a simple closure that returns whatever string is passed.
     *
     * @param mixed $returns
     * @return \Closure
     */
    public function createClosure($returns): Closure
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

    /** @testdox It should be possible to register an event on before-select which will short circuit a get() call if returns anything but null. */
    public function testEventBeforeSelectWillShortCircuitGet(): void
    {
        $events = $this->connection->getEventHandler();
        $events->registerEvent('before-select', 'foo', $this->createClosure('This should skip the query being executed.'));
        $result = $this->queryBuilderProvider()->table('foo')->get();

        $this->assertEmpty($this->wpdb->usage_log);
        $this->assertEquals('This should skip the query being executed.', $result);
        $this->assertContains('before-selectfoo', Objects::get_property($events, 'firedEvents'));
    }

    /** @testdox It should be possible to register an event on before-select which will NOT short circuit a get() call if returns null. */
    public function testEventBeforeSelectWillNotShortCircuitGet(): void
    {
        // Mock the WPDB return
        $this->wpdb->then_return = ['id' => 1, 'text' => 'MOCK'];

        $events = $this->connection->getEventHandler();
        $events->registerEvent('before-select', 'foo', $this->createClosure(null));
        $result = $this->queryBuilderProvider()->table('foo')->get();


        $this->assertNotEmpty($this->wpdb->usage_log);
        $this->assertEquals("SELECT * FROM foo", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals('MOCK', $result['text']);
        $this->assertEquals(1, $result['id']);
        $this->assertContains('before-selectfoo', Objects::get_property($events, 'firedEvents'));
    }
}
