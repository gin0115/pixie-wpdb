<?php

declare(strict_types=1);

/**
 * Unit tests for teh Raw object
 *
 * @package PinkCrab\Test_Helpers
 * @author Glynn Quelch glynn@pinkcrab.co.uk
 * @since 0.0.1
 */

namespace Pixie\Tests\Unit;

use Pixie\QueryBuilder\Raw;

class TestRaw extends \WP_UnitTestCase
{
    /** @testdox It should be possible to create a raw object using a static instantiation with only a value, no bindings */
    public function testStaticVal(): void
    {
        $raw = Raw::val('foo');
        $this->assertInstanceOf(Raw::class, $raw);
    }

    /** @testdox It should be possible to use a raw object as  stringable object, which returns the value regardless of bindings. */
    public function testIsStringable(): void
    {
        $raw = new Raw('STRING', []);
        $this->assertEquals('STRING', (string) $raw);
    }

    /*** @testdox It should be possible to access both the value and bindings from a raw object. */
    public function testCanGetExpressionAndBindings(): void
    {
        $raw = new Raw('EXP', [1,2,3]);
        $this->assertEquals('EXP', $raw->getValue());
        $this->assertCount(3, $raw->getBindings());
        $this->assertContains(1, $raw->getBindings());
        $this->assertContains(2, $raw->getBindings());
        $this->assertContains(3, $raw->getBindings());
    }

    /** @testdox It should be possible to quickly check if a raw statement has bindings applied. */
    public function testHasBindings(): void
    {
        $without = new Raw('a');
        $with = new Raw('b', [1,2,3]);
        $this->assertFalse($without->hasBindings());
        $this->assertTrue($with->hasBindings());
    }
}
