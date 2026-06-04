<?php

declare(strict_types=1);

/**
 * SQL generation tests for table aliasing via the ['table' => 'alias'] syntax (#27).
 *
 * Covers both (a) the table used for query selection (table()/from()) and
 * (b) the table used in a join condition, with and without a table prefix.
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\QueryBuilderHandler;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestTableAlias extends WP_UnitTestCase
{
    private function qb(?string $prefix = null): QueryBuilderHandler
    {
        $config = $prefix ? [Connection::PREFIX => $prefix] : [];
        return new QueryBuilderHandler(new Connection(new Logable_WPDB(), $config));
    }

    /** @testdox [#27] A selection table can be aliased with from(['table' => 'alias']). */
    public function testFromTableAlias(): void
    {
        $sql = $this->qb()->from(['foo' => 'f'])->getQuery()->getSql();
        $this->assertEquals('SELECT * FROM foo AS f', $sql);
    }

    /** @testdox [#27] A selection table can be aliased with table(['table' => 'alias']). */
    public function testSelectTableAlias(): void
    {
        $sql = $this->qb()->table(['foo' => 'f'])->getQuery()->getSql();
        $this->assertEquals('SELECT * FROM foo AS f', $sql);
    }

    /** @testdox [#27] The table prefix is applied to the table, not the alias. */
    public function testSelectTableAliasWithPrefix(): void
    {
        $sql = $this->qb('pr_')->table(['foo' => 'f'])->getQuery()->getSql();
        $this->assertEquals('SELECT * FROM pr_foo AS f', $sql);
    }

    /** @testdox [#27] Aliased and plain tables can be mixed in a single selection. */
    public function testMixedAliasedAndPlainTables(): void
    {
        $sql = $this->qb()->table('foo', ['bar' => 'b'])->getQuery()->getSql();
        $this->assertEquals('SELECT * FROM foo, bar AS b', $sql);
    }

    /** @testdox [#27] A join table can be aliased with the ['table' => 'alias'] syntax. */
    public function testJoinTableAliasAssociative(): void
    {
        $sql = $this->qb()
            ->table('foo')
            ->join(['bar' => 'b'], 'foo.id', '=', 'b.foo_id')
            ->getQuery()->getSql();

        $this->assertEquals('SELECT * FROM foo INNER JOIN bar AS b ON foo.id = b.foo_id', $sql);
    }

    /**
     * @testdox [#27] The join table alias definition prefixes the table, not the alias label.
     *
     * Note: the value side of the ON condition (`b.foo_id`) is still run through
     * the table prefixer, which cannot distinguish an alias from a real table —
     * this is pre-existing condition-prefixing behaviour, independent of #27.
     * The alias definition itself (`pr_bar AS b`) is what #27 covers.
     */
    public function testJoinTableAliasWithPrefix(): void
    {
        $sql = $this->qb('pr_')
            ->table('foo')
            ->join(['bar' => 'b'], 'foo.id', '=', 'b.foo_id')
            ->getQuery()->getSql();

        $this->assertEquals('SELECT * FROM pr_foo INNER JOIN pr_bar AS b ON pr_foo.id = pr_b.foo_id', $sql);
    }

    /** @testdox [#27][BC] The legacy indexed ['table', 'alias'] join syntax still works. */
    public function testJoinTableAliasIndexedStillWorks(): void
    {
        $sql = $this->qb()
            ->table('foo')
            ->join(['bar', 'b'], 'foo.id', '=', 'b.foo_id')
            ->getQuery()->getSql();

        $this->assertEquals('SELECT * FROM foo INNER JOIN bar AS b ON foo.id = b.foo_id', $sql);
    }
}
