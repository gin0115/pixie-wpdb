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
use Pixie\Event;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\EventHandler;
use Pixie\Tests\Logable_WPDB;
use Gin0115\WPUnit_Helpers\Objects;
use Pixie\QueryBuilder\QueryObject;
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
        // Should still hold empty key.
        $this->assertEmpty($handler->getEvents()['bar']);
    }

    /** @testdox It should be possible to register an event on before-select which will short circuit a get() call if returns anything but null. */
    public function testEventBeforeSelectWillShortCircuitGet(): void
    {
        $events = $this->connection->getEventHandler();
        $events->registerEvent(Event::BEFORE_SELECT, 'foo', $this->createClosure('This should skip the query being executed.'));
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

    /** @testdox It should be possible to register an event that is fired after a select query, which holds the query object, results and time taken. */
    public function testEventAfterSelect(): void
    {
        // Mock the WPDB return
        $this->wpdb->then_return = ['id' => 1, 'text' => 'MOCK'];

        // Data from event.
        $data = array();

        $this->connection->getEventHandler()
            ->registerEvent(Event::AFTER_SELECT, 'foo', function (QueryBuilderHandler $query, array $results, int $time) use (&$data) {
                $data['query'] = $query;
                $data['results'] = $results;
                $data['time'] = $time;
            });
        $results = $this->queryBuilderProvider()->table('foo')->get();

        $this->assertArrayHasKey('query', $data);
        $this->assertInstanceOf(QueryBuilderHandler::class, $data['query']);
        $this->assertArrayHasKey('results', $data);
        $this->assertIsArray($data['results']);
        $this->assertEquals($results, $data['results']);
        $this->assertArrayHasKey('time', $data);
        $this->assertIsInt($data['time']);
    }

    /** INSERT */

    /** @testdox It should be possible to register an event on before-insert which will short circuit a get() call if returns anything but null. */
    public function testEventBeforeInsertWillShortCircuitGet(): void
    {
        $events = $this->connection->getEventHandler();
        $events->registerEvent(Event::BEFORE_INSERT, 'foo', $this->createClosure('This should skip the query being executed.'));
        $result = $this->queryBuilderProvider()->table('foo')->insert(['bar' => 'baz']);

        $this->assertEmpty($this->wpdb->usage_log);
        $this->assertEquals('This should skip the query being executed.', $result);
        $this->assertContains('before-insertfoo', Objects::get_property($events, 'firedEvents'));
    }

    /** @testdox It should be possible to register an event on before-insert which will NOT short circuit a get() call if returns null. */
    public function testEventBeforeInsertWillNotShortCircuitGet(): void
    {
        // Mock the WPDB return
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 10;

        $events = $this->connection->getEventHandler();
        $events->registerEvent('before-insert', 'foo', $this->createClosure(null));
        $result = $this->queryBuilderProvider()->table('foo')->insert(['bar' => 'baz']);

        $this->assertNotEmpty($this->wpdb->usage_log);
        $this->assertEquals("INSERT INTO foo (bar) VALUES ('baz')", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals(10, $result);
        $this->assertContains('before-insertfoo', Objects::get_property($events, 'firedEvents'));
    }

    /** @testdox It should be possible to register an event that is fired after a select query, which holds the query object, results and time taken. */
    public function testEventAfterInsert(): void
    {
        // Mock the WPDB return
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 10;

        // Data from event.
        $data = array();

        $this->connection->getEventHandler()
            ->registerEvent(Event::AFTER_INSERT, 'foo', function (QueryBuilderHandler $query, ?int $results, int $time) use (&$data) {
                $data['query'] = $query;
                $data['results'] = $results;
                $data['time'] = $time;
            });
        $result = $this->queryBuilderProvider()->table('foo')->insert(['bar' => 'baz']);

        $this->assertArrayHasKey('query', $data);
        $this->assertInstanceOf(QueryBuilderHandler::class, $data['query']);

        $this->assertArrayHasKey('results', $data);
        $this->assertIsInt($data['results']);
        $this->assertEquals($result, $data['results']);

        $this->assertArrayHasKey('time', $data);
        $this->assertIsInt($data['time']);
    }


    /** UPDATE */

    /** @testdox It should be possible to register an event on before-update which will short circuit a get() call if returns anything but null. */
    public function testEventBeforeUpdateWillShortCircuitGet(): void
    {
        $events = $this->connection->getEventHandler();
        $events->registerEvent(Event::BEFORE_UPDATE, 'foo', $this->createClosure(9999));
        $result = $this->queryBuilderProvider()->table('foo')->where('id', 1)->update(['bar' => 'baz']);

        $this->assertEmpty($this->wpdb->usage_log);
        $this->assertEquals(9999, $result);
        $this->assertContains('before-updatefoo', Objects::get_property($events, 'firedEvents'));
    }

    /** @testdox It should be possible to register an event on before-update which will NOT short circuit a get() call if returns null. */
    public function testEventBeforeUpdateWillNotShortCircuitGet(): void
    {
        // Mock the WPDB return
        $this->wpdb->rows_affected = 2;
        $this->wpdb->insert_id = 24;

        $events = $this->connection->getEventHandler();
        $events->registerEvent('before-update', 'foo', $this->createClosure(null));
        $result = $this->queryBuilderProvider()->table('foo')->where('id', 1)->update(['bar' => 'baz']);

        $this->assertNotEmpty($this->wpdb->usage_log);
        $this->assertEquals("UPDATE foo SET bar='baz' WHERE id = 1", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals(2, $result);
        $this->assertContains('before-updatefoo', Objects::get_property($events, 'firedEvents'));
    }

    /** @testdox It should be possible to register an event that is fired after a select query, which holds the query object, results and time taken. */
    public function testEventAfterUpdate(): void
    {
        // Mock the WPDB return
        $this->wpdb->rows_affected = 2;
        $this->wpdb->insert_id = 24;

        // Data from event.
        $data = array();

        $this->connection->getEventHandler()
            ->registerEvent(Event::AFTER_UPDATE, 'foo', function (QueryBuilderHandler $query, QueryObject $queryObject, int $time) use (&$data) {
                $data['query'] = $query;
                $data['queryObject'] = $queryObject;
                $data['time'] = $time;
            });
        $result = $this->queryBuilderProvider()->table('foo')->where('id', 1)->update(['bar' => 'baz']);

        $this->assertArrayHasKey('query', $data);
        $this->assertInstanceOf(QueryBuilderHandler::class, $data['query']);

        $this->assertArrayHasKey('queryObject', $data);
        $this->assertInstanceOf(QueryObject::class, $data['queryObject']);

        $this->assertArrayHasKey('time', $data);
        $this->assertIsInt($data['time']);
    }

    /** DELETE */

    /** @testdox It should be possible to register an event on before-delete which will short circuit a get() call if returns anything but null. */
    public function testEventBeforeDeleteWillShortCircuitGet(): void
    {
        $events = $this->connection->getEventHandler();
        $events->registerEvent(Event::BEFORE_DELETE, 'foo', $this->createClosure('This should skip the query being executed.'));
        $result = $this->queryBuilderProvider()->table('foo')->where('id', 1)->delete();

        $this->assertEmpty($this->wpdb->usage_log);
        $this->assertEquals('This should skip the query being executed.', $result);
        $this->assertContains('before-deletefoo', Objects::get_property($events, 'firedEvents'));
    }

    /** @testdox It should be possible to register an event on before-delete which will NOT short circuit a get() call if returns null. */
    public function testEventBeforeDeleteWillNotShortCircuitGet(): void
    {
        // Mock the WPDB return
        $this->wpdb->rows_affected = 2;
        $this->wpdb->insert_id = 24;

        $events = $this->connection->getEventHandler();
        $events->registerEvent('before-delete', 'foo', $this->createClosure(null));
        $result = $this->queryBuilderProvider()->table('foo')->where('id', 1)->delete();

        $this->assertNotEmpty($this->wpdb->usage_log);
        $this->assertEquals("DELETE FROM foo WHERE id = 1", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals(2, $result);
        $this->assertContains('before-deletefoo', Objects::get_property($events, 'firedEvents'));
    }

    /** @testdox It should be possible to register an event that is fired after a select query, which holds the query object, results and time taken. */
    public function testEventAfterDelete(): void
    {
        // Mock the WPDB return
        $this->wpdb->rows_affected = 2;
        $this->wpdb->insert_id = 24;

        // Data from event.
        $data = array();

        $this->connection->getEventHandler()
            ->registerEvent(Event::AFTER_DELETE, 'foo', function (QueryBuilderHandler $query, QueryObject $queryObject, int $time) use (&$data) {
                $data['query'] = $query;
                $data['queryObject'] = $queryObject;
                $data['time'] = $time;
            });
        $this->queryBuilderProvider()->table('foo')->where('id', 1)->delete();

        $this->assertArrayHasKey('query', $data);
        $this->assertInstanceOf(QueryBuilderHandler::class, $data['query']);

        $this->assertArrayHasKey('queryObject', $data);
        $this->assertInstanceOf(QueryObject::class, $data['queryObject']);

        $this->assertArrayHasKey('time', $data);
        $this->assertIsInt($data['time']);
    }
}
