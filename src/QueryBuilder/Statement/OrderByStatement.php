<?php

declare(strict_types=1);

/**
 * Value object for an ORDER BY statement (#56).
 *
 * Shape: ['field' => string|Raw, 'type' => string].
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Statement;

class OrderByStatement extends Statement
{
    /**
     * @param mixed  $field
     * @param string $type
     */
    public function __construct($field, string $type)
    {
        $this->data = [
            'field' => $field,
            'type'  => $type,
        ];
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->data['field'];
    }

    public function getType(): string
    {
        return (string) $this->data['type'];
    }
}
