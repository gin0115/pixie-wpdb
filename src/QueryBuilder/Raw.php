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
     * @param string $value
     * @param mixed|mixed[] $bindings
     */
    public function __construct($value, $bindings = [])
    {
        $this->value    = (string)$value;
        $this->bindings = (array)$bindings;
    }

    /**
     * Create a Raw instance with no bindings
     *
     * @param string $value
     * @return self
     */
    public static function val(string $value): self
    {
        return new self($value, []);
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
     * Returns the current value held.
     *
     * @return string
     */
    public function getValue(): string
    {
        return (string) $this->value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->value;
    }
}
