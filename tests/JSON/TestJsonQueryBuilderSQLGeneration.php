<?php

declare(strict_types=1);

/**
 * Tests to ensure the Query Builder creates valid SQL queries.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\JSON;

use Pixie\Binding;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use PhpMyAdmin\SqlParser\Parser;
use Pixie\JSON\JsonSelectorHandler;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\Tests\SQLAssertionsTrait;
use Pixie\QueryBuilder\jsonQueryBuilder;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestJsonQueryBuilderSQLGeneration extends WP_UnitTestCase
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
     * @return \Pixie\QueryBuilder\jsonQueryBuilder
     */
    public function queryBuilderProvider(?string $prefix = null, ?string $alias = null): QueryBuilderHandler
    {
        $config = $prefix ? ['prefix' => $prefix] : [];
        $connection = new Connection($this->wpdb, $config, $alias);
        return new JsonQueryBuilder($connection);
    }

       /** @testdox It should be possible to use laravel style JSON selectors for whereJson(), whereNotJson(), orWhereJson(), orWhereNotJson() */
    public function testAllowLaravelStyleInWhereJson(): void
    {
        $cases = [
            'where' => [
                'helperMethod' => 'whereJson',
                'withArrows' => 'where'
            ],
            'orWhere' => [
                'helperMethod' => 'orWhereJson',
                'withArrows' => 'orWhere'
            ],
            'whereNot' => [
                'helperMethod' => 'whereNotJson',
                'withArrows' => 'whereNot'
            ],
            'orWhereNot' => [
                'helperMethod' => 'orWhereNotJson',
                'withArrows' => 'orWhereNot'
            ],

            ];

        // Run tests
        foreach ($cases as $method => $values) {
            $helperMethod = $this->queryBuilderProvider()
                ->table('mock_json')
                ->{$values['helperMethod']}('column', ['keya', 'keyb'], '=', 'value');

            $usingArrows = $this->queryBuilderProvider()
                ->table('mock_json')
                ->{$values['withArrows']}('column->keya->keyb', '=', 'value');

            $this->assertSame(
                $helperMethod->getQuery()->getRawSql(),
                $usingArrows->getQuery()->getRawSql(),
                "Failed asserting a match with method :: \"{$method}\""
            );

            // Check producing valid SQL
            $this->assertValidSQL($helperMethod->getQuery()->getRawSql());
            $this->assertValidSQL($usingArrows->getQuery()->getRawSql());
        }
    }

    /** @testdox It should be possible to use laravel style JSON selectors for whereInJson(),, whereNotInJson(), orWhereInJson(), orWhereNotInJson() */
    public function testAllowLaravelStyleInWhereInJson(): void
    {
        $cases = [
            'whereIn' => [
                'helperMethod' => 'whereInJson',
                'withArrows' => 'whereIn'
            ],
            'orWhereIn' => [
                'helperMethod' => 'orWhereInJson',
                'withArrows' => 'orWhereIn'
            ],
            'whereNotIn' => [
                'helperMethod' => 'whereNotInJson',
                'withArrows' => 'whereNotIn'
            ],
            'orWhereNotIn' => [
                'helperMethod' => 'orWhereNotInJson',
                'withArrows' => 'orWhereNotIn'
            ],

            ];

        // Run tests
        foreach ($cases as $method => $values) {
            $helperMethod = $this->queryBuilderProvider()
                ->table('mock_json')
                ->where('a', 'b')
                ->{$values['helperMethod']}('column', ['keya', 'keyb'], ['a','b']);

            $usingArrows = $this->queryBuilderProvider()
                ->table('mock_json')
                ->where('a', 'b')
                ->{$values['withArrows']}('column->keya->keyb', ['a','b']);

            $this->assertSame(
                $helperMethod->getQuery()->getRawSql(),
                $usingArrows->getQuery()->getRawSql(),
                "Failed asserting a match with method :: \"{$method}\""
            );

            // Check producing valid SQL
            $this->assertValidSQL($helperMethod->getQuery()->getRawSql());
            $this->assertValidSQL($usingArrows->getQuery()->getRawSql());
        }
    }

    /** @testdox It should be possible to create a query using (INNER) joinJSON for a relationship [JSON HELPER]*/
    public function testJoinJson(): void
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->joinJson('bar', 'foo.id', ['key1', 'key2'], '=', 'bar.id', 'index[2]');

        // Check the query is as expected
        $this->assertEquals("SELECT * FROM prefix_foo INNER JOIN prefix_bar ON JSON_UNQUOTE(JSON_EXTRACT(prefix_foo.id, \"$.key1.key2\")) = JSON_UNQUOTE(JSON_EXTRACT(prefix_bar.id, \"$.index[2]\"))", $builder->getQuery()->getRawSql());

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (OUTER) joinJSON for a relationship [JSON HELPER]*/
    public function testOuterJoinJson(): void
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->outerJoinJson('bar', 'foo.id', ['key1', 'key2'], '=', 'bar.id', 'index[2]');

        $this->assertEquals("SELECT * FROM prefix_foo FULL OUTER JOIN prefix_bar ON JSON_UNQUOTE(JSON_EXTRACT(prefix_foo.id, \"$.key1.key2\")) = JSON_UNQUOTE(JSON_EXTRACT(prefix_bar.id, \"$.index[2]\"))", $builder->getQuery()->getRawSql());

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (RIGHT) joinJSON for a relationship [JSON HELPER]*/
    public function testRightJoinJson(): void
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->rightJoinJson('bar', 'foo.id', ['key1', 'key2'], '=', 'bar.id', 'index[2]');

        $this->assertEquals("SELECT * FROM prefix_foo RIGHT JOIN prefix_bar ON JSON_UNQUOTE(JSON_EXTRACT(prefix_foo.id, \"$.key1.key2\")) = JSON_UNQUOTE(JSON_EXTRACT(prefix_bar.id, \"$.index[2]\"))", $builder->getQuery()->getRawSql());

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (LEFT) joinJSON for a relationship [JSON HELPER]*/
    public function testLeftJoinJson(): void
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->leftJoinJson('bar', 'foo.id', ['key1', 'key2'], '=', 'bar.id', 'index[2]');

        $this->assertEquals("SELECT * FROM prefix_foo LEFT JOIN prefix_bar ON JSON_UNQUOTE(JSON_EXTRACT(prefix_foo.id, \"$.key1.key2\")) = JSON_UNQUOTE(JSON_EXTRACT(prefix_bar.id, \"$.index[2]\"))", $builder->getQuery()->getRawSql());

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query using (CROSS) joinJSON for a relationship [JSON HELPER]*/
    public function testCrossJoinJson(): void
    {
        // Single Condition
        $builder = $this->queryBuilderProvider('prefix_')
            ->table('foo')
            ->crossJoinJson('bar', 'foo.id', ['key1', 'key2'], '=', 'bar.id', 'index[2]');

        $this->assertEquals("SELECT * FROM prefix_foo CROSS JOIN prefix_bar ON JSON_UNQUOTE(JSON_EXTRACT(prefix_foo.id, \"$.key1.key2\")) = JSON_UNQUOTE(JSON_EXTRACT(prefix_bar.id, \"$.index[2]\"))", $builder->getQuery()->getRawSql());

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a query which gets values form a JSON column, while using RAW object for both the MYSQL col key and JSON object key (1st generation) */
    public function testJsonSelectUsingRawValues(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table('jsonSelects')
            ->selectJson(new Raw('column'), new Raw('foo'));

        $this->assertEquals(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(column, "$.foo")) as json_foo FROM jsonSelects',
            $builder->getQuery()->getRawSql()
        );

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to do a select from a JSON value, using column->jsonKey1->jsonKey2 */
    public function testSelectWithJSONWithAlias(): void
    {
        $builder = $this->queryBuilderProvider()
            ->table('TableName')
            ->select(['column->foo->bar' => 'alias']);

        $expected = 'SELECT JSON_UNQUOTE(JSON_EXTRACT(column, "$.foo.bar")) AS alias FROM TableName';
        $this->assertEquals($expected, $builder->getQuery()->getRawSql());

        // Check for valid query
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

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to create a WHERE clause that allows OR NOT conditions, from traversing the JSON object. */
    public function testJsonOrWhereNot()
    {
        $builder = $this->queryBuilderProvider()
            ->table('mock_json')
            ->orWhereNotJson('jsonCol', ['string'], '=', 'AB')
            ->orWhereNotJson('jsonCol', ['thing','handle'], '=', 'bar');

        $expected = "SELECT * FROM mock_json WHERE NOT JSON_UNQUOTE(JSON_EXTRACT(jsonCol, \"$.string\")) = 'AB' OR NOT JSON_UNQUOTE(JSON_EXTRACT(jsonCol, \"$.thing.handle\")) = 'bar'";
        $this->assertEquals($expected, $builder->getQuery()->getRawSql());

        // Check for valid query
         $this->assertValidSQL($builder->getQuery()->getRawSql());
    }

    /** @testdox It should be possible to use Json Where conditions and have the operation assumed as = to shorten the syntax */
    public function testJsonWhereAssumesEqualsOperation(): void
    {
        $helperMethod = $this->queryBuilderProvider()
        ->table('foo')
            ->whereNotJson('col1', ['a1', 'b1'], 'val1')
            ->orWhereJson('col2', ['a2', 'b2'], 'val2')
            ->orWhereNotJson('col3', ['a3', 'b3'], 'val3');
        $whereNotJson = 'WHERE NOT JSON_UNQUOTE(JSON_EXTRACT(col1, "$.a1.b1")) = \'val1\'';
        $orWhereJson = 'OR JSON_UNQUOTE(JSON_EXTRACT(col2, "$.a2.b2")) = \'val2\'';
        $orWhereNotJson = 'OR NOT JSON_UNQUOTE(JSON_EXTRACT(col3, "$.a3.b3")) = \'val3\'';

        $sql = $helperMethod->getQuery()->getRawSql();
        $this->assertStringContainsString($whereNotJson, $sql);
        $this->assertStringContainsString($orWhereJson, $sql);
        $this->assertStringContainsString($orWhereNotJson, $sql);



        // Check for valid query
        $parser =  $this->assertValidSQL($sql);
    }

    /** @testdox It should be possible to use a JSON date query and have the assumption be its '=' operator. */
    public function testWhereDataJsonAssumesEquals(): void
    {
        $builder = function () {
            return $this->queryBuilderProvider()->table('mock_json');
        };

        $this->assertSame(
            $builder()->whereMonthJson('jsonCol', 'date', 10)->getQuery()->getRawSql(),
            $builder()->whereMonthJson('jsonCol', 'date', '=', 10)->getQuery()->getRawSql()
        );
        $this->assertSame(
            $builder()->whereDayJson('jsonCol', 'date', 21)->getQuery()->getRawSql(),
            $builder()->whereDayJson('jsonCol', 'date', '=', 21)->getQuery()->getRawSql()
        );
        $this->assertSame(
            $builder()->whereYearJson('jsonCol', 'date', '1978')->getQuery()->getRawSql(),
            $builder()->whereYearJson('jsonCol', 'date', '=', '1978')->getQuery()->getRawSql()
        );
        $this->assertSame(
            $builder()->whereDateJson('jsonCol', 'date', '1978-12-10')->getQuery()->getRawSql(),
            $builder()->whereDateJson('jsonCol', 'date', '=', '1978-12-10')->getQuery()->getRawSql()
        );
    }
}
