<?php

declare(strict_types=1);

/**
 * Self-contained handler for building the FROM table list (#31).
 *
 * Owns the rendering of the `tables` statement (including ['table' => 'alias']
 * aliasing) into a SQL fragment, reusing the adapter's low-level primitives.
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Handler;

use Pixie\QueryBuilder\WPDBAdapter;

class TableConditionHandler
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
     * Builds the comma-separated table list for a FROM clause.
     *
     * @param array<int|string, string> $tables
     */
    public function build(array $tables): string
    {
        return $this->adapter->arrayStr($tables, ', ');
    }
}
