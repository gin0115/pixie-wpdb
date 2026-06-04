<?php

declare(strict_types=1);

/**
 * Integration tests for the opt-in "throw on WPDB error" behaviour (#26).
 *
 * Verifies that, when a connection is configured with
 * Connection::THROW_ON_ERROR, a WPDB error during execution raises a
 * WpDbException, and that the default behaviour (flag off) is unchanged.
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\QueryBuilderHandler;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\WpDbException;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestThrowOnWpdbError extends WP_UnitTestCase
{
    /** @var \wpdb */
    private $wpdb;

    public function setUp(): void
    {
        global $wpdb;
        $this->wpdb = clone $wpdb;
        // The WP test bootstrap suppresses error output; ensure last_error is
        // still captured (suppress_errors does not clear last_error).
        $this->wpdb->suppress_errors(true);
        parent::setUp();
    }

    /**
     * Builds a query builder bound to a connection with the given config.
     *
     * @param array<string, mixed> $config
     */
    private function builder(array $config = []): QueryBuilderHandler
    {
        $connection = new Connection($this->wpdb, $config);
        return new QueryBuilderHandler($connection);
    }

    /** @testdox [#26] With THROW_ON_ERROR enabled, a select against a missing table throws a WpDbException. */
    public function testThrowsOnSelectError(): void
    {
        $builder = $this->builder([Connection::THROW_ON_ERROR => true]);

        $this->expectException(WpDbException::class);

        $builder->table('pixie_no_such_table_26')->get();
    }

    /** @testdox [#26] The thrown WpDbException is a Pixie Exception and carries the WPDB error message. */
    public function testExceptionTypeAndMessage(): void
    {
        $builder = $this->builder([Connection::THROW_ON_ERROR => true]);

        try {
            $builder->table('pixie_no_such_table_26')->get();
            $this->fail('Expected WpDbException was not thrown.');
        } catch (WpDbException $e) {
            $this->assertInstanceOf(\Pixie\Exception::class, $e);
            $this->assertInstanceOf(\Exception::class, $e);
            $this->assertNotEmpty($e->getMessage());
            $this->assertStringContainsString($this->wpdb->last_error, $e->getMessage());
        }
    }

    /** @testdox [#26][BC] Without the flag, a select against a missing table does NOT throw and returns null. */
    public function testDoesNotThrowByDefault(): void
    {
        $builder = $this->builder();

        $result = $builder->table('pixie_no_such_table_26')->get();

        $this->assertEmpty($result);
    }

    /** @testdox [#26] With THROW_ON_ERROR enabled, an insert against a missing table throws a WpDbException. */
    public function testThrowsOnInsertError(): void
    {
        $builder = $this->builder([Connection::THROW_ON_ERROR => true]);

        $this->expectException(WpDbException::class);

        $builder->table('pixie_no_such_table_26')->insert(['string' => 'a']);
    }

    /** @testdox [#26] When a query succeeds, no exception is thrown even with THROW_ON_ERROR enabled. */
    public function testNoThrowOnSuccessfulQuery(): void
    {
        // mock_foo is created by the sibling integration suite; create defensively.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta(
            "CREATE TABLE mock_foo (
             id mediumint(8) unsigned NOT NULL auto_increment,
             string varchar(255) NULL,
             number int NULL,
             PRIMARY KEY  (id)
            ) COLLATE {$this->wpdb->collate}"
        );

        $builder = $this->builder([Connection::THROW_ON_ERROR => true]);
        $result  = $builder->table('mock_foo')->get();

        // No exception; an empty table returns an empty result set.
        $this->assertEmpty($result);
    }
}
