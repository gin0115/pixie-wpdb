<?php

declare(strict_types=1);

/**
 * Tests that query statements are now value objects while remaining
 * backwards-compatible with the previous associative-array shape (#56).
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\QueryBuilderHandler;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Pixie\QueryBuilder\Statement\Statement;
use Pixie\QueryBuilder\Statement\JoinStatement;
use Pixie\QueryBuilder\Statement\OrderByStatement;
use Pixie\QueryBuilder\Statement\CriteriaStatement;

class TestStatementObjects extends WP_UnitTestCase
{
    private function qb(): QueryBuilderHandler
    {
        return new QueryBuilderHandler(new Connection(new Logable_WPDB(), []));
    }

    /** @testdox [#56] A where statement is a CriteriaStatement object. */
    public function testWhereIsObject(): void
    {
        $statements = $this->qb()->table('foo')->where('a', '=', 1)->getStatements();

        $this->assertInstanceOf(CriteriaStatement::class, $statements['wheres'][0]);
        $this->assertInstanceOf(Statement::class, $statements['wheres'][0]);
    }

    /** @testdox [#56][BC] A where statement is still readable via array access. */
    public function testWhereArrayAccessCompatible(): void
    {
        $where = $this->qb()->table('foo')->where('a', '=', 1)->getStatements()['wheres'][0];

        $this->assertEquals('a', $where['key']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals(1, $where['value']);
        $this->assertEquals('AND', $where['joiner']);
        $this->assertTrue(isset($where['key']));
    }

    /** @testdox [#56] A where statement exposes typed getters and toArray(). */
    public function testWhereGettersAndToArray(): void
    {
        $where = $this->qb()->table('foo')->where('a', '=', 1)->getStatements()['wheres'][0];

        $this->assertEquals('a', $where->getKey());
        $this->assertEquals('=', $where->getOperator());
        $this->assertEquals(1, $where->getValue());
        $this->assertEquals('AND', $where->getJoiner());
        $this->assertSame(
            ['key' => 'a', 'operator' => '=', 'value' => 1, 'joiner' => 'AND'],
            $where->toArray()
        );
    }

    /** @testdox [#56] A having statement is a CriteriaStatement object. */
    public function testHavingIsObject(): void
    {
        $statements = $this->qb()->table('foo')
            ->groupBy('a')->having('a', '>', 1)->getStatements();

        $this->assertInstanceOf(CriteriaStatement::class, $statements['havings'][0]);
        $this->assertEquals('a', $statements['havings'][0]['key']);
    }

    /** @testdox [#56] An order by statement is an OrderByStatement object. */
    public function testOrderByIsObject(): void
    {
        $statements = $this->qb()->table('foo')->orderBy('a', 'DESC')->getStatements();

        $this->assertInstanceOf(OrderByStatement::class, $statements['orderBys'][0]);
        $this->assertEquals('a', $statements['orderBys'][0]['field']);
        $this->assertEquals('DESC', $statements['orderBys'][0]->getType());
    }

    /** @testdox [#56] A join statement is a JoinStatement object. */
    public function testJoinIsObject(): void
    {
        $statements = $this->qb()->table('foo')
            ->join('bar', 'foo.id', '=', 'bar.foo_id')->getStatements();

        $this->assertInstanceOf(JoinStatement::class, $statements['joins'][0]);
        $this->assertEquals('inner', $statements['joins'][0]['type']);
        $this->assertEquals('bar', $statements['joins'][0]->getTable());
    }

    /** @testdox [#56] A statement object is iterable yielding its fields. */
    public function testStatementIsIterable(): void
    {
        $where = $this->qb()->table('foo')->where('a', '=', 1)->getStatements()['wheres'][0];

        $collected = [];
        foreach ($where as $field => $value) {
            $collected[$field] = $value;
        }

        $this->assertEquals(['key' => 'a', 'operator' => '=', 'value' => 1, 'joiner' => 'AND'], $collected);
    }

    /** @testdox [#56][BC] Generated SQL is unchanged by the statement-object conversion. */
    public function testGeneratedSqlUnchanged(): void
    {
        $sql = $this->qb()->table('foo')
            ->join('bar', 'foo.id', '=', 'bar.foo_id')
            ->where('foo.a', '=', 1)
            ->groupBy('foo.b')
            ->having('foo.a', '>', 0)
            ->orderBy('foo.b', 'DESC')
            ->getQuery()->getRawSql();

        $this->assertEquals(
            'SELECT * FROM foo INNER JOIN bar ON foo.id = bar.foo_id WHERE foo.a = 1 GROUP BY foo.b HAVING foo.a > 0 ORDER BY foo.b DESC',
            $sql
        );
    }
}
