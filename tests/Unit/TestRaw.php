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
}
