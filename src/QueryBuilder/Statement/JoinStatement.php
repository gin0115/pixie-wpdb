<?php

declare(strict_types=1);

/**
 * Value object for a JOIN statement (#56).
 *
 * Shape: ['type' => string, 'table' => mixed, 'joinBuilder' => mixed].
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Statement;

class JoinStatement extends Statement
{
    /**
     * @param string $type
     * @param mixed  $table
     * @param mixed  $joinBuilder
     */
    public function __construct(string $type, $table, $joinBuilder)
    {
        $this->data = [
            'type'        => $type,
            'table'       => $table,
            'joinBuilder' => $joinBuilder,
        ];
    }

    public function getType(): string
    {
        return (string) $this->data['type'];
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->data['table'];
    }

    /**
     * @return mixed
     */
    public function getJoinBuilder()
    {
        return $this->data['joinBuilder'];
    }
}
