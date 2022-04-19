<?php

declare(strict_types=1);

/**
 * Tests to ensure the Query Builder creates valid SQL queries.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\QueryBuilderHandler;

use Pixie\Binding;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use PhpMyAdmin\SqlParser\Parser;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\Tests\SQLAssertionsTrait;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestQueryBuilderSQLGeneration extends WP_UnitTestCase
{
    use SQLAssertionsTrait;

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

    /** @testdox It should be possible to define the table to use in a select query, with more mysql feeling syntax $db->select('*')->from('table') */
    public function testDefineFromTableNames(): void
    {
        $builder = $this->queryBuilderProvider()
        ->select(['foo.id', 'bar.id'])
        ->from('foo')
        ->from('bar');

        $this->assertEquals('SELECT foo.id, bar.id FROM foo, bar', $builder->getQuery()->getSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query for multiple tables. */
    public function testMultiTableQuery(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo', 'bar');

        $this->assertEquals('SELECT * FROM foo, bar', $builder->getQuery()->getSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to do a quick and simple find using a single key value  */
    public function testSimpleFind(): void
    {
        // Using the assumed `id` as key
        $builder = $this->queryBuilderProvider()
        ->table('foo')->find(1);
        // Check the passed query to prepare.
        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('SELECT * FROM foo WHERE id = 1 LIMIT 1', $log['query']);
        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);

        // With custom key
        $builder = $this->queryBuilderProvider()
        ->table('foo')->find(2, 'custom');

        $log = $this->wpdb->usage_log['get_results'][1];
        $this->assertEquals('SELECT * FROM foo WHERE custom = 2 LIMIT 1', $log['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    /** @testdox It should be possible to create a select query for specified fields. */
    public function testSelectFields(): void
    {
        // Singe column
        $builder = $this->queryBuilderProvider()
        ->table('foo')
        ->select('single');

        $this->assertEquals('SELECT single FROM foo', $builder->getQuery()->getSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());

        // Multiple
        $builderMulti = $this->queryBuilderProvider()
        ->table('foo')
        ->select(['tree', 'dual']);

        $this->assertEquals('SELECT tree, dual FROM foo', $builderMulti->getQuery()->getSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMulti->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use aliases with the select fields. */
    public function testSelectWithAliasForColumns(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo')
        ->select(['single' => 'sgl', 'foo' => 'bar']);

        $this->assertEquals('SELECT single AS sgl, foo AS bar FROM foo', $builder->getQuery()->getSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to select distinct values, either individually or multiple columns. */
    public function testSelectDistinct(): void
    {
        // Singe column
        $builder = $this->queryBuilderProvider()
        ->table('foo')
        ->selectDistinct('single');

        $this->assertEquals('SELECT DISTINCT single FROM foo', $builder->getQuery()->getSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());

        // Multiple
        $builderMulti = $this->queryBuilderProvider()
        ->table('foo')
        ->selectDistinct(['foo', 'dual']);

        $this->assertEquals('SELECT DISTINCT foo, dual FROM foo', $builderMulti->getQuery()->getSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMulti->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to call findAll() and have the values prepared using WPDB::prepare() */
    public function testFindAll(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('my_table')->findAll('name', 'Sana');

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('SELECT * FROM my_table WHERE name = \'Sana\'', $log['query']);
        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    /** @testdox It should be possible to create a where condition but only return the first value and have this generated and run through WPDB::prepare() */
    public function testFirstWithWhereCondition(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('bar', '=', 'value')->first();

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('SELECT * FROM foo WHERE bar = \'value\' LIMIT 1', $log['query']);
        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    /** @testdox It should be possible to do a query which gets a count of all rows using sql `count()` */
    public function testSelectCount(): void
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->select('*')->where('tree', '=', 'value')->count();

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals("SELECT COUNT(*) AS aggregateValue FROM (SELECT * FROM foo WHERE tree = 'value') as count LIMIT 1", $log['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    ################################################
    ##              WHERE CONDITIONS              ##
    ################################################


    /** @testdox It should be possible to create a query which uses Where and Where not (using AND condition) */
    public function testWhereAndWhereNot(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', '=', 'value')
        ->where('tree2', '=', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' AND tree2 = 'value2'", $builderWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());


        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNot('tree', '<', 'value')
        ->whereNot('tree2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE NOT tree < 'value' AND NOT tree2 > 'value2'", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', '=', 'value')
        ->whereNot('tree2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' AND NOT tree2 > 'value2'", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where and Where not (using OR condition) */
    public function testWhereOrWhereNot(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhere('tree', '=', 'value')
        ->orWhere('tree2', '=', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' OR tree2 = 'value2'", $builderWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereNot('tree', '<', 'value')
        ->orWhereNot('tree2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE NOT tree < 'value' OR NOT tree2 > 'value2'", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhere('tree', '=', 'value')
        ->orWhereNot('tree2', '>', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' OR NOT tree2 > 'value2'", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where In and Where not In (using AND condition) */
    public function testWhereInAndWhereNotIn(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->whereIn('baz', ['v1', 'v2'])
        ->whereIn('baz2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE baz IN ('v1', 'v2') AND baz2 IN (2, 12)", $builderWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNotIn('baz', ['v1', 'v2'])
        ->whereNotIn('baz2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE baz NOT IN ('v1', 'v2') AND baz2 NOT IN (2, 12)", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNotIn('baz', ['v1', 'v2'])
        ->whereIn('baz2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE baz NOT IN ('v1', 'v2') AND baz2 IN (2, 12)", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where In and Where not In (using OR condition) */
    public function testWhereInOrWhereNotIn(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereIn('tree', ['v1', 'v2'])
        ->orWhereIn('tree2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE tree IN ('v1', 'v2') OR tree2 IN (2, 12)", $builderWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereNotIn('tree', ['v1', 'v2'])
        ->orWhereNotIn('tree2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE tree NOT IN ('v1', 'v2') OR tree2 NOT IN (2, 12)", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereNotIn('tree', ['v1', 'v2'])
        ->orWhereIn('tree2', [2, 12]);
        $this->assertEquals("SELECT * FROM foo WHERE tree NOT IN ('v1', 'v2') OR tree2 IN (2, 12)", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where Null and Where not Null (using AND condition) */
    public function testWhereNullAndWhereNotNull(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNull('tree')
        ->whereNull('tree2');
        $this->assertEquals("SELECT * FROM foo WHERE tree IS NULL AND tree2 IS NULL", $builderWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNotNull('tree')
        ->whereNotNull('tree2');
        $this->assertEquals("SELECT * FROM foo WHERE tree IS NOT NULL AND tree2 IS NOT NULL", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNotNull('tree')
        ->whereNull('tree2');
        $this->assertEquals("SELECT * FROM foo WHERE tree IS NOT NULL AND tree2 IS NULL", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses Where Null and Where not Null (using OR condition) */
    public function testWhereNullOrWhereNotNull(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereNull('tree')
        ->orWhereNull('tree2');
        $this->assertEquals("SELECT * FROM foo WHERE tree IS NULL OR tree2 IS NULL", $builderWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereNotNull('tree')
        ->orWhereNotNull('tree2');
        $this->assertEquals("SELECT * FROM foo WHERE tree IS NOT NULL OR tree2 IS NOT NULL", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereNotNull('tree')
        ->orWhereNull('tree2');
        $this->assertEquals("SELECT * FROM foo WHERE tree IS NOT NULL OR tree2 IS NULL", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a querying using BETWEEN (AND/OR) 2 values. */
    public function testWhereBetween(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->whereBetween('tree', 'v1', 'v2')
        ->whereBetween('tree2', 2, 12);
        $this->assertEquals("SELECT * FROM foo WHERE tree BETWEEN 'v1' AND 'v2' AND tree2 BETWEEN 2 AND 12", $builderWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());

        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereBetween('tree2', 2, 12)
        ->whereBetween('tree', 'v1', 'v2');
        $this->assertEquals("SELECT * FROM foo WHERE tree2 BETWEEN 2 AND 12 AND tree BETWEEN 'v1' AND 'v2'", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->orWhereBetween('tree', 'v1', 'v2')
        ->orWhereBetween('tree2', 2, 12);
        $this->assertEquals("SELECT * FROM foo WHERE tree BETWEEN 'v1' AND 'v2' OR tree2 BETWEEN 2 AND 12", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use any where() condition and have the operator assumed as = (equals) */
    public function testWhereAssumedEqualsOperator(): void
    {
        $where = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', 'value');
        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value'", $where->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($where->getQuery()->getRawSql());

        $orWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', 'value')
        ->orWhere('tree2', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' OR tree2 = 'value2'", $orWhere->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($orWhere->getQuery()->getRawSql());

        $whereNot = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNot('tree', 'value');
        $this->assertEquals("SELECT * FROM foo WHERE NOT tree = 'value'", $whereNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($whereNot->getQuery()->getRawSql());

        $orWhereNot = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', 'value')
        ->orWhereNot('tree2', 'value2');
        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' OR NOT tree2 = 'value2'", $orWhereNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($orWhereNot->getQuery()->getRawSql());
    }

    ################################################
    ##   GROUP, ORDER BY, LIMIT/OFFSET & HAVING   ##
    ################################################

    /** @testdox It should be possible to create a grouped where condition
     * @group nested
     */
    public function testGroupedWhere(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', '=', 'value')
        ->where(function (QueryBuilderHandler $query) {
            $query->where('tree2', '<>', 'value2');
            $query->orWhere('tree3', '=', 'value3');
        });

        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' AND (tree2 <> 'value2' OR tree3 = 'value3')", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

     /** @testdox It should be possible to create a grouped OR where condition */
    public function testGroupedOrWhere(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', '=', 'value')
        ->orWhere(function (QueryBuilderHandler $query) {
            $query->where('tree2', '<>', 'value2');
            $query->orWhere('tree3', '=', 'value3');
        });

        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' OR (tree2 <> 'value2' OR tree3 = 'value3')", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a grouped OR where NOT condition */
    public function testGroupedWhereNot(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo')
        ->where('tree', '=', 'value')
        ->whereNot(function (QueryBuilderHandler $query) {
            $query->where('tree2', '<>', 'value2')
            ->orWhere('tree3', '=', 'value3');
        });

        $this->assertEquals("SELECT * FROM foo WHERE tree = 'value' AND NOT (tree2 <> 'value2' OR tree3 = 'value3')", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses group by (SINGLE) */
    public function testSingleGroupBy(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo')->groupBy('bar');

        $this->assertEquals("SELECT * FROM foo GROUP BY bar", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which uses group by (Multiple) */
    public function testMultipleGroupBy(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo')->groupBy(['bar', 'baz']);

        $this->assertEquals("SELECT * FROM foo GROUP BY bar, baz", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to order by a single key and specify the direction. */
    public function testOrderBy(): void
    {
        // Assumed ASC (default.)
        $builderDef = $this->queryBuilderProvider()
        ->table('foo')->orderBy('bar');

        $this->assertEquals("SELECT * FROM foo ORDER BY bar ASC", $builderDef->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderDef->getQuery()->getRawSql());

        // Specified DESC
        $builderDesc = $this->queryBuilderProvider()
        ->table('foo')->orderBy(['bar' => 'DESC']);

        $this->assertEquals("SELECT * FROM foo ORDER BY bar DESC", $builderDesc->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderDesc->getQuery()->getRawSql());

        // Using the default
        $builderDesc = $this->queryBuilderProvider()
        ->table('foo')->orderBy('bar', 'DESC');

        $this->assertEquals("SELECT * FROM foo ORDER BY bar DESC", $builderDesc->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderDesc->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use a Raw expression for the order by reference. */
    public function testOrderByRawExpression(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('foo')->orderBy(new Raw('col = %s', ['bar']), 'DESC');
        $this->assertEquals("SELECT * FROM foo ORDER BY col = 'bar' DESC", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to order by multiple keys and specify the direction. */
    public function testOrderByMultiple(): void
    {
        // Assumed ASC (default.)
        $builderDef = $this->queryBuilderProvider()
        ->table('foo')->orderBy(['bar', 'baz']);

        $this->assertEquals("SELECT * FROM foo ORDER BY bar ASC, baz ASC", $builderDef->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderDef->getQuery()->getRawSql());

        // Specified DESC
        $builderDesc = $this->queryBuilderProvider()
        ->table('foo')->orderBy(['bar', 'baz'], 'DESC');

        $this->assertEquals("SELECT * FROM foo ORDER BY bar DESC, baz DESC", $builderDesc->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderDesc->getQuery()->getRawSql());

        // Directions per field.
        $builderDesc = $this->queryBuilderProvider()
        ->table('foo')->orderBy(['bar' => 'ASC', 'baz'], 'DESC');
        $this->assertEquals("SELECT * FROM foo ORDER BY bar ASC, baz DESC", $builderDesc->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderDesc->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to set HAVING in queries.
     * @group having
     */
    public function testHaving(): void
    {
        $builderHaving = $this->queryBuilderProvider()
        ->table('foo')
        ->select('*')
        ->groupBy('baz')
        ->having('foo.real', '=', 'tree');

        $this->assertEquals("SELECT * FROM foo GROUP BY baz HAVING foo.real = 'tree'", $builderHaving->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderHaving->getQuery()->getRawSql());

        $builderMixed = $this->queryBuilderProvider()
        ->table('foo')
        ->select('*')
        ->groupBy('baz')
        ->having('foo.real', '!=', 'tree')
        ->orHaving('foo.bar', '=', 'woop');

        $this->assertEquals("SELECT * FROM foo GROUP BY baz HAVING foo.real != 'tree' OR foo.bar = 'woop'", $builderMixed->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderMixed->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to limit the query */
    public function testLimit(): void
    {
        $builderLimit = $this->queryBuilderProvider()
        ->table('foo')->limit(12);

        $this->assertEquals("SELECT * FROM foo LIMIT 12", $builderLimit->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderLimit->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to set the offset that a query will start return results from */
    public function testOffset()
    {
        $builderOffset = $this->queryBuilderProvider()
        ->table('foo')->offset(12)->limit(6);

        $this->assertEquals("SELECT * FROM foo LIMIT 6 OFFSET 12", $builderOffset->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderOffset->getQuery()->getRawSql());
    }

    #################################################
    ##    JOIN {INNER, LEFT, RIGHT, FULL OUTER}    ##
    #################################################

    /**
     * @testdox It should be possible to create a query using (INNER) join for a relationship
     * @group join
     */
    public function testJoin(): void
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->join('bar', 'bar.id', '=', 'foo.id');

        $this->assertEquals("SELECT * FROM prefix_foo INNER JOIN prefix_bar ON prefix_bar.id = prefix_foo.id", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to create a query using (OUTER) join for a relationship
     * @group join
     */
    public function testOuterJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->outerJoin('bar', 'bar.id', '=', 'foo.id');

        $this->assertEquals("SELECT * FROM prefix_foo FULL OUTER JOIN prefix_bar ON prefix_bar.id = prefix_foo.id", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to create a query using (RIGHT) join for a relationship
     * @group join
     */
    public function testRightJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->rightJoin('bar', 'bar.id', '=', 'foo.id');

        $this->assertEquals("SELECT * FROM prefix_foo RIGHT JOIN prefix_bar ON prefix_bar.id = prefix_foo.id", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to create a query using (LEFT) join for a relationship
     * @group join
     */
    public function testLeftJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->leftJoin('bar', 'bar.id', '=', 'foo.id');

        $this->assertEquals("SELECT * FROM prefix_foo LEFT JOIN prefix_bar ON prefix_bar.id = prefix_foo.id", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to create a query using (CROSS) join for a relationship
     * @group join
     */
    public function testCrossJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->crossJoin('bar', 'bar.id', '=', 'foo.id');

        $this->assertEquals("SELECT * FROM prefix_foo CROSS JOIN prefix_bar ON prefix_bar.id = prefix_foo.id", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to create a query using (INNER) join for a relationship
     * @group join
     */
    public function testInnerJoin()
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('in_')
        ->table('foo')
        ->innerJoin('bar', 'bar.id', '=', 'foo.id');

        $this->assertEquals("SELECT * FROM in_foo INNER JOIN in_bar ON in_bar.id = in_foo.id", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to create a conditional join using multiple ON with AND conditions
     * @group join
     */
    public function testMultipleJoinAndViaClosure()
    {
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->join('bar', function (JoinBuilder $builder) {
            $builder->on('bar.id', '!=', 'foo.id');
            $builder->on('bar.baz', '!=', 'foo.baz');
        });
        $this->assertEquals("SELECT * FROM prefix_foo INNER JOIN prefix_bar ON prefix_bar.id != prefix_foo.id AND prefix_bar.baz != prefix_foo.baz", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to create a conditional join using multiple ON with OR conditions
     * @group join
     */
    public function testMultipleJoinOrViaClosure()
    {
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->join('bar', function (JoinBuilder $builder): void {
            $builder->orOn('bar.id', '!=', 'foo.id');
            $builder->orOn('bar.baz', '!=', 'foo.baz');
        });
        $this->assertEquals("SELECT * FROM prefix_foo INNER JOIN prefix_bar ON prefix_bar.id != prefix_foo.id OR prefix_bar.baz != prefix_foo.baz", $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /**
     * @testdox It should be possible to do a join with a table as an alias
     * @group join
     */
    public function testJoinWithAlias(): void
    {
        $builder = $this->queryBuilderProvider('prefix_')
        ->table('foo')
        ->crossJoin(['bar' => 'foo'], 'bar.id', '=', 'foo.id');
    }


    #################################################
    ##             SUB AND RAW QUERIES             ##
    #################################################

    /** @testdox It should be possible to create a raw query which can be executed with or without binding values. */
    public function testRawQuery(): void
    {
        // Without bindings.
        $builder = $this->queryBuilderProvider(null, 'testRawQuery');
        \testRawQuery::query('SELECT * FROM foo')->get();

        $this->assertEquals('SELECT * FROM foo', $this->wpdb->usage_log['get_results'][0]['query']);

        // Reset the log
        $this->wpdb->usage_log = [];

        // With bindings.
        \testRawQuery::query('SELECT * FROM foo WHERE bar = %s AND baz != %d', ['string', 314])->get();

        $this->assertEquals('SELECT * FROM foo WHERE bar = \'string\' AND baz != 314', $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals('SELECT * FROM foo WHERE bar = %s AND baz != %d', $this->wpdb->usage_log['prepare'][0]['query']);
        $this->assertCount(2, $this->wpdb->usage_log['prepare'][0]['args']);
        $this->assertEquals('string', $this->wpdb->usage_log['prepare'][0]['args'][0]);
        $this->assertEquals(314, $this->wpdb->usage_log['prepare'][0]['args'][1]);

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
    }


    /** @testdox It should be possible to create a raw SQL expression that can be used as in a sub query */
    public function testRawExpression(): void
    {
        $builder = $this->queryBuilderProvider(null, 'testRawExpression');

        // With no bindings.
        /** @var Raw */
        $query = \testRawExpression::raw('SELECT * FROM foo');
        $this->assertInstanceOf(Raw::class, $query);
        $this->assertEquals('SELECT * FROM foo', (string) $query);
        $this->assertIsArray($query->getBindings());
        $this->assertEmpty($query->getBindings());

        // With single value (not array) binding value.
        /** @var Raw */
        $query = \testRawExpression::raw('SELECT * FROM foo WHERE %s = \'single\'', 'single');
        $this->assertContains('single', $query->getBindings());
        $this->assertCount(1, $query->getBindings());

        // With multiple binding values.
        $query = \testRawExpression::raw('SELECT * FROM foo WHERE %s = \'single\'', ['a', 'b']);
        $this->assertContains('a', $query->getBindings());
        $this->assertContains('b', $query->getBindings());
        $this->assertCount(2, $query->getBindings());
    }

    /** @testdox It should be possible to use RAW Expressions are parts of a query */
    public function testNestedQueryWithRawExpressions(): void
    {
        $this->queryBuilderProvider(null, 'AB');
        $query = \AB::table('foo')
        ->select(\AB::raw('count(cb_my_table.id) as tot'))
        ->where('value', '=', 'Ifrah')
        ->where(\AB::raw('bar = %s', 'now'));

        $this->assertEquals("SELECT count(cb_my_table.id) as tot FROM foo WHERE value = 'Ifrah' AND bar = 'now'", $query->getQuery()->getRawSql());// Check for valid SQL syntax

        $this->assertValidSQL($query->getQuery()->getRawSql());
    }


    #################################################
    ##               INSERT & UPDATE               ##
    #################################################

    /** @testdox It should be possible to insert a single row of data and get the row id/key returned. */
    public function testInsertSingle(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 24;

        $newID = $this->queryBuilderProvider()
        ->table('foo')
        ->insert(['name' => 'Sana', 'description' => 'Blah']);

        $this->assertEquals(24, $newID);

        // Check the actual query.
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Sana','Blah')", $this->wpdb->usage_log['get_results'][0]['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to insert multiple rows of data and get the row id/key returned as an array. */
    public function testInsertMultiple(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 7;

        $data = [
        ['name' => 'Sana', 'description' => 'Blah'],
        ['name' => 'Mark', 'description' => 'Woo'],
        ['name' => 'Sam', 'description' => 'Boo'],
        ];

        $newIDs = $this->queryBuilderProvider()
        ->table('foo')
        ->insert($data);

        $this->assertEquals([7, 7, 7], $newIDs); // Will always return 7 as mocked.

        // Check the actual queries.
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Sana','Blah')", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Mark','Woo')", $this->wpdb->usage_log['get_results'][1]['query']);
        $this->assertEquals("INSERT INTO foo (name,description) VALUES ('Sam','Boo')", $this->wpdb->usage_log['get_results'][2]['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][1]['query']);
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][2]['query']);
    }

    /** @testdox It should be possible to use Raw values for MYSQL function and constant values, which are not wrapped in quotes by WPDB. */
    public function testInsertRawValue()
    {
        $this->queryBuilderProvider()
        ->table('foo')->insert([
        'col1' => 'val1',
        'col2' => new Raw('CURRENT_TIMESTAMP')
        ]);
        $this->assertEquals("INSERT INTO foo (col1,col2) VALUES ('val1',CURRENT_TIMESTAMP)", $this->wpdb->usage_log['get_results'][0]['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to an Insert which ignores all errors generated by MYSQL */
    public function testInsertIgnore(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 89;

        $data = [
        ['name' => 'Sana', 'description' => 'Blah'],
        ['name' => 'Mark', 'description' => 'Woo'],
        ['name' => 'Sam', 'description' => 'Boo'],
        ];

        $newIDs = $this->queryBuilderProvider()
        ->table('foo')
        ->insertIgnore($data);

        $this->assertEquals([89, 89, 89], $newIDs);

        // Check the actual queries.
        $this->assertEquals("INSERT IGNORE INTO foo (name,description) VALUES ('Sana','Blah')", $this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertEquals("INSERT IGNORE INTO foo (name,description) VALUES ('Mark','Woo')", $this->wpdb->usage_log['get_results'][1]['query']);
        $this->assertEquals("INSERT IGNORE INTO foo (name,description) VALUES ('Sam','Boo')", $this->wpdb->usage_log['get_results'][2]['query']);
        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][1]['query']);
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][2]['query']);
    }

    /** @testdox It should be possible to create a query which will do an update on a duplicate key  */
    public function testInsertOnDuplicateKey(): void
    {
        // Mock success from single insert
        $this->wpdb->rows_affected = 1;
        $this->wpdb->insert_id = 12;

        $ID = $this->queryBuilderProvider()
        ->table('foo')
        ->onDuplicateKeyUpdate(['name' => 'Baza', 'counter' => 1])
        ->insert(['name' => 'Baza', 'counter' => 2]);

        $this->assertEquals(12, $ID);
        $this->assertEquals("INSERT INTO foo (name,counter) VALUES ('Baza',2) ON DUPLICATE KEY UPDATE name='Baza',counter=1", $this->wpdb->usage_log['get_results'][0]['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to create a REPLACE INTO query and have the values added using WPDB::prepare()*/
    public function testReplace()
    {
        $this->queryBuilderProvider()
        ->table('foo')
        ->replace(['id' => 24, 'name' => 'Glynn', 'doubt' => true]);

        $prepared = $this->wpdb->usage_log['prepare'][0];
        $query = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals('REPLACE INTO foo (id,name,doubt) VALUES (%d,%s,%d)', $prepared['query']);
        $this->assertEquals('REPLACE INTO foo (id,name,doubt) VALUES (24,\'Glynn\',1)', $query['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($query['query']);
    }

    /** @testdox It should be possible to create a query which will delete all rows that the match the criteria defined. Any values should be passed to WPDB::prepare() before being used. */
    public function testDelete()
    {
        $this->queryBuilderProvider()
        ->table('foo')
        ->where('id', '>', 5)
        ->delete();

        $prepared = $this->wpdb->usage_log['prepare'][0];
        $query = $this->wpdb->usage_log['get_results'][0];

        $this->assertEquals('DELETE FROM foo WHERE id > %d', $prepared['query']);
        $this->assertEquals('DELETE FROM foo WHERE id > 5', $query['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($query['query']);
    }

    /** @testdox It should be possible to create a nested query using subQueries. (Example from Readme) */
    public function testSubQueryForTable(): void
    {
        $builder = $this->queryBuilderProvider();

        $subQuery = $builder->table('person_details')
        ->select('details')
        ->where('person_id', '=', 3);


        $query = $builder->table('my_table')
        ->select('my_table.*')
        ->select($builder->subQuery($subQuery, 'table_alias1'));

        $builder->table($builder->subQuery($query, 'table_alias2'))
        ->select('*')
        ->get();

        $this->assertEquals(
            'SELECT * FROM (SELECT my_table.*, (SELECT details FROM person_details WHERE person_id = 3) as table_alias1 FROM my_table) as table_alias2',
            $this->wpdb->usage_log['get_results'][0]['query']
        );

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
    }

    /**
     * @see https://www.mysqltutorial.org/mysql-subquery/
     * @testdox ...find customers whose payments are greater than the average payment using a subquery
     */
    public function testSubQueryExample()
    {
        $builder = $this->queryBuilderProvider();

        $avgSubQuery = $builder->table('payments')->select("AVG(amount)");

        $builder->select(['customerNumber', 'checkNumber', 'amount'])
        ->from('payments')
        ->where('amount', '>', $builder->subQuery($avgSubQuery))
        ->get();

        $this->assertEquals(
            'SELECT customerNumber, checkNumber, amount FROM payments WHERE amount > (SELECT AVG(amount) FROM payments)',
            $this->wpdb->usage_log['get_results'][0]['query']
        );

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
    }

    /**
     * @see https://www.mysqltutorial.org/mysql-subquery/
     * @testdox ...you can use a subquery with NOT IN operator to find the customers who have not placed any orders
     */
    public function testSubQueryInOperatorExample()
    {
        $builder = $this->queryBuilderProvider();

        $avgSubQuery = $builder->table('orders')->selectDistinct("customerNumber");

        $builder->table('customers')
        ->select('customerName')
        ->whereNotIn('customerNumber', $builder->subQuery($avgSubQuery))
        ->get();

        $this->assertEquals(
            'SELECT customerName FROM customers WHERE customerNumber NOT IN (SELECT DISTINCT customerNumber FROM orders)',
            $this->wpdb->usage_log['get_results'][0]['query']
        );

        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][0]['query']);
    }

    /** @testdox It should be possible to use partial expressions as strings and not have quotes added automatically by WPDB::prepare() */
    public function testUseRawValueForUnescapedMysqlConstants(): void
    {
        $this->queryBuilderProvider()->table('foo')->update(['bar' => new Raw('TIMESTAMP')]);
        $this->assertEquals("UPDATE foo SET bar=TIMESTAMP", $this->wpdb->usage_log['get_results'][0]['query']);

        $this->queryBuilderProvider()->table('orders')
        ->select(['Order_ID', 'Product_Name', new Raw("DATE_FORMAT(Order_Date,'%d--%m--%y') as new_date_formate")])
        ->get();
        $this->assertEquals(
            "SELECT Order_ID, Product_Name, DATE_FORMAT(Order_Date,'%d--%m--%y') as new_date_formate FROM orders",
            $this->wpdb->usage_log['get_results'][1]['query']
        );
        // Check for valid SQL syntax
        $this->assertValidSQL($this->wpdb->usage_log['get_results'][1]['query']);
    }

    /** @testdox It should be possible to use a Binding value in a delete where query */
    public function testDeleteUsingBindings(): void
    {
        $this->queryBuilderProvider()
        ->table('foo')
        ->where('id', '>', Binding::asInt(5.112131564))
        ->delete();

        $prepared = $this->wpdb->usage_log['prepare'][0];
        $query = $this->wpdb->usage_log['get_results'][0];

        $this->assertEquals('DELETE FROM foo WHERE id > %d', $prepared['query']);
        $this->assertEquals('DELETE FROM foo WHERE id > 5', $query['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($query['query']);
    }

    /** @testdox It should be possible to use both RAW expressions and Bindings values for doing where in queries. */
    public function testWhereInUsingBindingsAndRawExpressions(): void
    {
        $builderWhere = $this->queryBuilderProvider()
        ->table('foo')
        ->whereIn('tree', [Binding::asString('v1'), Binding::asRaw("'v2'")])
        ->whereIn('tree2', [Binding::asInt(10 / 4), new Raw('%d', 12)]);
        $this->assertEquals("SELECT * FROM foo WHERE tree IN ('v1', 'v2') AND tree2 IN (2, 12)", $builderWhere->getQuery()->getRawSql());


        // Check for valid SQL syntax
        $this->assertValidSQL($builderWhere->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use RAW expressions for the key in whereNull conditions. */
    public function testWhereIsNullUsingRawForColumn(): void
    {
        $builderNot = $this->queryBuilderProvider()
        ->table('foo')
        ->whereNotNull(new Raw('tree'))
        ->whereNotNull('tree2');
        $this->assertEquals("SELECT * FROM foo WHERE tree IS NOT NULL AND tree2 IS NOT NULL", $builderNot->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builderNot->getQuery()->getRawSql());
    }


    /** @testdox It should be possible to do a select from a JSON value, using column->jsonKey1->jsonKey2 */
    public function testSelectWithJSONWithAlias(): void
    {
        $builder = $this->queryBuilderProvider()
        ->table('TableName')
        ->select(['column->foo->bar' => 'alias']);

        $expected = 'SELECT JSON_UNQUOTE(JSON_EXTRACT(column, "$.foo.bar")) AS alias FROM TableName';
        $this->assertEquals($expected, $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use table.column and have the prefix added to the table, even if used as JSON Select query */
    public function testAllColumnsInJSONSelectWithTableDotColumnShouldHavePrefixAdded()
    {
        $builder = $this->queryBuilderProvider('pr_')
        ->table('table')
        ->select(['table.column->foo->bar' => 'alias']);

        $expected = 'SELECT JSON_UNQUOTE(JSON_EXTRACT(pr_table.column, "$.foo.bar")) AS alias FROM pr_table';
        $this->assertEquals($expected, $builder->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($builder->getQuery()->getRawSql());
    }





    /** @testdox It should be possible to use Laravel style arrow selectors for using JSON in order by. */
    public function testOrderByJsonExpression(): void
    {
        $builder = function (): QueryBuilderHandler {
            return $this->queryBuilderProvider()->table('mock_json');
        };

        $query = $builder()->orderBy('single->value->once', 'DESC')->getQuery()->getRawSql();
        $expected = "SELECT * FROM mock_json ORDER BY JSON_UNQUOTE(JSON_EXTRACT(single, \"$.value.once\")) DESC";
        $this->assertEquals($expected, $query);

        // Check for valid SQL syntax
        $this->assertValidSQL($query);

        $query = $builder()->orderBy(['multi->value->three' => 'DESC', 'multi->value' => 'ASC'])->getQuery()->getRawSql();
        $expected = "SELECT * FROM mock_json ORDER BY JSON_UNQUOTE(JSON_EXTRACT(multi, \"$.value.three\")) DESC, JSON_UNQUOTE(JSON_EXTRACT(multi, \"$.value\")) ASC";
        $this->assertEquals($expected, $query);
        // Check for valid SQL syntax
        $this->assertValidSQL($query);
    }

    /** @testdox It should be possible to use groupby with function calls. */
    public function testCount_OrderBy_GroupBy_Complex(): void
    {
        $sql = $this->queryBuilderProvider()
        ->table('Customers')
        ->select(new Raw('COUNT(CustomerID)'), 'Country')
        ->groupBy('Country')
        ->orderBy(new Raw('COUNT(CustomerID)'), 'DESC')
        ->getQuery()->getRawSql();

        $expected = 'SELECT COUNT(CustomerID), Country FROM Customers GROUP BY Country ORDER BY COUNT(CustomerID) DESC';
        $this->assertSame($expected, $sql);
        // Check for valid SQL syntax
        $this->assertValidSQL($sql);
    }

    /**
     * @testdox Examples used in WIKI for having().
     * @group having
     */
    public function testHavingExamplesFromWiki(): void
    {
        // Using SUM function
        $sql = $this->queryBuilderProvider()
        ->table('order_details')
        ->select(['product', 'SUM(quantity)' => '"Total quantity"'])
        ->groupBy('product')
        ->having('SUM(quantity)', '>', 10);
        $expected = 'SELECT product, SUM(quantity) AS "Total quantity" FROM order_details GROUP BY product HAVING SUM(quantity) > 10';
        $this->assertSame($expected, $sql->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($sql->getQuery()->getRawSql());

        // Group by multiple https://stackoverflow.com/questions/14756222/multiple-aggregate-functions-in-having-clause
        $sql = $this->queryBuilderProvider()
        ->table('movies')
        ->select('category_id', 'year_released')
        ->groupBy('year_released')
        ->having('category_id', '<', 4)
        ->having('category_id', '>', 2);
        $expected = 'SELECT category_id, year_released FROM movies GROUP BY year_released HAVING category_id < 4 AND category_id > 2';
        $this->assertSame($expected, $sql->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($sql->getQuery()->getRawSql());

        // Multiple as or Having
        $sql = $this->queryBuilderProvider()
        ->table('movies')
        ->select('category_id', 'year_released')
        ->groupBy('year_released')
        ->having('category_id', '<', 4)
        ->orHaving('category_id', '>', 2);
        $expected = 'SELECT category_id, year_released FROM movies GROUP BY year_released HAVING category_id < 4 OR category_id > 2';
        $this->assertSame($expected, $sql->getQuery()->getRawSql());

        // Check for valid SQL syntax
        $this->assertValidSQL($sql->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use JSON arrow selectors with count() and have a valid SQL query created. */
    public function testCountWithJsonSelectors()
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('tree', '=', 'value')->count('multi->value->three');

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals("SELECT COUNT(JSON_UNQUOTE(JSON_EXTRACT(multi, \"$.value.three\"))) AS aggregateValue FROM (SELECT * FROM foo WHERE tree = 'value') as count LIMIT 1", $log['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    /** @testdox It should be possible to use JSON arrow selectors with min() and have a valid SQL query created. */
    public function testMinWithJsonSelectors()
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('tree', '=', 'value')->min('multi->value->three');

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals("SELECT MIN(JSON_UNQUOTE(JSON_EXTRACT(multi, \"$.value.three\"))) AS aggregateValue FROM (SELECT * FROM foo WHERE tree = 'value') as count LIMIT 1", $log['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    /** @testdox It should be possible to use JSON arrow selectors with max() and have a valid SQL query created. */
    public function testMaxWithJsonSelectors()
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('tree', '=', 'value')->max('multi->value->three');

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals("SELECT MAX(JSON_UNQUOTE(JSON_EXTRACT(multi, \"$.value.three\"))) AS aggregateValue FROM (SELECT * FROM foo WHERE tree = 'value') as count LIMIT 1", $log['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    /** @testdox It should be possible to use JSON arrow selectors with average() and have a valid SQL query created. */
    public function testAverageWithJsonSelectors()
    {
        $builder = $this->queryBuilderProvider();
        $builder->table('foo')->where('tree', '=', 'value')->average('multi->value->three');

        $log = $this->wpdb->usage_log['get_results'][0];
        $this->assertEquals("SELECT AVG(JSON_UNQUOTE(JSON_EXTRACT(multi, \"$.value.three\"))) AS aggregateValue FROM (SELECT * FROM foo WHERE tree = 'value') as count LIMIT 1", $log['query']);

        // Check for valid SQL syntax
        $this->assertValidSQL($log['query']);
    }

    /** @testdox It should be possible to do a simple conditional inline using the fluent API todo a IF statement */
    public function testWhenOnlyIf(): void
    {
        $builder = $this->queryBuilderProvider()->table('foo');

        $builder->when(
            1 == 1,
            function (QueryBuilderHandler $query): void {
                $query->select('pass');
            }
        );

        $builder->when(
            1 === 2,
            function (QueryBuilderHandler $query): void {
                $query->select('fail');
            }
        );

        $sql = $builder->getQuery()->getRawSql();
        $this->assertEquals('SELECT pass FROM foo', $sql);
    }

    /** @testdox It should be possible to do a conditional inline using the fluent API todo an IF/ELSE statement */
    public function testWhenIfElse(): void
    {
        $builder = $this->queryBuilderProvider()->table('foo');
        $builder->when(
            1 !== 1,
            function (QueryBuilderHandler $query): void {
                $query->select('true');
            },
            function (QueryBuilderHandler $query): void {
                $query->select('false');
            }
        );

        $sql = $builder->getQuery()->getRawSql();
        $this->assertEquals('SELECT false FROM foo', $sql);
    }
}
