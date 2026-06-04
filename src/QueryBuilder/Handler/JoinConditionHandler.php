<?php

declare(strict_types=1);

/**
 * Self-contained handler for building JOIN clauses (#31).
 *
 * Owns the rendering of the `joins` statements — including aliased join tables
 * (['table' => 'alias'] and the legacy ['table', 'alias']) and the ON criteria
 * — reusing the adapter's low-level primitives.
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Handler;

use Pixie\QueryBuilder\Raw;
use Pixie\QueryBuilder\WPDBAdapter;

class JoinConditionHandler
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
     * Builds the JOIN portion of a query from its statements.
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     */
    public function build(array $statements): string
    {
        $sql = '';

        if (!array_key_exists('joins', $statements) || !is_array($statements['joins'])) {
            return $sql;
        }

        foreach ($statements['joins'] as $joinArr) {
            $table       = $this->resolveTable($joinArr['table']);
            $joinBuilder = $joinArr['joinBuilder'];

            /**
             * @var string[]
             */
            $sqlArr = [
                $sql,
                strtoupper($joinArr['type']),
                'JOIN',
                $table,
                'ON',
                $joinBuilder->getQuery('criteriaOnly', false)->getSql(),
            ];

            $sql = $this->adapter->concatenateQuery($sqlArr);
        }

        return $sql;
    }

    /**
     * Resolves a join table (string, Raw, or aliased array) to SQL.
     *
     * @param mixed $table
     */
    protected function resolveTable($table): string
    {
        if (is_array($table)) {
            // Supports both ['table', 'alias'] (indexed) and the
            // ['table' => 'alias'] (associative) syntax used for selection.
            $joinKeys = array_keys($table);
            if (is_string($joinKeys[0])) {
                $realTable = $joinKeys[0];
                $alias     = $table[$realTable];
            } else {
                $realTable = $table[0];
                $alias     = $table[1];
            }

            $mainTable  = $this->adapter->stringifyValue($this->adapter->wrapSanitizer($realTable));
            $aliasTable = $this->adapter->stringifyValue($this->adapter->wrapSanitizer($alias));

            return $mainTable . ' AS ' . $aliasTable;
        }

        return $table instanceof Raw
            ? $this->adapter->parseRaw($table)
            : (string) $this->adapter->stringifyValue($this->adapter->wrapSanitizer($table));
    }
}
