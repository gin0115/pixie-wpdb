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

    /** @testdox It should be possible to configure the connection to use a cloned instance of WPDB and have all options added */
    public function testConnectionConfigClonedWPDBInstance(): void
    {
        $wpdb = new Logable_WPDB();

        // As true
        $connection = new Connection($wpdb, [Connection::CLONE_WPDB => true]);
        $connectionInstance = $connection->getDbInstance();
        $this->assertNotSame($wpdb, $connectionInstance);

        $connection2 = new Connection($wpdb, [Connection::CLONE_WPDB => false]);
        $connection2Instance = $connection2->getDbInstance();
        $this->assertSame($wpdb, $connection2Instance);
    }

    /** @testdox It should be possible to set the connection to use the prefix from WPDB and should a custom prefix also be added, WPDB will take priority. */
    public function testCanUseWPDBPrefixWithConnection(): void
    {
        // use WPDB Prefix TRUE, to set form WPDB.
        $wpdb = new Logable_WPDB();
        $wpdb->prefix = 'TEST_';
        $connection = new Connection($wpdb, [Connection::USE_WPDB_PREFIX => true]);
        $this->assertEquals('TEST_', $connection->getAdapterConfig()['prefix']);

        // do not use WPDB Prefix FALSE to not set.
        $connection2 = new Connection($wpdb, [Connection::USE_WPDB_PREFIX => false]);
        $this->assertArrayNotHasKey('prefix', $connection2->getAdapterConfig());

        // using WPDB instance should overrule custom prefix
        $connection3 = new Connection($wpdb, [
            Connection::USE_WPDB_PREFIX => true,
            Connection::PREFIX => 'bar_'
        ]);
        $this->assertEquals('TEST_', $connection3->getAdapterConfig()['prefix']);
        $this->assertNotEquals('bar_', $connection3->getAdapterConfig()['prefix']);
    }

    /** @testdox It should be possible to configure if the connection should show or hide errors */
    public function testShowHideWPDBErrorsConfig(): void
    {
        // As is defined in WPDB, even with clone
        $wpdb1 = new Logable_WPDB();
        $wpdb1->show_errors(true);
        $wpdb1->suppress_errors(false);
        $connection1 = new Connection($wpdb1, [Connection::CLONE_WPDB => true]);
        $connection1WPDBInstance = $connection1->getDbInstance();
        $this->assertTrue($connection1WPDBInstance->show_errors);
        $this->assertFalse($connection1WPDBInstance->suppress_errors);

        // Defined to hide errors.
        $wpdb2 = new Logable_WPDB();
        $wpdb2->show_errors();
        $connection2 = new Connection($wpdb2, [Connection::SHOW_ERRORS => false]);
        $connection2WPDBInstance = $connection2->getDbInstance();
        $this->assertFalse($connection2WPDBInstance->show_errors);
        $this->assertTrue($connection2WPDBInstance->suppress_errors);

        // Defined to show errors
        $wpdb3 = new Logable_WPDB();
        $wpdb3->show_errors();
        $connection3 = new Connection($wpdb3, [Connection::SHOW_ERRORS => true]);
        $connection3WPDBInstance = $connection3->getDbInstance();
        $this->assertTrue($connection3WPDBInstance->show_errors);
        $this->assertFalse($connection3WPDBInstance->suppress_errors);
    }
}
