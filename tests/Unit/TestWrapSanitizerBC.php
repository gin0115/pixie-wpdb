<?php

declare(strict_types=1);

/**
 * Backwards-compatibility tests for the public WPDBAdapter::wrapSanitizer()
 * surface after the sanitiser was relocated into the Sanitizer class (#48).
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Closure;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use Pixie\QueryBuilder\WPDBAdapter;

class TestWrapSanitizerBC extends WP_UnitTestCase
{
    private function getAdapter(): WPDBAdapter
    {
        return new WPDBAdapter(new Connection(new Logable_WPDB(), []));
    }

    /** @testdox [#48][BC] wrapSanitizer() is still public and handles plain fields. */
    public function testWrapSanitizerHandlesPlainField(): void
    {
        $adapter = $this->getAdapter();
        // Default adapter sanitizer is empty, so the value is returned untouched.
        $this->assertEquals('my_table.id', $adapter->wrapSanitizer('my_table.id'));
    }

    /** @testdox [#48][BC] wrapSanitizer() leaves a "*" untouched. */
    public function testWrapSanitizerKeepsStar(): void
    {
        $this->assertEquals('*', $this->getAdapter()->wrapSanitizer('*'));
    }

    /** @testdox [#48][BC] wrapSanitizer() parses a Raw value to a string. */
    public function testWrapSanitizerParsesRaw(): void
    {
        $result = $this->getAdapter()->wrapSanitizer(new Raw('COUNT(*)'));
        $this->assertEquals('COUNT(*)', $result);
    }

    /** @testdox [#48][BC] wrapSanitizer() returns a Closure unchanged. */
    public function testWrapSanitizerReturnsClosure(): void
    {
        $closure = function (): void {
        };
        $this->assertSame($closure, $this->getAdapter()->wrapSanitizer($closure));
    }
}
