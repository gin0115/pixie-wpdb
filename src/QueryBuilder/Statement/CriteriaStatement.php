<?php

declare(strict_types=1);

/**
 * Value object for a criteria statement: where, having and nested/join criteria (#56).
 *
 * Shape: ['key' => mixed, 'operator' => mixed, 'value' => mixed, 'joiner' => string].
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Statement;

class CriteriaStatement extends Statement
{
    /**
     * @param mixed  $key
     * @param mixed  $operator
     * @param mixed  $value
     * @param string $joiner
     */
    public function __construct($key, $operator, $value, string $joiner)
    {
        $this->data = [
            'key'      => $key,
            'operator' => $operator,
            'value'    => $value,
            'joiner'   => $joiner,
        ];
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->data['key'];
    }

    /**
     * @return mixed
     */
    public function getOperator()
    {
        return $this->data['operator'];
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->data['value'];
    }

    public function getJoiner(): string
    {
        return (string) $this->data['joiner'];
    }
}
