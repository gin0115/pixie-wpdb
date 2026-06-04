<?php

declare(strict_types=1);

/**
 * Coverage for the statement value objects' full ArrayAccess surface and
 * typed accessors (#56), and the WpDbException factory fallbacks (#26).
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use WP_UnitTestCase;
use Pixie\WpDbException;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\Statement\JoinStatement;
use Pixie\QueryBuilder\Statement\OrderByStatement;
use Pixie\QueryBuilder\Statement\CriteriaStatement;

class TestValueObjectInternals extends WP_UnitTestCase
{
    /** @testdox [#56] A statement supports ArrayAccess writes via a keyed offset. */
    public function testOffsetSetKeyed(): void
    {
        $statement = new CriteriaStatement('a', '=', 1, 'AND');
        $statement['operator'] = '!=';

        $this->assertEquals('!=', $statement['operator']);
        $this->assertTrue(isset($statement['operator']));
    }

    /** @testdox [#56] A statement supports ArrayAccess append with a null offset. */
    public function testOffsetSetAppend(): void
    {
        $statement = new CriteriaStatement('a', '=', 1, 'AND');
        $statement[] = 'appended';

        $this->assertContains('appended', $statement->toArray());
    }

    /** @testdox [#56] A statement supports ArrayAccess unset. */
    public function testOffsetUnset(): void
    {
        $statement = new CriteriaStatement('a', '=', 1, 'AND');
        unset($statement['value']);

        $this->assertFalse(isset($statement['value']));
        $this->assertArrayNotHasKey('value', $statement->toArray());
    }

    /** @testdox [#56] JoinStatement exposes typed getType()/getTable()/getJoinBuilder(). */
    public function testJoinStatementGetters(): void
    {
        $builder   = new \stdClass();
        $statement = new JoinStatement('left', 'comments', $builder);

        $this->assertEquals('left', $statement->getType());
        $this->assertEquals('comments', $statement->getTable());
        $this->assertSame($builder, $statement->getJoinBuilder());
    }

    /** @testdox [#56] OrderByStatement exposes typed getField()/getType(). */
    public function testOrderByStatementGetters(): void
    {
        $statement = new OrderByStatement('created_at', 'DESC');

        $this->assertEquals('created_at', $statement->getField());
        $this->assertEquals('DESC', $statement->getType());
    }

    /** @testdox [#26] WpDbException::fromWpdb() falls back to a default message when last_error is empty. */
    public function testWpDbExceptionUnknownError(): void
    {
        $wpdb = new Logable_WPDB();
        $wpdb->last_error = '';

        $exception = WpDbException::fromWpdb($wpdb);

        $this->assertEquals('Unknown WPDB error', $exception->getMessage());
    }

    /** @testdox [#26] WpDbException::fromWpdb() appends the SQL context when provided. */
    public function testWpDbExceptionWithSql(): void
    {
        $wpdb = new Logable_WPDB();
        $wpdb->last_error = 'Table missing';

        $exception = WpDbException::fromWpdb($wpdb, 'SELECT * FROM nope');

        $this->assertStringContainsString('Table missing', $exception->getMessage());
        $this->assertStringContainsString('[SQL: SELECT * FROM nope]', $exception->getMessage());
    }
}
