<?php

namespace Pixie\QueryBuilder;

class Raw
{
    /**
     * @var string
     */
    protected $value;

    /**
     * @var mixed[]
     */
    protected $bindings;

    /**
     * @param string|Raw $value
     * @param mixed|mixed[] $bindings
     */
    public function __construct($value, $bindings = [])
    {
        $this->value    = (string)$value;
        $this->bindings = (array)$bindings;
    }

    /**
     * Returns the current bindings
     *
     * @return mixed[]
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->value;
    }
}
