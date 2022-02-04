<?php

declare(strict_types=1);

/**
 * Unit tests for the binding object
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Pixie\Binding;
use Pixie\Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\QueryBuilderHandler;

class TestBinding extends WP_UnitTestCase
{

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

    /** @testdox It should be possible to create a bindings using the Value and its Type. */
    public function testCanCreateValidBinding()
    {
        $cases = [
            'valueString' => Binding::STRING,
            'valueBool' => Binding::BOOL,
            'valueInt' => Binding::INT,
            'valueFloat' => Binding::FLOAT,
            'valueRaw' => Binding::RAW,
            'valueJSON' => Binding::JSON,
        ];

        foreach ($cases as $value => $type) {
            $binding = new Binding($value, $type);
            $this->assertEquals($type, $binding->getType());

            // Raw should return an instance of Raw with the value callable using __toString()
            if (Binding::RAW === $type) {
                $this->assertInstanceOf(Raw::class, $binding->getValue());
                $this->assertEquals($value, (string)$binding->getValue());
            } else {
                $this->assertEquals($value, $binding->getValue());
            }
        }
    }

    /** @testdox It should be possible to check if the binding has a value which can be bound. */
    public function testHasTypeDefined(): void
    {
        $cases = [
            'asString' => true,
            'asFloat' => true,
            'asInt' => true,
            'asBool' => true,
            'asJSON' => true,
            'asRAW' => false,
        ];

        foreach ($cases as $method => $value) {
            $binding = Binding::{$method}('Value');
            $this->assertEquals($value, $binding->hasTypeDefined(), "{$method} failed");
        }
    }

    /** @testdox It should be possible to create a binding without passing a type or using null and having this treated as a RAW query. */
    public function testAllowNoTypeToBePassed()
    {
        $noType = new Binding('noType');
        $this->assertInstanceOf(Raw::class, $noType->getValue());

        $nullType = new Binding('asNull', null);
        $this->assertInstanceOf(Raw::class, $nullType->getValue());
    }

    /** @testdox When attempting to create a binding using a none supported type, an exception should be thrown. */
    public function testCanNotCreateBindingWithInvalidType(): void
    {
        $this->expectException(Exception::class);
        new Binding('val', 'invalid');
    }

    /** @testdox It should be possible to use a static method as short syntax for defining a String based binding. */
    public function testAsString(): void
    {
        $binding = Binding::asString('String');
        $this->assertEquals('String', $binding->getValue());
        $this->assertEquals(Binding::STRING, $binding->getType());
    }

    /** @testdox It should be possible to use a static method as short syntax for defining a Float based binding. */
    public function testAsFloat(): void
    {
        $binding = Binding::asFloat('Float');
        $this->assertEquals('Float', $binding->getValue());
        $this->assertEquals(Binding::FLOAT, $binding->getType());
    }

    /** @testdox It should be possible to use a static method as short syntax for defining a Int based binding. */
    public function testAsInt(): void
    {
        $binding = Binding::asInt('Int');
        $this->assertEquals('Int', $binding->getValue());
        $this->assertEquals(Binding::INT, $binding->getType());
    }

    /** @testdox It should be possible to use a static method as short syntax for defining a Bool based binding. */
    public function testAsBool(): void
    {
        $binding = Binding::asBool('Bool');
        $this->assertEquals('Bool', $binding->getValue());
        $this->assertEquals(Binding::BOOL, $binding->getType());
    }

    /** @testdox It should be possible to use a static method as short syntax for defining a JSON based binding. */
    public function testAsJSON(): void
    {
        $binding = Binding::asJSON('JSON');
        $this->assertEquals('JSON', $binding->getValue());
        $this->assertEquals(Binding::JSON, $binding->getType());
    }

    /** @testdox It should be possible to use a static method as short syntax for defining a Raw based binding. */
    public function testAsRaw(): void
    {
        $binding = Binding::asRAW('Raw');
        $this->assertEquals('Raw', $binding->getValue());
        $this->assertEquals(Binding::RAW, $binding->getType());
    }

        /** USING BINDING OBJECT */

    /** @testdox It should be possible to define both the value and its expected type, when creating a query using a Binding object. */
    public function testUsingBindingOnWhere(): void
    {
        $this->queryBuilderProvider()
            ->table('foo')
            ->where('raw', '=', Binding::asRaw("'value'"))
            ->where('string', '=', Binding::asString('value'))
            ->where('int', '=', Binding::asInt(7))
            ->where('bool', '=', Binding::asBool(7 === 8))
            ->where('float', '=', Binding::asFloat(3.14))
            ->where('json', '>', '["something"]')
            ->get();

            $queryWithPlaceholders = $this->wpdb->usage_log['prepare'][0]['query'];

            $this->assertStringContainsString("raw = 'value'", $queryWithPlaceholders);
            $this->assertStringContainsString("string = %s", $queryWithPlaceholders);
            $this->assertStringContainsString("int = %d", $queryWithPlaceholders);
            $this->assertStringContainsString("bool = %d", $queryWithPlaceholders);
            $this->assertStringContainsString("float = %f", $queryWithPlaceholders);
            $this->assertStringContainsString("json > %s", $queryWithPlaceholders);
    }

    /** @testdox It should be possible to create an update query with the use of binding objects for the value to define the format, regardless of value type. */
    public function testUsingBindingOnUpdate(): void
    {
        $this->queryBuilderProvider()
            ->table('foo')
            ->update([
                'string' => Binding::asString('some string value'),
                'int' => Binding::asInt('7'),
                'float' => Binding::asFloat((1 / 3)),
                'bool' => Binding::asBool('1'),
                'raw' => Binding::asRaw("'WILD STRING'"),
            ]);

            $queryWithPlaceholders = $this->wpdb->usage_log['prepare'][0]['query'];
            $this->assertCount(4, $this->wpdb->usage_log['prepare'][0]['args']);
            $this->assertStringContainsString("raw='WILD STRING'", $queryWithPlaceholders);
            $this->assertStringContainsString("string=%s", $queryWithPlaceholders);
            $this->assertStringContainsString("int=%d", $queryWithPlaceholders);
            $this->assertStringContainsString("float=%f", $queryWithPlaceholders);
            $this->assertStringContainsString("bool=%d", $queryWithPlaceholders);
    }

    /** @testdox It should be possible to create an insert query with the use of binding objects for the value to define the format, regardless of value type. */
    public function testUsingBindingOnInsert(): void
    {
        $this->queryBuilderProvider()
            ->table('foo')
            ->insert([
                'string' => Binding::asString('some string value'),
                'int' => Binding::asInt('7'),
                'float' => Binding::asFloat((1 / 3)),
                'bool' => Binding::asBool('1'),
                'raw' => Binding::asRaw("'WILD STRING'"),
                'rawNative' => new Raw('[%d]', 5),
            ]);

            $queryWithPlaceholders = $this->wpdb->usage_log['prepare'][1]['query'];
            $this->assertEquals("INSERT INTO foo (string,int,float,bool,raw,rawNative) VALUES (%s,%d,%f,%d,'WILD STRING',[5])", $queryWithPlaceholders);
    }

    /** @testdox It should be possible to use binding values on a BETWEEN query */
    public function testUsingBindingsOnBetweenCondition(): void
    {
        $this->queryBuilderProvider()
            ->table('foo')
            ->whereBetween('bar', Binding::asInt('7'), Binding::asFloat((1 / 3)))
            ->get();

        $queryWithPlaceholders = $this->wpdb->usage_log['prepare'][0]['query'];
        $this->assertEquals("SELECT * FROM foo WHERE bar BETWEEN %d AND %f", $queryWithPlaceholders);
    }

}
