<?php

declare(strict_types=1);

/**
 * Unit tests for the self-contained Sanitizer relocated out of WPDBAdapter (#48).
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Closure;
use WP_UnitTestCase;
use Pixie\QueryBuilder\Raw;
use Pixie\QueryBuilder\Sanitizer;

class TestSanitizer extends WP_UnitTestCase
{
    /** @testdox [#48] With no sanitizer character a plain field is returned untouched. */
    public function testPlainFieldWithoutSanitizer(): void
    {
        $sanitizer = new Sanitizer('');
        $this->assertEquals('id', $sanitizer->wrap('id'));
    }

    /** @testdox [#48] A field is wrapped with the configured sanitizer character. */
    public function testWrapsFieldWithSanitizer(): void
    {
        $sanitizer = new Sanitizer('`');
        $this->assertEquals('`id`', $sanitizer->wrap('id'));
    }

    /** @testdox [#48] A table.field value is wrapped per segment. */
    public function testWrapsEachDotSegment(): void
    {
        $sanitizer = new Sanitizer('`');
        $this->assertEquals('`my_table`.`id`', $sanitizer->wrap('my_table.id'));
    }

    /** @testdox [#48] A "*" segment is never wrapped. */
    public function testStarIsNotWrapped(): void
    {
        $sanitizer = new Sanitizer('`');
        $this->assertEquals('*', $sanitizer->wrap('*'));
        $this->assertEquals('`my_table`.*', $sanitizer->wrap('my_table.*'));
    }

    /** @testdox [#48] A Closure value is returned unchanged for later resolution. */
    public function testClosureReturnedAsIs(): void
    {
        $sanitizer = new Sanitizer('`');
        $closure   = function (): void {
        };
        $this->assertSame($closure, $sanitizer->wrap($closure));
    }

    /** @testdox [#48] A Raw value is stringified through the provided raw parser. */
    public function testRawValueUsesProvidedParser(): void
    {
        $sanitizer = new Sanitizer('`');
        $raw       = new Raw('NOW()');

        $result = $sanitizer->wrap($raw, function (Raw $r): string {
            return 'PARSED:' . (string) $r;
        });

        $this->assertEquals('PARSED:NOW()', $result);
    }

    /** @testdox [#48] A Raw value falls back to its __toString when no parser is given. */
    public function testRawValueFallsBackToToString(): void
    {
        $sanitizer = new Sanitizer('`');
        $raw       = new Raw('NOW()');

        $this->assertEquals('NOW()', $sanitizer->wrap($raw));
    }

    /** @testdox [#48] The configured sanitizer character is exposed via getSanitizer(). */
    public function testGetSanitizer(): void
    {
        $this->assertEquals('`', (new Sanitizer('`'))->getSanitizer());
        $this->assertEquals('', (new Sanitizer())->getSanitizer());
    }
}
