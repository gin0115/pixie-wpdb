<?php

declare(strict_types=1);

/**
 * Integration test using WP PHPUnit MYSQL instance.
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use stdClass;
use Exception;
use Pixie\Binding;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlFoo);
        dbDelta($sqlBar);
        dbDelta($sqlJson);
        dbDelta($sqlDates);

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

    /** @testdox [WPDB] It should be possible to do various simple SELECT queries using WHERE conditions using a live instance of WPDB (WHERE, WHERE NOT, WHERE AND, WHERE IN, WHERE BETWEEN) */
    public function testWhere()
    {
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'b', 'number' => 3], ['%s', '%d']);

        // Test Where
        $allAs = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->get();

        $this->assertCount(2, $allAs);
        $this->assertEquals('a', $allAs[0]->string);
        $this->assertEquals('a', $allAs[1]->string);

        // Test Where And
        $and = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->where('number', 1)
            ->get();

        $this->assertCount(1, $and);
        $this->assertEquals('a', $and[0]->string);
        $this->assertEquals('1', $and[0]->number);

        // NotWhere
        $not = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->whereNot('string', 'a')
            ->get();

        $this->assertCount(1, $not);
        $this->assertEquals('b', $not[0]->string);
        $this->assertEquals('3', $not[0]->number);

        // In
        $in = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->whereIn('number', [1,3])
            ->get();

        $this->assertCount(2, $in);
        $this->assertEquals('a', $in[0]->string);
        $this->assertEquals('b', $in[1]->string);

        // Between
        $between = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->whereBetween('number', 2, 3)
            ->get();

        $this->assertCount(2, $between);
        $this->assertEquals('a', $between[0]->string);
        $this->assertEquals('2', $between[0]->number);
        $this->assertEquals('b', $between[1]->string);
        $this->assertEquals('3', $between[1]->number);
    }

    /** @testdox [WPDB] It should be possible to do various Aggregation (COUNT, MIN, MAX, SUM, AVERAGE) for results, using WPDB*/
    public function testAggregation(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'b', 'number' => 3], ['%s', '%d']);

        // Count results
        $count = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->count();

        $this->assertEquals(2, $count);

        // Get a sum of all numbers.
        $sum = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->sum('number');

        $this->assertEquals(3.0, $sum);

        // Get the avg
        $avg = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('string', 'a')
            ->average('number');

        $this->assertEquals(1.5, $avg);

        // Get the max
        $max = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->max('number');

        $this->assertEquals(3, $max);

        // Get the min
        $min = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->min('number');

        $this->assertEquals(1, $min);
    }

    /** @testdox [WPDB] It should be possible to use a custom alias for static calls, prefix for table names and have the results in an array not object. */
    public function testUsingTablePrefixWithAlias(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'a', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'b', 'number' => 3], ['%s', '%d']);

        $this->queryBuilderProvider('mock_', 'BA');

        $results = \BA::select(['number' => 'num']) // Only fetch number as num
            ->from('foo') // Should use the mock_ prefix
            ->setFetchMode(\ARRAY_A) // Return as an array
            ->get();

        // Expected [['num' => '1'],['num' => '2'],['num' => '3']]

        // Check we only have number as num and results are arrays.
        foreach ($results as $key => $result) {
            $this->assertIsArray($result);
            $this->assertCount(1, $result);
            $this->assertEquals(\strval($key + 1), $result['num']);
        }
    }

    /** @testdox [WPDB] It should be possible to join basic joins (INNER, LEFT & RIGHT) and have the query executed using WPDB. */
    public function testJoins(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'Primary', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Secondary', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Third', 'number' => 3], ['%s', '%d']);

        $this->wpdb->insert('mock_bar', ['string' => 'Apple', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Apple', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Banana', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Banana', 'number' => 3], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Strawberry', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_bar', ['string' => 'Raspberry', 'number' => 4], ['%s', '%d']);

        // Get all FRUITS (from mock_bar) with the TYPE (from mock_foo)
        $fruitsInner = $this->queryBuilderProvider('mock_')
            ->select(['bar.string' => 'name','foo.string' => 'type'])
            ->from('bar')
            ->join('foo', 'bar.number', '=', 'foo.number')
            ->setFetchMode(\ARRAY_A)
            ->get();

        // Expected 5
        $expected = [
            ['name' => 'Apple',  'type' => 'Primary'],
            ['name' => 'Apple',  'type' => 'Secondary'],
            ['name' => 'Banana',  'type' => 'Primary'],
            ['name' => 'Banana',  'type' => 'Third'],
            ['name' => 'Strawberry',  'type' => 'Primary'],
        ];

        $this->assertCount(5, $fruitsInner);
        $this->assertEmpty($this->arrayDifMD($expected, $fruitsInner));


        // Left Join
        $fruitsLeft = $this->queryBuilderProvider('mock_')
            ->select(['bar.string' => 'name','foo.string' => 'type'])
            ->from('bar')
            ->leftJoin('foo', 'bar.number', '=', 'foo.number')
            ->setFetchMode(\ARRAY_A)
            ->get();

        // Expected 6
        $expected = [
            ['name' => 'Apple',  'type' => 'Primary'],
            ['name' => 'Banana',  'type' => 'Primary'],
            ['name' => 'Strawberry',  'type' => 'Primary'],
            ['name' => 'Apple',  'type' => 'Secondary'],
            ['name' => 'Banana',  'type' => 'Third'],
            ['name' => 'Raspberry',  'type' => null],
        ];

        $this->assertCount(6, $fruitsLeft);
        $this->assertEmpty($this->arrayDifMD($expected, $fruitsLeft));

        // Right Join
        $fruitsRight = $this->queryBuilderProvider('mock_')
            ->select(['bar.string' => 'name','foo.string' => 'type'])
            ->from('bar')
            ->rightJoin('foo', 'bar.number', '=', 'foo.number')
            ->setFetchMode(\ARRAY_A)
            ->get();

        // Expected 5
        $expected = [
            ['name' => 'Apple',  'type' => 'Primary'],
            ['name' => 'Apple',  'type' => 'Secondary'],
            ['name' => 'Banana',  'type' => 'Primary'],
            ['name' => 'Banana',  'type' => 'Third'],
            ['name' => 'Strawberry',  'type' => 'Primary'],
        ];

        $this->assertCount(5, $fruitsRight);
        $this->assertEmpty($this->arrayDifMD($expected, $fruitsRight));
    }

    /** @testdox It should be possible to INSERT a single or multiple tables and return the primary key. It should then possible to get the same values back using find() */
    public function testInsertAndFind(): void
    {
        $this->queryBuilderProvider('mock_', 'BB');

        // Insert Single
        $new =  \BB::table('foo')
            ->insert([
                'string' => "testInsertAndFind",
                'number' => 12
            ]);

        $result = \BB::table('foo')->find($new);
        $this->assertEquals($new, $result->id);
        $this->assertEquals("testInsertAndFind", $result->string);
        $this->assertEquals('12', $result->number);

        // Insert Multiple
        $new =  \BB::table('foo')
            ->insert([
                ['string' => "a", 'number' => 1],
                ['string' => "b", 'number' => 2],
            ]);

        // a
        $a = \BB::table('foo')->find($new[0]);
        $this->assertEquals($new[0], $a->id);
        $this->assertEquals("a", $a->string);
        $this->assertEquals('1', $a->number);

        // b
        $b = \BB::table('foo')->find($new[1]);
        $this->assertEquals($new[1], $b->id);
        $this->assertEquals("b", $b->string);
        $this->assertEquals('2', $b->number);
    }

    /** @testdox [WPDB] It should be possible to update a row using a where clause and have it indicated with boolean value if the update was successful (false if not updated) */
    public function testUpdate()
    {
        $this->wpdb->insert('mock_foo', ['string' => 'Primary', 'number' => 1], ['%s', '%d']);

        $updated = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('number', 1)
            ->update(['string' => 'updated']);
        // As this has been updated, we should get a 1 to denote 1 row updated.
        $this->assertEquals(1, $updated);

        // Check the value was updated.
        $this->assertEquals(
            'updated',
            $this->queryBuilderProvider()->table('mock_foo')->find(1, 'number')->string
        );

        // Update with the same value, should return 0 as not changed.
        $updated = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->where('number', 1)
            ->update(['string' => 'updated']);
        $this->assertEquals(0, $updated);
    }

    /** @testdox [WPDB] It should be possible to use updateOrInsert/upsert to either add a unique dataset or update an existing one. */
    public function testUpsert()
    {
        $builder = $this->queryBuilderProvider();

        // INSERT
        $id = $builder->table('mock_foo')->updateOrInsert(['string' => 'first', 'number' => 24]);

        // Check we actually inserted the row.
        $this->assertTrue($id >= 1);
        $this->assertEquals('first', $builder->table('mock_foo')->find(24, 'number')->string);

        // UPDATE
        $updated = $builder->table('mock_foo')->updateOrInsert(['string' => 'second', 'number' => 24]);

        // Check we updated.
        $this->assertEquals(1, $updated);
        $this->assertEquals('second', $builder->table('mock_foo')->find(24, 'number')->string);
    }

    /** @testdox [WPDB] It should be possible to create a query which deletes all rows based on the criteria */
    public function testDeleteWhere(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'First', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Second', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Third', 'number' => 3], ['%s', '%d']);

        $builder = $this->queryBuilderProvider();

        // Remove all with a NUMBER of 2 or more.
        $r = $builder->table('mock_foo')->where('number', '>=', 2)->delete();

        // Check we only have the first value.
        $rows = $builder->table('mock_foo')->get();
        $this->assertCount(1, $rows);
        $this->assertEquals('First', $rows[0]->string);
    }

    /** @testdox [WPDB] It should be possible to remove all rows from a table */
    public function testDeleteAll(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'First', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Second', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Third', 'number' => 3], ['%s', '%d']);

        $builder = $this->queryBuilderProvider();

        // Remove all with a NUMBER of 2 or more.
        $builder->table('mock_foo')->delete();

        // Check we only have the first value.
        $rows = $builder->table('mock_foo')->get();

        $this->assertEmpty($rows);
    }

    /** @testdox [WPDB] It should be possible to create a query and have the results returned in a populated objects. (MANY) */
    public function testHydrationWithModelMany(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'First', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Second', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Third', 'number' => 1], ['%s', '%d']);

        $rows = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->setFetchMode(ModelWithMagicSetter::class)
            ->get();

        $this->assertInstanceOf(ModelWithMagicSetter::class, $rows[0]);
        $this->assertEquals('First', $rows[0]->string);
        $this->assertEquals('1', $rows[0]->number);

        $this->assertInstanceOf(ModelWithMagicSetter::class, $rows[1]);
        $this->assertEquals('Second', $rows[1]->string);
        $this->assertEquals('2', $rows[1]->number);

        $this->assertInstanceOf(ModelWithMagicSetter::class, $rows[2]);
        $this->assertEquals('Third', $rows[2]->string);
        $this->assertEquals('1', $rows[2]->number);
    }

    /** @testdox [WPDB] It should be possible to create a query and have the result returned in a populated objects. (SINGLE) */
    public function testHydrationWithModelSingle()
    {
        $this->wpdb->insert('mock_foo', ['string' => 'First', 'number' => 1], ['%s', '%d']);

        $row = $this->queryBuilderProvider()
            ->table('mock_foo')
            ->setFetchMode(ModelWithMagicSetter::class)
            ->first();

        $this->assertInstanceOf(ModelWithMagicSetter::class, $row);
        $this->assertEquals('First', $row->string);
        $this->assertEquals('1', $row->number);
    }

    /** @testdox It should be possible to map the results to either of the default WP return types [OBJECT, OBJECT_K, ARRAY_N, ARRAY_K] and custom models (with constructor args) using the Hydrator */
    public function testGetReturnTypes(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'First', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Second', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Third', 'number' => 1], ['%s', '%d']);

        $selectAllFromMockFoo = $this->queryBuilderProvider()->table('mock_foo');

        // Objects as a list
        $objectList = $selectAllFromMockFoo->setFetchMode(\OBJECT)->get();
        $this->assertArrayHasKey(0, $objectList);
        $this->assertArrayHasKey(2, $objectList);
        $this->assertInstanceOf(stdClass::class, $objectList[0]);

        // Objects as a map with row ID as keys.
        $objectMap = $selectAllFromMockFoo->setFetchMode(\OBJECT_K)->get();
        foreach ($objectMap as $key => $row) {
            $this->assertInstanceOf(stdClass::class, $row);
            $this->assertEquals($key, $row->id);
        }

        // Arrays without row keys, just numerical [0=>id,1=>string,2=>number]
        $arrayList = $selectAllFromMockFoo->setFetchMode(\ARRAY_N)->get();
        $this->assertArrayHasKey(0, $arrayList);
        $this->assertArrayHasKey(2, $arrayList);
        $this->assertIsArray($arrayList[0]);
        $this->assertEquals("First", $arrayList[0][1]);
        $this->assertEquals("1", $arrayList[0][2]);
        $this->assertEquals("Second", $arrayList[1][1]);
        $this->assertEquals("2", $arrayList[1][2]);
        $this->assertEquals("Third", $arrayList[2][1]);
        $this->assertEquals("1", $arrayList[2][2]);

        // Arrays with row keys.
        $arrayMap = $selectAllFromMockFoo->setFetchMode(\ARRAY_A)->get();
        $this->assertIsArray($arrayMap[0]);
        $this->assertEquals("First", $arrayMap[0]['string']);
        $this->assertEquals("1", $arrayMap[0]['number']);
        $this->assertEquals("Second", $arrayMap[1]['string']);
        $this->assertEquals("2", $arrayMap[1]['number']);
        $this->assertEquals("Third", $arrayMap[2]['string']);
        $this->assertEquals("1", $arrayMap[2]['number']);

        // Custom Models with constructor properties.
        $modelWithConstructor = $selectAllFromMockFoo->setFetchMode(ModelForMockFoo::class, ['defined'])->get();
        foreach ($modelWithConstructor as $key => $model) {
            $this->assertInstanceOf(ModelForMockFoo::class, $model);
            $this->assertContains($model->string, ['First!!', 'Second!!', 'Third!!']); // Adds !! to the end of the string with setter method set_string()
            $this->assertContains($model->number, [1,2]); // Casts value to int using setNumber() method
            $this->assertNotEquals(-1, $model->rowId); // Sets from `id` row using magic __set(), defaults to -1 if not set using __set()
            $this->assertEquals('defined', $model->constructorProp); // Is set from constructor args passed, is DEFAULT if not defined.
        }
    }

    /** @testdox It should be possible to do a find or fail query. An excetpion should be thrown if no result is found. */
    public function testFindOrFail(): void
    {
        $this->wpdb->insert('mock_foo', ['string' => 'First', 'number' => 1], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Second', 'number' => 2], ['%s', '%d']);
        $this->wpdb->insert('mock_foo', ['string' => 'Third', 'number' => 1], ['%s', '%d']);

        $builder = $this->queryBuilderProvider()->table('mock_foo');

        $row = $builder->findOrFail('First', 'string');
        $this->assertEquals(1, $row->number);

        $this->expectExceptionMessage('Failed to find string=Forth');
        $this->expectException(Exception::class);

        $row = $builder->findOrFail('Forth', 'string');
    }

    /** @testdox It should be possible to query a date column by month */
    public function testWhereMonth(): void
    {
        $this->wpdb->insert('mock_dates', ['date' => '2020-10-10', 'unix' => '2020-10-10 18:19:03', 'datetime' => '2020-10-10 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2002-10-05', 'unix' => '2002-10-10 18:19:03', 'datetime' => '2002-10-10 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2002-3-3', 'unix' => '2002-3-3 18:19:03', 'datetime' => '2002-3-3 18:19:03'], ['%s', '%s']);

        $month3 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereMonth('unix', Binding::asString(3))
            ->get();

        $this->assertCount(1, $month3);
        $this->assertEquals('2002-03-03', $month3[0]->date);

        $month10 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereMonth('date', '>', 9)
            ->get();

        $this->assertCount(2, $month10);
        $this->assertEquals('2020-10-10', $month10[0]->date);
        $this->assertEquals('2002-10-05', $month10[1]->date);
    }

    /** @testdox It should be possible to query a date column by day */
    public function testWhereDay(): void
    {
        $this->wpdb->insert('mock_dates', ['date' => '2020-10-10', 'unix' => '2020-10-10 18:19:03', 'datetime' => '2020-10-10 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2010-10-05', 'unix' => '2010-10-05 18:19:03', 'datetime' => '2010-10-05 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2002-03-12', 'unix' => '2002-03-12 18:19:03', 'datetime' => '2002-03-12 18:19:03'], ['%s', '%s']);

        $day5 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereDay('unix', Binding::asString(5))
            ->get();
        $this->assertCount(1, $day5);
        $this->assertEquals('2010-10-05', $day5[0]->date);

        $dayAbove9 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereDay('date', '>', 9)
            ->get();

        $this->assertCount(2, $dayAbove9);
        $this->assertEquals('2020-10-10', $dayAbove9[0]->date);
        $this->assertEquals('2002-03-12', $dayAbove9[1]->date);

        $day10 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereDay('datetime', Binding::asString(10))
            ->get();
        $this->assertCount(1, $day10);
        $this->assertEquals('2020-10-10', $day10[0]->date);
    }

    /** @testdox It should be possible to query a date column by year */
    public function testWhereYear(): void
    {
        $this->wpdb->insert('mock_dates', ['date' => '2022-10-10', 'unix' => '2022-10-10 18:19:03', 'datetime' => '2022-10-10 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2010-10-05', 'unix' => '2010-10-05 18:19:03', 'datetime' => '2010-10-05 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2020-03-12', 'unix' => '2020-03-12 18:19:03', 'datetime' => '2020-03-12 18:19:03'], ['%s', '%s']);

        $only2010 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereYear('unix', Binding::asString(2010))
            ->get();
        $this->assertCount(1, $only2010);
        $this->assertEquals('2010-10-05', $only2010[0]->date);

        $after2009 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereYear('date', '>', new Raw('%d', [2019]))
            ->get();

        $this->assertCount(2, $after2009);
        $this->assertEquals('2022-10-10', $after2009[0]->date);
        $this->assertEquals('2020-03-12', $after2009[1]->date);

        $only2022 = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereYear('datetime', Binding::asFloat(2022))
            ->get();
        $this->assertCount(1, $only2022);
        $this->assertEquals('2022-10-10', $only2022[0]->date);
    }

    /** @testdox It should be possible to query a date column by date */
    public function testWhereDate(): void
    {
        $this->wpdb->insert('mock_dates', ['date' => '2022-10-10', 'unix' => '2022-10-10 18:19:03', 'datetime' => '2022-10-10 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2010-10-05', 'unix' => '2010-10-05 18:19:03', 'datetime' => '2010-10-05 18:19:03'], ['%s', '%s']);
        $this->wpdb->insert('mock_dates', ['date' => '2020-03-12', 'unix' => '2020-03-12 18:19:03', 'datetime' => '2020-03-12 18:19:03'], ['%s', '%s']);

        $resultA = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereDate('unix', Binding::asString('2010-10-05'))
            ->get();
        $this->assertCount(1, $resultA);
        $this->assertEquals('2010-10-05', $resultA[0]->date);

        $resultB = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereDate('date', '!=', new Raw('%s', ['2020-03-12']))
            ->get();

        $this->assertCount(2, $resultB);
        $this->assertEquals('2022-10-10', $resultB[0]->date);
        $this->assertEquals('2010-10-05', $resultB[1]->date);

        $resultC = $this->queryBuilderProvider('mock_')
            ->table('dates')
            ->whereDate('datetime', date("Y-m-d", 1665425943)) // strtotime('2022-10-10 18:19:03')
            ->get();
        $this->assertCount(1, $resultC);
        $this->assertEquals('2022-10-10', $resultC[0]->date);
    }



    /**************************************/
    /*         JSON FUNCTIONALITY         */
    /**************************************/

    /** @testdox It should be possible to select values from inside a JSON object, held in a JSON column type. */
    public function testCanSelectFromWithinJSONColumn1GenDeep()
    {
         $this->wpdb->insert('mock_json', ['string' => 'a', 'jsonCol' => \json_encode((object)['id' => 24748, 'name' => 'Sam'])], ['%s', '%s']);

        $asRaw = $this->queryBuilderProvider('mock_')
            ->table('json')
            ->select('string', new Raw('JSON_UNQUOTE(JSON_EXTRACT(jsonCol, "$.id")) as jsonID'))
            ->get();

        $asSelectJson = $this->queryBuilderProvider('mock_')
            ->table('json')
            ->select('string')
            ->selectJson('jsonCol', "id", 'jsonID')
            ->get();

        $this->assertEquals($asRaw[0]->string, $asSelectJson[0]->string);
        $this->assertEquals($asRaw[0]->jsonID, $asSelectJson[0]->jsonID);

        // Without Alias
        $jsonWoutAlias = $this->queryBuilderProvider('mock_')
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
        $objectVal = $this->queryBuilderProvider('mock_')
            ->table('json')
            ->select('string')
            ->selectJson('jsonCol', ['data', 'object', 'obj1'], 'jsonVALUE')
            ->first();

        $this->assertNotNull($objectVal);
        $this->assertEquals('a', $objectVal->string);
        $this->assertEquals('val1', $objectVal->jsonVALUE);

        // Extract an entire array.
        $arrayValues = $this->queryBuilderProvider('mock_')
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
        $pluckArrayValue = $this->queryBuilderProvider('mock_')
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

        $whereThingFoo = $this->queryBuilderProvider('mock_')
            ->table('json')
            ->whereJson('jsonCol', 'thing', 'foo') // Assume its '='
            ->get();

        $this->assertEquals($whereThingFooRaw[0]->string, $whereThingFoo[0]->string);
        $this->assertEquals($whereThingFooRaw[0]->jsonCol, $whereThingFoo[0]->jsonCol);

        // Check with prefix
        $whereThingFooPrefixed = $this->queryBuilderProvider('mock_')
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

        $rows = $this->queryBuilderProvider()
            ->table('mock_json')
            ->orWhereJson('jsonCol', ['thing','handle'], '=', 'foo')
            ->orWhereJson('jsonCol', ['thing','handle'], '=', 'bar')
            ->orderBy('string')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEquals('y', $rows[0]->string);
        $this->assertEquals('z', $rows[1]->string);
    }
}
