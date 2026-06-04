<?php

declare(strict_types=1);

/**
 * Tests for the self-contained condition handlers extracted in #31.
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\WPDBAdapter;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Pixie\QueryBuilder\Handler\JoinConditionHandler;
use Pixie\QueryBuilder\Handler\WhereConditionHandler;
use Pixie\QueryBuilder\Handler\TableConditionHandler;
use Pixie\QueryBuilder\Handler\SelectConditionHandler;

class TestConditionHandlers extends WP_UnitTestCase
{
    private function adapter(): WPDBAdapter
    {
        return new WPDBAdapter(new Connection(new Logable_WPDB(), []));
    }

    private function qb(): QueryBuilderHandler
    {
        return new QueryBuilderHandler(new Connection(new Logable_WPDB(), []));
    }

    /** @testdox [#31] TableConditionHandler renders plain and aliased tables. */
    public function testTableHandler(): void
    {
        $handler = new TableConditionHandler($this->adapter());

        $this->assertEquals('foo, bar', $handler->build([0 => 'foo', 1 => 'bar']));
        $this->assertEquals('users AS u', $handler->build(['users' => 'u']));
    }

    /** @testdox [#31] SelectConditionHandler renders plain and aliased fields. */
    public function testSelectHandler(): void
    {
        $handler = new SelectConditionHandler($this->adapter());

        $this->assertEquals('a, b', $handler->build([0 => 'a', 1 => 'b']));
        $this->assertEquals('total AS t', $handler->build(['total' => 't']));
    }

    /** @testdox [#31] WhereConditionHandler builds a typed criteria fragment from real statements. */
    public function testWhereHandler(): void
    {
        $statements = $this->qb()->table('foo')->where('a', '=', 1)->getStatements();

        $handler = new WhereConditionHandler($this->adapter());
        [$criteria, $bindings] = $handler->build($statements, 'wheres', 'WHERE');

        $this->assertStringStartsWith('WHERE', $criteria);
        $this->assertStringContainsString('a =', $criteria);
        $this->assertEquals([1], $bindings);
    }

    /** @testdox [#31] WhereConditionHandler returns an empty fragment when the key is absent. */
    public function testWhereHandlerEmpty(): void
    {
        $handler = new WhereConditionHandler($this->adapter());
        [$criteria, $bindings] = $handler->build([], 'wheres', 'WHERE');

        $this->assertEquals('', $criteria);
        $this->assertEquals([], $bindings);
    }

    /** @testdox [#31] JoinConditionHandler builds a JOIN clause (incl. aliased tables) from real statements. */
    public function testJoinHandler(): void
    {
        $statements = $this->qb()->table('foo')
            ->join(['bar' => 'b'], 'foo.id', '=', 'b.foo_id')
            ->getStatements();

        $handler = new JoinConditionHandler($this->adapter());
        $sql     = $handler->build($statements);

        $this->assertEquals('INNER JOIN bar AS b ON foo.id = b.foo_id', $sql);
    }

    /** @testdox [#31] JoinConditionHandler returns an empty string when there are no joins. */
    public function testJoinHandlerNoJoins(): void
    {
        $handler = new JoinConditionHandler($this->adapter());
        $this->assertEquals('', $handler->build([]));
    }

    /** @testdox [#31] JoinConditionHandler builds a JOIN with a plain (non-aliased) string table. */
    public function testJoinHandlerPlainStringTable(): void
    {
        $statements = $this->qb()->table('foo')
            ->join('bar', 'foo.id', '=', 'bar.foo_id')
            ->getStatements();

        $handler = new JoinConditionHandler($this->adapter());

        $this->assertEquals('INNER JOIN bar ON foo.id = bar.foo_id', $handler->build($statements));
    }

    /** @testdox [#31] JoinConditionHandler builds a JOIN with a Raw table expression. */
    public function testJoinHandlerRawTable(): void
    {
        $statements = $this->qb()->table('foo')
            ->join(new \Pixie\QueryBuilder\Raw('bar'), 'foo.id', '=', 'bar.foo_id')
            ->getStatements();

        $handler = new JoinConditionHandler($this->adapter());

        $this->assertEquals('INNER JOIN bar ON foo.id = bar.foo_id', $handler->build($statements));
    }

    /** @testdox [#31][BC] End-to-end query generation is unchanged after the extraction. */
    public function testEndToEndUnchanged(): void
    {
        $sql = $this->qb()->table('foo')
            ->select(['foo.a', 'foo.b'])
            ->join(['bar' => 'b'], 'foo.id', '=', 'b.foo_id')
            ->where('foo.a', '=', 1)
            ->getQuery()->getRawSql();

        $this->assertEquals(
            'SELECT foo.a, foo.b FROM foo INNER JOIN bar AS b ON foo.id = b.foo_id WHERE foo.a = 1',
            $sql
        );
    }
}
