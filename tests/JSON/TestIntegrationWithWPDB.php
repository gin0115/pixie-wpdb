<?php

declare(strict_types=1);

/**
 * Integration test using WP PHPUnit MYSQL instance using JSON Builder.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\JSON;

use DateTime;
use stdClass;
use Exception;
use Pixie\Binding;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\JsonQueryBuilder;
use Pixie\Tests\Fixtures\ModelForMockFoo;
use Pixie\QueryBuilder\QueryBuilderHandler;
use Pixie\Tests\Fixtures\ModelWithMagicSetter;

class TestIntegrationWithWPDB extends WP_UnitTestCase
{
     /** Test WPDB instance.
     * @var \wpdb
    */
    private $wpdb;

    protected static $createdTables = false;

    public function setUp(): void
    {
        global $wpdb;
        $this->wpdb = clone $wpdb;
        parent::setUp();

        if (! static::$createdTables) {
            $this->createTables();
        }
    }

    public function createTables(): void
    {
        $wpdb_collate = $this->wpdb->collate;
        $sqlFoo =
         "CREATE TABLE mock_foo (
         id mediumint(8) unsigned NOT NULL auto_increment ,
         string varchar(255) NULL,
         number int NULL,
         PRIMARY KEY  (id)
         )
         COLLATE {$this->wpdb->collate}";

        $sqlBar =
         "CREATE TABLE mock_bar (
         id mediumint(8) unsigned NOT NULL auto_increment ,
         string varchar(255) NULL,
         number int NULL,
         PRIMARY KEY  (id)
         )
         COLLATE {$this->wpdb->collate}";

        $sqlJson =
         "CREATE TABLE mock_json (
         id mediumint(8) unsigned NOT NULL auto_increment ,
         string varchar(255) NULL,
         jsonCol json NULL,
         PRIMARY KEY  (id)
         )
         COLLATE {$this->wpdb->collate}";

        $sqlDates =
         "CREATE TABLE mock_dates (
         id mediumint(8) unsigned NOT NULL auto_increment ,
         date DATE NULL,
         datetime DATETIME NULL,
         unix TIMESTAMP NULL,
         time TIME NULL,
         PRIMARY KEY  (id)
         )
         COLLATE {$this->wpdb->collate}";

        $sqlUnique = "CREATE TABLE mock_unique (
         id mediumint(8) unsigned NOT NULL auto_increment ,
         email varchar(200) NULL,
         counter int NULL,
         PRIMARY KEY  (id),
         UNIQUE KEY email  (email)
         )
         COLLATE {$this->wpdb->collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlFoo);
        dbDelta($sqlBar);
        dbDelta($sqlJson);
        dbDelta($sqlDates);
        dbDelta($sqlUnique);

        static::$createdTables = true;
    }

    /**
     * Array diff for MD arrays [['id'=>1, 'name'=>'foo']]
     *
     * @param array<string|int, mixed> $array1
     * @param array<string|int, mixed> $array2
     * @return array<string|int, mixed>
     */
    private function arrayDifMD($array1, $array2): array
    {
        foreach ($array1 as $key1 => $value1) {
            if (in_array($value1, $array2)) {
                unset($array1[$key1]);
            }
        }
        return $array1;
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
     * Generates a query builder helper.
     *
     * @param string|null $prefix
     * @return \Pixie\QueryBuilder\QueryBuilderHandler
     */
    public function jsonQueryBuilderProvider(?string $prefix = null, ?string $alias = null): JsonQueryBuilder
    {
        $builder = $this->queryBuilderProvider($prefix, $alias);
        return new JsonQueryBuilder($builder->getConnection());
    }


    /** @testdox It should be possible to select values from inside a JSON object, held in a JSON column type. */
    public function testCanSelectFromWithinJSONColumn1GenDeep()
    {
         $this->wpdb->insert('mock_json', ['string' => 'a', 'jsonCol' => \json_encode((object)['id' => 24748, 'name' => 'Sam'])], ['%s', '%s']);

        $asRaw = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->select('string', new Raw('JSON_UNQUOTE(JSON_EXTRACT(jsonCol, "$.id")) as jsonID'))
            ->get();

        $asSelectJson = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->select('string')
            ->selectJson('jsonCol', "id", 'jsonID')
            ->get();

        $this->assertEquals($asRaw[0]->string, $asSelectJson[0]->string);
        $this->assertEquals($asRaw[0]->jsonID, $asSelectJson[0]->jsonID);

        // Without Alias
        $jsonWoutAlias = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->select('string')
            ->selectJson('jsonCol', "id")
            ->get();

        $this->assertEquals('a', $jsonWoutAlias[0]->string);
        $this->assertEquals('24748', $jsonWoutAlias[0]->json_id);
    }

    /** @testdox It should be possible  */
    public function testCanSelectFromWithinJSONColumn3GenDeep(): void
    {
        $jsonData = (object)[
            'key' => 'apple',
            'data' => (object) [
                'array' => [1,2,3,4],
                'object' => (object) [
                    'obj1' => 'val1',
                    'obj2' => 'val2',
                ]
            ]
        ];
        $this->wpdb->insert('mock_json', ['string' => 'a', 'jsonCol' => \json_encode($jsonData)], ['%s', '%s']);

        // Extract a value from an object
        $objectVal = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->select('string')
            ->selectJson('jsonCol', ['data', 'object', 'obj1'], 'jsonVALUE')
            ->first();

        $this->assertNotNull($objectVal);
        $this->assertEquals('a', $objectVal->string);
        $this->assertEquals('val1', $objectVal->jsonVALUE);

        // Extract an entire array.
        $arrayValues = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->select('string')
            ->selectJson('jsonCol', ['data', 'array'], 'jsonVALUE')
            ->first();

        $this->assertNotNull($arrayValues);
        $this->assertEquals('a', $arrayValues->string);
        $this->assertEquals('[1, 2, 3, 4]', $arrayValues->jsonVALUE);
        $this->assertCount(4, \json_decode($arrayValues->jsonVALUE));
        $this->assertContains('1', \json_decode($arrayValues->jsonVALUE));
        $this->assertContains('2', \json_decode($arrayValues->jsonVALUE));
        $this->assertContains('3', \json_decode($arrayValues->jsonVALUE));
        $this->assertContains('4', \json_decode($arrayValues->jsonVALUE));

        // Pluck a single item from an array using its key.
        $pluckArrayValue = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->select('string')
            ->selectJson('json.jsonCol', ['data', 'array[1]'], 'jsonVALUE')
            ->first();

        $this->assertNotNull($pluckArrayValue);
        $this->assertEquals('a', $pluckArrayValue->string);
        $this->assertEquals('2', $pluckArrayValue->jsonVALUE);
    }

    /** @testdox It should be possible to do a where query that checks a value inside a json value. Tests only 1 level deep */
    public function testJsonWhere1GenDeep()
    {
        $this->wpdb->insert('mock_json', ['string' => 'a', 'jsonCol' => \json_encode((object)['id' => 24748, 'thing' => 'foo'])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'b', 'jsonCol' => \json_encode((object)['id' => 78945, 'thing' => 'foo'])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'b', 'jsonCol' => \json_encode((object)['id' => 78941, 'thing' => 'bar'])], ['%s', '%s']);

        $whereThingFooRaw = $this->queryBuilderProvider('mock_')
            ->table('json')
            ->where(new Raw('JSON_EXTRACT(jsonCol,"$.thing")'), '=', 'foo')
            ->get();

        $whereThingFoo = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->whereJson('jsonCol', 'thing', 'foo') // Assume its '='
            ->get();

        $this->assertEquals($whereThingFooRaw[0]->string, $whereThingFoo[0]->string);
        $this->assertEquals($whereThingFooRaw[0]->jsonCol, $whereThingFoo[0]->jsonCol);

        // Check with prefix
        $whereThingFooPrefixed = $this->jsonQueryBuilderProvider('mock_')
            ->table('json')
            ->whereJson('json.jsonCol', 'thing', '<>', 'bar') // NOT BAR
            ->get();

        $this->assertEquals($whereThingFooPrefixed[0]->string, $whereThingFoo[0]->string);
        $this->assertEquals($whereThingFooPrefixed[0]->jsonCol, $whereThingFoo[0]->jsonCol);
    }

    /** @testdox It should be possible to create a WHERE clause that allows OR conditions, from traversing the JSON object. */
    public function testJsonWhereOr()
    {
        $this->wpdb->insert('mock_json', ['string' => 'z', 'jsonCol' => \json_encode((object)['id' => 24748, 'thing' => (object) ['handle' => 'foo'] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'y', 'jsonCol' => \json_encode((object)['id' => 78945, 'thing' => (object) ['handle' => 'bar'] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'x', 'jsonCol' => \json_encode((object)['id' => 78941, 'thing' => (object) ['handle' => 'baz'] ])], ['%s', '%s']);

        $rows = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->orWhereJson('jsonCol', ['thing','handle'], '=', 'foo')
            ->orWhereJson('jsonCol', ['thing','handle'], '=', 'bar')
            ->orderBy('string')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals('y', $rows[0]->string);
        $this->assertEquals('z', $rows[1]->string);
    }

    /** @testdox It should be possible to create a WHERE clause that allows NOT conditions, from traversing the JSON object. */
    public function testJsonWhereNot()
    {
        $this->wpdb->insert('mock_json', ['string' => 'Apple', 'jsonCol' => \json_encode((object)['id' => 24748, 'thing' => (object) ['handle' => 'foo'] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'Banana', 'jsonCol' => \json_encode((object)['id' => 78945, 'thing' => (object) ['handle' => 'bar'] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'Cherry', 'jsonCol' => \json_encode((object)['id' => 78941, 'thing' => (object) ['handle' => 'baz'] ])], ['%s', '%s']);

        $rows = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->WhereNotJson('jsonCol', ['thing','handle'], '=', 'foo')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals('Banana', $rows[0]->string);
        $this->assertEquals('Cherry', $rows[1]->string);
    }

    /** @testdox It should be possible to create a WHERE IN clause t, from traversing the JSON object. */
    public function testJsonWhereIn()
    {
        $this->wpdb->insert('mock_json', ['string' => 'Apple', 'jsonCol' => \json_encode((object)['id' => 24748, 'thing' => (object) ['handle' => 'foo'] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'Banana', 'jsonCol' => \json_encode((object)['id' => 78945, 'thing' => (object) ['handle' => 'bar'] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'Cherry', 'jsonCol' => \json_encode((object)['id' => 78941, 'thing' => (object) ['handle' => 'baz'] ])], ['%s', '%s']);

        $rows = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->whereInJson('jsonCol', ['thing','handle'], ['foo', 'bre', 'baz'])
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals('Apple', $rows[0]->string);  // Has Foo
        $this->assertEquals('Cherry', $rows[1]->string); // Has Baz
    }

    /** @testdox It should be possible to create a WHERE between clause  from traversing the JSON object. */
    public function testJsonWhereBetween()
    {
        $this->wpdb->insert('mock_json', ['string' => 'Apple', 'jsonCol' => \json_encode((object)['id' => 24748, 'thing' => (object) ['value' => 2] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'Banana', 'jsonCol' => \json_encode((object)['id' => 78945, 'thing' => (object) ['value' => 4] ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'Cherry', 'jsonCol' => \json_encode((object)['id' => 78941, 'thing' => (object) ['value' => 9] ])], ['%s', '%s']);

        $rows = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->whereBetweenJson('jsonCol', ['thing','value'], 3, 10)
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals('Banana', $rows[0]->string);
        $this->assertEquals('Cherry', $rows[1]->string);

        $rows = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->whereBetweenJson('jsonCol', ['thing','value'], 1, 3) // Apple
            ->orWhereBetweenJson('jsonCol', ['thing','value'], 8, 13) // Cherry
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals('Apple', $rows[0]->string);
        $this->assertEquals('Cherry', $rows[1]->string);
    }

    /** @testdox It should be possible to do the full range of whereDate conditions with JSON values. (whereDayJson(), whereMonthJson(), whereYearJson() & whereDateJson() */
    public function testJsonWhereDates()
    {
        $this->wpdb->insert('mock_json', ['string' => 'A', 'jsonCol' => \json_encode((object)['id' => 1, 'date' =>  '2020-10-10 18:19:03' ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'B', 'jsonCol' => \json_encode((object)['id' => 2, 'date' =>  '2000-10-12 18:19:03' ])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'C', 'jsonCol' => \json_encode((object)['id' => 3, 'date' =>  '2020-12-12 18:19:03' ])], ['%s', '%s']);

        $day = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->whereDayJson(new Raw('jsonCol'), 'date', 12)
            ->get();

        $this->assertCount(2, $day);
        $this->assertEquals('B', $day[0]->string);
        $this->assertEquals('C', $day[1]->string);

        $month = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->whereMonthJson('jsonCol', new Raw('date'), 10)
            ->get();

        $this->assertCount(2, $month);
        $this->assertEquals('A', $month[0]->string);
        $this->assertEquals('B', $month[1]->string);

        $year = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->whereYearJson('jsonCol', 'date', new Raw('2020'))
            ->get();

        $this->assertCount(2, $year);
        $this->assertEquals('A', $year[0]->string);
        $this->assertEquals('C', $year[1]->string);

        $year = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->whereDateJson('jsonCol', 'date', '2000-10-12')
            ->get();

        $this->assertCount(1, $year);
        $this->assertEquals('B', $year[0]->string);
    }

    /** @testdox It should be possible to sort results by a value held inside a JSON object. Either using arrow selectors or with a custom helper method. */
    public function testOrderByJson(): void
    {
        $this->wpdb->insert('mock_json', ['string' => 'A', 'jsonCol' => \json_encode((object)['col1' => 1, 'col2' => 'c'])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'B', 'jsonCol' => \json_encode((object)['col1' => 2, 'col2' => 'c'])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'C', 'jsonCol' => \json_encode((object)['col1' => 2, 'col2' => 'b'])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'D', 'jsonCol' => \json_encode((object)['col1' => 3, 'col2' => 'c'])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'E', 'jsonCol' => \json_encode((object)['col1' => 4, 'col2' => 'a'])], ['%s', '%s']);

        $byCol1As = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->orderByJson('jsonCol', 'col1', 'ASC')
            ->get();
        $this->assertEquals('A', $byCol1As[0]->string);
        $this->assertEquals('E', $byCol1As[4]->string);

        $byCol1Des = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->orderByJson('jsonCol', 'col1', 'DESC')
            ->get();
        $this->assertEquals('A', $byCol1Des[4]->string);
        $this->assertEquals('E', $byCol1Des[0]->string);

        $byCol2DesThenCol1As = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->orderByJson('jsonCol', 'col2', 'DESC')
            ->orderBy('jsonCol->col1', 'ASC')
            ->get();
        $this->assertEquals('A', $byCol2DesThenCol1As[0]->string); // "{"col1":1,"col2":"c"}"
        $this->assertEquals('B', $byCol2DesThenCol1As[1]->string); // "{"col1":2,"col2":"c"}"
        $this->assertEquals('D', $byCol2DesThenCol1As[2]->string); // "{"col1":3,"col2":"c"}"
        $this->assertEquals('C', $byCol2DesThenCol1As[3]->string); // "{"col1":2,"col2":"b"}"
        $this->assertEquals('E', $byCol2DesThenCol1As[4]->string); // "{"col1":4,"col2":"a"}"
    }

    /** @testdox [WIKI EXAMPLE] orderByJson https://github.com/gin0115/pixie-wpdb/wiki/Examples---JSON-Operations#order-by-json */
    public function testOrderByJsonWikiExample(): void
    {
        $this->wpdb->insert('mock_json', ['string' => 'A', 'jsonCol' => \json_encode(['stats' => ['likes' => 450, 'dislikes' => 5]])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'B', 'jsonCol' => \json_encode(['stats' => ['likes' => 45, 'dislikes' => 500]])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'C', 'jsonCol' => \json_encode(['stats' => ['likes' => 85463, 'dislikes' => 785]])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'D', 'jsonCol' => \json_encode(['stats' => ['likes' => 45, 'dislikes' => 14]])], ['%s', '%s']);

        $results = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->orderByJson('jsonCol', ['stats','likes'], 'DESC')
            ->orderByJson('jsonCol', ['stats','dislikes'], 'ASC')
            ->get();
        $this->assertEquals('C', $results[0]->string); // Words
        $this->assertEquals('A', $results[1]->string); // Some Tile
        $this->assertEquals('D', $results[2]->string); // Examples
        $this->assertEquals('B', $results[3]->string); // Foo Ba
    }


    /** @testdox It should be possible to do joins from and to JSON object values. [USING JSON HELPER METHOD] */
    public function testJoinOnJsonHelper(): void
    {
        $this->wpdb->insert('mock_json', ['string' => 'A', 'jsonCol' => \json_encode(['data' => ['category' => 'Cat A', 'number' => 1]])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'B', 'jsonCol' => \json_encode(['data' => ['category' => 'Cat B', 'number' => 2]])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'C', 'jsonCol' => \json_encode(['data' => ['category' => 'Cat C', 'number' => 3]])], ['%s', '%s']);
        $this->wpdb->insert('mock_json', ['string' => 'D', 'jsonCol' => \json_encode(['data' => ['category' => 'Cat D', 'number' => 4]])], ['%s', '%s']);
        $this->wpdb->insert('mock_foo', ['string' => 'Cat A', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Cat B', 'number' => 2], ['%s', '%d']);

        $joinJSONTo = $this->jsonQueryBuilderProvider()
            ->table('mock_foo')
            ->joinJson('mock_json', 'mock_foo.string', null, '=', 'mock_json.jsonCol', ['data','category'])
            ->get();

        // Easily get the defined cat from the JSON string.
        $getCategoryFromResult = function ($result) {
            $decoded = \json_decode($result);
            return $decoded->data->category;
        };

        $this->assertEquals("Cat A", $getCategoryFromResult($joinJSONTo[0]->jsonCol));
        $this->assertEquals('A', $joinJSONTo[0]->string);
        $this->assertEquals("Cat B", $getCategoryFromResult($joinJSONTo[1]->jsonCol));
        $this->assertEquals('B', $joinJSONTo[1]->string);

        $leftJoinJsonFrom = $this->jsonQueryBuilderProvider()
            ->table('mock_json')
            ->leftJoinJson('mock_foo', 'mock_json.jsonCol', ['data','number'], '=', 'mock_foo.number', null)
            ->get();

        $this->assertEquals("Cat A", $getCategoryFromResult($leftJoinJsonFrom[0]->jsonCol));
        $this->assertEquals('Cat A', $leftJoinJsonFrom[0]->string);
        $this->assertEquals("Cat B", $getCategoryFromResult($leftJoinJsonFrom[1]->jsonCol));
        $this->assertEquals('Cat B', $leftJoinJsonFrom[1]->string);
        $this->assertEquals("Cat C", $getCategoryFromResult($leftJoinJsonFrom[2]->jsonCol));
        $this->assertNull($leftJoinJsonFrom[2]->string);
        $this->assertEquals("Cat D", $getCategoryFromResult($leftJoinJsonFrom[3]->jsonCol));
        $this->assertNull($leftJoinJsonFrom[3]->string);
    }

    
}
