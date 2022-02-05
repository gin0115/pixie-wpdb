<?php

declare(strict_types=1);

/**
 * Tests for the WPDB Adaptor
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\WPDBAdapter;

class TestWPDBAdaptor extends WP_UnitTestCase
{
    /** @var Logable_WPDB Mocked WPDB instance. */
    private $wpdb;

    /** @var Connection */
    private $connection;

    public function setUp(): void
    {
        $this->wpdb = new Logable_WPDB();
        parent::setUp();
    }

    /**
     * Get an Adapter
     *
     * @param string|null $prefix
     * @param string|null $alias
     * @return \Pixie\WPDBAdapter
     */
    public function getAdapter(?string $prefix = null, ?string $alias = null): WPDBAdapter
    {
        $config = $prefix ? ['prefix' => $prefix] : [];
        $this->connection = new Connection($this->wpdb, $config, $alias);
        return new WPDBAdapter($this->connection);
    }

    /** @testdox Attempting to do a select query with no defined table and exception should be thrown. */
    public function testThrowsExceptionAttemptingSelectWithNoTable(): void
    {
        $this->expectExceptionMessage('No table specified');
        $this->expectException(Exception::class);
        $adapter = $this->getAdapter();
        $adapter->select([]);
    }

    /** @testdox When attempting to compile a query, if no criteria is defined the result should also be empty. */
    public function testCriteriaQueryGenerationWillReturnEmptyIfNoCriteriaStatementsDefined(): void
    {
        $criteriaQuery = $this->getAdapter()->criteriaOnly([]);
        $this->assertArrayHasKey('sql', $criteriaQuery);
        $this->assertEmpty($criteriaQuery['sql']);
        $this->assertArrayHasKey('bindings', $criteriaQuery);
        $this->assertEmpty($criteriaQuery['bindings']);
    }

    /** @testdox Attempting to insert data without passing a valid table name, should result in an exception being thrown. */
    public function testAttemptingToInsertWhereNoTableDefinedShouldResultInAnException(): void
    {
        $this->expectExceptionMessage('No table specified');
        $this->expectException(Exception::class);
        $this->getAdapter()->insert([], ['foo' => 'bar']);
    }

    /** @testdox Attempting to insert on duplicate with matching criteria, should throw an exception*/
    public function testAttemptingToInsertOnDuplicateWithNoDuplicateDataShouldResultInAnException(): void
    {
        $this->expectExceptionMessage('No data given');
        $this->expectException(Exception::class);
        $this->getAdapter()->insert(['tables' => ['foo'],'onduplicate' => []], ['foo' => 'bar']);
    }

    /** @testdox Attempting to run an update without defining a table, should throw an exception*/
    public function testAttemptingToUpdateWithNoTableDefinedShouldResultInAnException(): void
    {
        $this->expectExceptionMessage('No table specified');
        $this->expectException(Exception::class);
        $this->getAdapter()->update([], ['foo' => 'bar']);
    }


    /** @testdox Attempting to update without any data, should throw an exception*/
    public function testAttemptingToUpdateWithNoDataDefinedShouldResultInAnException(): void
    {
        $this->expectExceptionMessage('No data given');
        $this->expectException(Exception::class);
        $this->getAdapter()->update(['tables' => ['foo']], []);
    }

    /** @testdox Attempting to run delete without defining a table, should throw an exception*/
    public function testAttemptingToDeleteWithNoTableDefinedShouldResultInAnException(): void
    {
        $this->expectExceptionMessage('No table specified');
        $this->expectException(Exception::class);
        $this->getAdapter()->delete([]);
    }

    /** @testdox It should be possible to infer the sprintf style format placeholder from a value passed. */
    public function testInferType(): void
    {
        $this->assertEquals('%s', $this->getAdapter()->inferType('string'));
        $this->assertEquals('%f', $this->getAdapter()->inferType(3.14));
        $this->assertEquals('%d', $this->getAdapter()->inferType(3));
        $this->assertEquals('%d', $this->getAdapter()->inferType(false));
        $this->assertEquals('', $this->getAdapter()->inferType(['im', 'an', 'array']));
    }
}
