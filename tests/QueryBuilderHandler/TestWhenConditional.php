<?php

declare(strict_types=1);

/**
 * SQL generation tests for the Eloquent-style when() conditional (#36).
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\QueryBuilderHandler;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestWhenConditional extends WP_UnitTestCase
{
    private function qb(): QueryBuilderHandler
    {
        return new QueryBuilderHandler(new Connection(new Logable_WPDB(), []));
    }

    /** @testdox [#36] when(true, ...) applies the true closure. */
    public function testAppliesTrueBranchWhenTrue(): void
    {
        $sql = $this->qb()->table('foo')
            ->when(true, function ($q): void {
                $q->where('active', '=', 1);
            })
            ->getQuery()->getRawSql();

        $this->assertEquals('SELECT * FROM foo WHERE active = 1', $sql);
    }

    /** @testdox [#36] when(false, ...) skips the true closure and leaves the query untouched. */
    public function testSkipsTrueBranchWhenFalse(): void
    {
        $sql = $this->qb()->table('foo')
            ->when(false, function ($q): void {
                $q->where('active', '=', 1);
            })
            ->getQuery()->getSql();

        $this->assertEquals('SELECT * FROM foo', $sql);
    }

    /** @testdox [#36] when(false, ..., $false) applies the optional false closure. */
    public function testAppliesFalseBranchWhenFalse(): void
    {
        $sql = $this->qb()->table('foo')
            ->when(
                false,
                function ($q): void {
                    $q->where('active', '=', 1);
                },
                function ($q): void {
                    $q->where('archived', '=', 1);
                }
            )
            ->getQuery()->getRawSql();

        $this->assertEquals('SELECT * FROM foo WHERE archived = 1', $sql);
    }

    /** @testdox [#36] when() returns the builder for fluent chaining when the closure returns nothing. */
    public function testReturnsBuilderForChaining(): void
    {
        $builder = $this->qb()->table('foo');

        $returned = $builder->when(true, function ($q): void {
            $q->where('active', '=', 1);
        });

        $this->assertInstanceOf(QueryBuilderHandler::class, $returned);

        // Chaining continues to work after when().
        $sql = $returned->where('number', '>', 5)->getQuery()->getRawSql();
        $this->assertEquals('SELECT * FROM foo WHERE active = 1 AND number > 5', $sql);
    }

    /** @testdox [#36] when() passes the query builder instance into the closure. */
    public function testClosureReceivesBuilder(): void
    {
        $received = null;
        $builder  = $this->qb()->table('foo');

        $builder->when(true, function ($q) use (&$received): void {
            $received = $q;
        });

        $this->assertSame($builder, $received);
    }
}
