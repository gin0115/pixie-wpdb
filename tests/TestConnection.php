<?php

declare(strict_types=1);

/**
 * Unit tests custom WPDB base Pixie connection
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestConnection extends WP_UnitTestCase
{
    /** @testdox It should be possible to create a connection using WPDB */
    public function testWPDBConnection(): void
    {
        $wpdb = new Logable_WPDB();
        $connection = new Connection($wpdb);
        $this->assertSame($wpdb, $connection->getDbInstance());
    }

    /** @testdox It should be possible to set the wpdb instance. */
    public function testSetDbInstance(): void
    {
        // Create with global wpdb
        $connection = new Connection($GLOBALS['wpdb']);
        // Set with custom
        $wpdb = new Logable_WPDB();
        $connection->setDbInstance($wpdb);
        $this->assertSame($wpdb, $connection->getDbInstance());
    }

    /** @testdox It should be possible to create a connection and be able to recall the first instance with a static method. */
    public function testCachesFirstInstance(): void
    {
        // Initial Connection
        $wpdb = new Logable_WPDB();
        $connection1 = new Connection($wpdb);

        // Second Connection
        $connection2 = new Connection($this->createMock('wpdb'));

        // Should use the DB istance from the first.
        $this->assertInstanceOf(Logable_WPDB::class, Connection::getStoredConnection()->getDbInstance());
    }

    /**
     * @testdox Attempting to access the stored connection which has not been set, should result in an exception being thrown
     * @runInSeparateProcess Run in own process due to static property.
     * @preserveGlobalState disabled
     */
    public function testAttemptingToAccessAnUnsetCachedConnectionShouldThrowException(): void
    {
        $this->expectExceptionMessage('No initial instance of Connection created');
        $this->expectException(Exception::class);
        Connection::getStoredConnection();
    }

    /** @testdox It should be possible to create a new query builder instance from a connection */
    public function testGetQueryBuilder(): void
    {
        // Initial Connection
        $wpdb = new Logable_WPDB();
        $connection = new Connection($wpdb);

        $builder = $connection->getQueryBuilder();

        $this->assertInstanceOf(QueryBuilderHandler::class, $builder);
        $this->assertSame($builder->getConnection(), $connection);
    }
}
