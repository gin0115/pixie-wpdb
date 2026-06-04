<?php

declare(strict_types=1);

/**
 * Self-contained handler for building WHERE/HAVING criteria fragments (#31).
 *
 * Owns the type-prefixing and whitespace normalisation around the generic
 * criteria builder, reusing the adapter's buildCriteria() primitive.
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Handler;

use Pixie\QueryBuilder\WPDBAdapter;

class WhereConditionHandler
{
    /**
     * @var WPDBAdapter
     */
    protected $adapter;

    public function __construct(WPDBAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Builds a criteria fragment of a given type (e.g. WHERE / HAVING).
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param string                               $key        the statement key to read (wheres / havings)
     * @param string                               $type       the SQL keyword to prefix (WHERE / HAVING)
     * @param bool                                 $bindValues
     *
     * @return array{0:string, 1:string[]}
     */
    public function build(array $statements, string $key, string $type, bool $bindValues = true): array
    {
        $criteria = '';
        $bindings = [];

        if (isset($statements[$key])) {
            list($criteria, $bindings) = $this->adapter->buildCriteria($statements[$key], $bindValues);

            if ($criteria) {
                $criteria = $type . ' ' . $criteria;
            }
        }

        // Remove any multiple whitespace.
        $criteria = (string) preg_replace('!\s+!', ' ', $criteria);

        return [$criteria, $bindings];
    }
}
