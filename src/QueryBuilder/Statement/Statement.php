<?php

declare(strict_types=1);

/**
 * Base value object for query statements (#56).
 *
 * Statements were previously plain associative arrays built with compact() and
 * read with array access throughout the adapter. They are now objects, but
 * implement ArrayAccess + IteratorAggregate so every existing array-style
 * consumer keeps working and getStatements() stays shape-compatible.
 *
 * @since  0.2.0
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\QueryBuilder\Statement;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use ReturnTypeWillChange;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
abstract class Statement implements ArrayAccess, IteratorAggregate
{
    /**
     * The statement's backing data.
     *
     * @var array<array-key, mixed>
     */
    protected $data = [];

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if (null === $offset) {
            $this->data[] = $value;

            return;
        }

        $this->data[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * @return ArrayIterator<array-key, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    /**
     * The statement as its original associative-array shape.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
