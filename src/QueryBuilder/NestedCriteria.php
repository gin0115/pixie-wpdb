<?php

namespace Pixie\QueryBuilder;

class NestedCriteria extends QueryBuilderHandler
{
    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @param string $joiner
     *
     * @return $this
     */
    protected function whereHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key                            = $this->addTablePrefix($key);
        $this->statements['criteria'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }
}
