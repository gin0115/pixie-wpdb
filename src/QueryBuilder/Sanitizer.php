<?php

declare(strict_types=1);

/**
 * Self-contained value sanitiser for table and field identifiers (#48).
 *
 * Relocated out of WPDBAdapter so the wrapping of identifiers with the
 * adapter's sanitizer character (e.g. backticks) is independent of the adapter
 * itself. WPDBAdapter::wrapSanitizer() remains as a backwards-compatible
 * delegating wrapper around this class.
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder;

use Closure;
use Pixie\QueryBuilder\Raw;

class Sanitizer
{
    /**
     * The character(s) used to wrap identifiers, e.g. a backtick.
     *
     * @var string
     */
    protected $sanitizer;

    /**
     * @param string $sanitizer the wrapping character(s); empty string for none
     */
    public function __construct(string $sanitizer = '')
    {
        $this->sanitizer = $sanitizer;
    }

    /**
     * Wraps a table/field identifier with the sanitizer character.
     *
     * A value joined with "." (e.g. my_table.id) is wrapped per-segment, and a
     * "*" segment is left untouched. Raw values are handed to the optional
     * $rawParser callback (so the adapter can stringify them), and Closures are
     * returned as-is for the caller to resolve.
     *
     * @param string|Raw|Closure $value
     * @param callable|null      $rawParser fn(Raw): string used to stringify Raw values
     *
     * @return string|Closure
     */
    public function wrap($value, ?callable $rawParser = null)
    {
        // Raw query: hand to the caller's parser, or cast (Raw has __toString()).
        if ($value instanceof Raw) {
            return null !== $rawParser ? $rawParser($value) : (string) $value;
        }

        if ($value instanceof Closure) {
            return $value;
        }

        // Separate table and field joined with a ".", like my_table.id
        $valueArr = explode('.', $value, 2);

        foreach ($valueArr as $key => $subValue) {
            // Don't wrap "*", which is not a usual field.
            $valueArr[$key] = '*' == trim($subValue)
                ? $subValue
                : $this->sanitizer . $subValue . $this->sanitizer;
        }

        // Join these back with "." and return.
        return implode('.', $valueArr);
    }

    /**
     * The configured sanitizer character(s).
     *
     * @return string
     */
    public function getSanitizer(): string
    {
        return $this->sanitizer;
    }
}
