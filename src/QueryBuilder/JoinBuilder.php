<?php namespace Pixie\QueryBuilder;

class JoinBuilder extends QueryBuilderHandler
{
    /**
     * @param string|Raw $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    public function on($key, ?string $operator, $value): self
    {
        return $this->joinHandler($key, $operator, $value, 'AND');
    }

    /**
     * @param string|Raw $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    public function orOn($key, ?string $operator, $value): self
    {
        return $this->joinHandler($key, $operator, $value, 'OR');
    }

    /**
     * @param string|Raw $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    protected function joinHandler($key, ?string $operator = null, $value = null, string $joiner = 'AND'): self
    {
        $key = $this->addTablePrefix($key);
        $value = $this->addTablePrefix($value);
        $this->statements['criteria'][] = compact('key', 'operator', 'value', 'joiner');
        return $this;
    }
}
