<?php

declare(strict_types=1);

/**
 * Unit tests for the Query Builder Handler class.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\Transaction;
use Pixie\Exception as PixieException;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Pixie\QueryBuilder\TransactionHaltException;

class TestQueryBuilderHandler extends WP_UnitTestCase
{

    /** Mocked WPDB instance.
     * @var Logable_WPDB
     */
    private $wpdb;

    public function setUp(): void
    {
        $this->wpdb = new Logable_WPDB();
        parent::setUp();
    }

    /**
     * Generates a query builder helper.
     *
     * @param string|null $prefix
     * @return \Pixie\QueryBuilder\QueryBuilderHandler
     */
    public function queryBuilderProvider(?string $prefix = null, ?string $alias = null): QueryBuilderHandler
    {
        $config = $prefix ? ['prefix' => $prefix] : [];
        $connection = new Connection($this->wpdb, $config, $alias);
        return new QueryBuilderHandler($connection);
    }

    /**
     * @testdox It should not be possible to create a handler without either passing a connection of one already existing stored as a static to the class.
     * @runInSeparateProcess Run in own process due to static property.
     * @preserveGlobalState disabled
     */
    public function testCantInitialiseWithoutConnection()
    {
        $this->expectExceptionMessage('No initial instance of Connection created');
        $this->expectException(Exception::class);
        new QueryBuilderHandler();
    }

    /** @testdox It should be possible to change the connection being used and access not only the connection, but the underlying MYSQL connection (wpdb) */
    public function testSetGetConnection(): void
    {
        $builder = $this->queryBuilderProvider('prefix_');
        $initialConnection = $builder->getConnection();
        $this->assertSame($this->wpdb, $builder->dbInstance());

        $connection = new Connection($this->createMock('wpdb'), []);
        $builder->setConnection($connection);
        $this->assertSame($connection, $builder->getConnection());
        $this->assertNotSame($connection, $initialConnection);
    }

    /** @testdox It should be possible to create a new query builder instance, using either the current connection or a custom one. */
    public function testCreateNewQuery(): void
    {
        $builder = $this->queryBuilderProvider('prefix_');

        // Using the same connection.
        $copyBuilder = $builder->newQuery();
        $this->assertSame($builder->getConnection(), $copyBuilder->getConnection());

        // Using custom connection.
        $connection = new Connection($this->createMock(\wpdb::class), []);
        $customBuilder = $builder->newQuery($connection);
        $this->assertSame($connection, $customBuilder->getConnection());
    }

    /** @testdox It should be possible to carry out a number of queries as part of a transaction and commit the results manually. */
    public function testTransactionsWithCommit(): void
    {
        $this->queryBuilderProvider()
            ->transaction(function (Transaction $builder) {
                $builder->table('foo')->insert(['name' => 'Dave']);
                $builder->table('foo')->insert(['name' => 'Jane']);
                $builder->commit();
            });

        $this->assertSame(["START TRANSACTION", "COMMIT"], $this->wpdb->usage_log['query']);
        $this->assertEquals("INSERT INTO foo (name) VALUES ('Dave')", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals("INSERT INTO foo (name) VALUES ('Jane')", $this->wpdb->usage_log['get_results'][1]['query']);
    }

    /** @testdox It should be possible to carry out a number of queries as part of a transaction and rollback the results manually. */
    public function testTransactionWithRollback(): void
    {
        $this->queryBuilderProvider()
            ->transaction(function (Transaction $builder) {
                $builder->table('foo')->insert(['name' => 'Dave']);
                $builder->table('foo')->insert(['name' => 'Jane']);
                $builder->rollback();
            });
        $this->assertSame(["START TRANSACTION", "ROLLBACK"], $this->wpdb->usage_log['query']);
        $this->assertTrue(true, 'Avoids issues with no assertion in test!');
    }

    /** @testdox It should be possible to use WPDB errors which are printed to the screen as a trigger for auto rollback with a transaction. This mimics PDO */
    public function testTransactionCatchWPDBError(): void
    {
        $this->queryBuilderProvider()
            ->transaction(function (Transaction $builder) {
                $builder->table('foo')->insert(['name' => 'Dave']);
                print('WPDB ERROR - Insert name=Dave');
            });
        $this->assertSame(["START TRANSACTION", "ROLLBACK"], $this->wpdb->usage_log['query']);
        $this->assertEquals("INSERT INTO foo (name) VALUES ('Dave')", $this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to catch an exceptions and trigger for auto rollback with a transaction. This mimics PDO */
    public function testTransactionCatchException(): void
    {
        $this->queryBuilderProvider()
            ->transaction(function (Transaction $builder) {
                $builder->table('foo')->insert(['name' => 'Dave']);
                throw new Exception("Error Processing Request", 1);
            });
        $this->assertSame(["START TRANSACTION", "ROLLBACK"], $this->wpdb->usage_log['query']);
        $this->assertEquals("INSERT INTO foo (name) VALUES ('Dave')", $this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to run a transaction and have it auto commit if is neither manually rolled back, committed or generates errors. */
    public function testTransactionAutoCommit(): void
    {
        $this->queryBuilderProvider()
            ->transaction(function (Transaction $builder) {
                $builder->table('foo')->insert(['name' => 'Dave']);
                $builder->table('foo')->insert(['name' => 'Jane']);
            });

        $this->assertSame(["START TRANSACTION", "COMMIT"], $this->wpdb->usage_log['query']);
        $this->assertEquals("INSERT INTO foo (name) VALUES ('Dave')", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals("INSERT INTO foo (name) VALUES ('Jane')", $this->wpdb->usage_log['get_results'][1]['query']);
    }

    /** @testdox When calling commit on a transaction, a TransactionHaltException should be thrown */
    public function testTransactionThrowsHaltOnCommit()
    {
        $this->expectException(TransactionHaltException::class);
        $transaction = new Transaction($this->queryBuilderProvider()->getConnection());
        $transaction->commit();
    }

    /** @testdox When calling rollback on a transaction, a TransactionHaltException should be thrown */
    public function testTransactionThrowsHaltOnRollback()
    {
        $this->expectException(TransactionHaltException::class);
        $transaction = new Transaction($this->queryBuilderProvider()->getConnection());
        $transaction->rollback();
    }

    /** @testdox Attempting to do any aggregate (COUNT, SUM, MIN, MAX, AVERAGE) where defined field is not included in the select, should result in an exception being thrown. */
    public function testThrowsExceptionAttemptingToUseAggregateOnFieldNotInSelect()
    {
        $this->expectExceptionMessage("Failed count query - the column bar hasn't been selected in the query.");
        $this->expectException(Exception::class);
        $this->queryBuilderProvider()->select('foo')->count('bar');
    }

    /** @testdox Attempting to do any aggregate (COUNT, SUM, MIN, MAX, AVERAGE) where the table has not been defined should throw an exception.  */
    public function testThrowsExceptionAttemptingToUseAggregateWithoutDefiningATable(): void
    {
        $this->expectExceptionMessage("No table selected");
        $this->expectException(Exception::class);
        $this->queryBuilderProvider()->select('bar')->count('bar');
    }

    /** @testdox Attempting to generate a query from the current definitions, but using an invalid type (NOT 'select', 'insert', 'insertignore', 'replace', 'delete', 'update', 'criteriaonly') and exception should be thrown. */
    public function testThrowsExceptionAttemptingToGetAQueryUsingAnInvalidType(): void
    {
        $this->expectExceptionMessage("INVALID is not a known type");
        $this->expectException(Exception::class);
        $this->queryBuilderProvider()->select('*')->from('foo')->getQuery('INVALID', ['some', 'data']);
    }

    /** @testdox It should be possible to add a prefix to a single or array of table names. Any that are generated by sub queries, will not be prefixed. */
    public function testAddTablePrefix(): void
    {
        $rawQuery = new Raw('SELECT * FROM', ['ignored']);
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo');


        $prefixedArray = $builder->addTablePrefix(
            ['raw' => $rawQuery, 'alias' => 'someTable'],
            false
        );

        $this->assertArrayHasKey('raw', $prefixedArray);
        $this->assertSame($rawQuery, $prefixedArray['raw']);
        $this->assertArrayHasKey('prefix_alias', $prefixedArray);
        $this->assertEquals('someTable', $prefixedArray['prefix_alias']);

        $prefixedSingle = $builder->addTablePrefix(
            'someTable',
            false
        );
        $this->assertEquals('prefix_someTable', $prefixedSingle);
    }

    /** @testdox It should be possible to create a simple joinUsing query for simple FROM tableA JOIN tableB ON tableA.key = tableB.key, using only the table and key. */
    public function testJoinUsing(): void
    {
        $this->queryBuilderProvider()
            ->table('foo')
            ->joinUsing('bar', 'id')
            ->get();

        $this->assertEquals(
            "SELECT * FROM foo INNER JOIN foo.id ON bar.id = foo.id",
            $this->wpdb->usage_log['get_results'][0]['query']
        );
    }

    /** @testdox When attempting to use joinUsing, a base table must be defined or an exception will be thrown */
    public function testJoinUsingThrowsIfNoTableSelected(): void
    {
        $this->expectExceptionMessage('JoinUsing can only be used with a single table set as the base of the query');
        $this->expectException(PixieException::class);

        $this->queryBuilderProvider()
            ->joinUsing('bar', 'id')
            ->get();
    }

        /** @testdox When attempting to use joinUsing, only a single base table must be defined or an exception will be thrown */
    public function testJoinUsingThrowsIfMultipleTableSelected(): void
    {
        $this->expectExceptionMessage('JoinUsing can only be used with a single table set as the base of the query');
        $this->expectException(PixieException::class);

        $this->queryBuilderProvider()
            ->table('a', 'b')
            ->joinUsing('bar', 'id')
            ->get();
    }

    /** @testdox When attemptning to use a JSON expression as a select, using select(). An alias must be supplied, or an exception should be thrown. */
    public function testMustUseAliasWithJsonSelect(): void
    {
        $this->expectExceptionMessage('An alias must be used if you wish to select from JSON Object');
        $this->expectException(Exception::class);
        $this->queryBuilderProvider()
            ->table('a')
            ->select('a->b')
            ->first();
    }
}
