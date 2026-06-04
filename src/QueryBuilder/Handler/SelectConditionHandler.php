<?php

declare(strict_types=1);

/**
 * Self-contained handler for building the SELECT field list (#31).
 *
 * Owns the rendering of the `selects` statement (including ['field' => 'alias']
 * aliasing) into a SQL fragment, reusing the adapter's low-level primitives.
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Handler;

use Pixie\QueryBuilder\WPDBAdapter;

class SelectConditionHandler
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
     * Builds the comma-separated field list for a SELECT clause.
     *
     * @param array<int|string, string> $selects
     */
    public function build(array $selects): string
    {
        return $this->adapter->arrayStr($selects, ', ');
    }
}
