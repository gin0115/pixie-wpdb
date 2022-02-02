<?php

namespace Pixie\QueryBuilder;

class JsonQueryBuilder extends QueryBuilderHandler
{
    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($column, $nodes, $operator, $value, 'AND');
    }

    /**
     * @param string|Raw $column The database column which holds the JSON value
     * @param string|Raw|string[] $nodes The json key/index to search
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return static
     */
    public function whereNotJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($column, $nodes, $operator, $value, 'AND NOT');
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function orWhereJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($column, $nodes, $operator, $value, 'OR');
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function orWhereNotJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($column, $nodes, $operator, $value, 'OR NOT');
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function whereInJson($column, $nodes, $values): self
    {
        return $this->whereJsonHandler($column, $nodes, 'IN', $values, 'AND');
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function whereNotInJson($column, $nodes, $values): self
    {
        return $this->whereJsonHandler($column, $nodes, 'NOT IN', $values, 'AND');
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function orWhereInJson($column, $nodes, $values): self
    {
        return $this->whereJsonHandler($column, $nodes, 'IN', $values, 'OR');
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function orWhereNotInJson($column, $nodes, $values): self
    {
        return $this->whereJsonHandler($column, $nodes, 'NOT IN', $values, 'OR');
    }

    /**
     * @param string|Raw $column
    * @param string|Raw|string[] $nodes The json key/index to search
     * @param mixed $valueFrom
     * @param mixed $valueTo
     *
     * @return static
     */
    public function whereBetweenJson($column, $nodes, $valueFrom, $valueTo): self
    {
        return $this->whereJsonHandler($column, $nodes, 'BETWEEN', [$valueFrom, $valueTo], 'AND');
    }

    /**
     * @param string|Raw $column
    * @param string|Raw|string[] $nodes The json key/index to search
     * @param mixed $valueFrom
     * @param mixed $valueTo
     *
     * @return static
     */
    public function orWhereBetweenJson($column, $nodes, $valueFrom, $valueTo): self
    {
        return $this->whereJsonHandler($column, $nodes, 'BETWEEN', [$valueFrom, $valueTo], 'OR');
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereDayJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($column, $nodes, 'DAY', $operator, $value);
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereMonthJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($column, $nodes, 'MONTH', $operator, $value);
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereYearJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($column, $nodes, 'YEAR', $operator, $value);
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereDateJson($column, $nodes, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($column, $nodes, 'DATE', $operator, $value);
    }

    /**
     * Maps a function call for a JSON where condition
     *
     * @param string|Raw $column
     * @param string|Raw|string[] $nodes
     * @param string $function
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return static
     */
    protected function whereFunctionCallJsonHandler($column, $nodes, $function, $operator, $value): self
    {
        // Handle potential raw values.
        if ($column instanceof Raw) {
            $column = $this->adapterInstance->parseRaw($column);
        }
        if ($nodes instanceof Raw) {
            $nodes = $this->adapterInstance->parseRaw($nodes);
        }

        return $this->whereFunctionCallHandler(
            $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($column, $nodes),
            $function,
            $operator,
            $value
        );
    }

    /**
    * @param string|Raw $column The database column which holds the JSON value
    * @param string|Raw|string[] $nodes The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @param string $joiner
    * @return static
    */
    protected function whereJsonHandler($column, $nodes, $operator = null, $value = null, string $joiner = 'AND'): self
    {
        // Handle potential raw values.
        if ($column instanceof Raw) {
            $column = $this->adapterInstance->parseRaw($column);
        }
        if ($nodes instanceof Raw) {
            $nodes = $this->adapterInstance->parseRaw($nodes);
        }

        return $this->whereHandler(
            $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($column, $nodes),
            $operator,
            $value,
            $joiner
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $leftColumn
     * @param string|Raw|string[]|null $leftNodes The json key/index to search
     * @param string $operator
     * @param string|Raw $rightColumn
     * @param string|Raw|string[]|null $rightNodes
     * @param string $type
     *
     * @return static
     */
    public function joinJson(
        $table,
        $leftColumn,
        $leftNodes,
        string $operator,
        $rightColumn,
        $rightNodes,
        $type = 'inner'
    ): self {
        // Convert key if json
        if (null !== $rightNodes) {
            $rightColumn = $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($rightColumn, $rightNodes);
        }

        // Convert key if json
        if (null !== $leftNodes) {
            $leftColumn = $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($leftColumn, $leftNodes);
        }

        return $this->join($table, $leftColumn, $operator, $rightColumn, $type);
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $leftColumn
     * @param string|Raw|string[]|null $leftNodes The json key/index to search
     * @param string $operator
     * @param string|Raw $rightColumn
     * @param string|Raw|string[]|null $rightNodes
     *
     * @return static
     */
    public function leftJoinJson(
        $table,
        $leftColumn,
        $leftNodes,
        string $operator,
        $rightColumn,
        $rightNodes
    ): self {
        return $this->joinJson(
            $table,
            $leftColumn,
            $leftNodes,
            $operator,
            $rightColumn,
            $rightNodes,
            'left'
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $leftColumn
     * @param string|Raw|string[]|null $leftNodes The json key/index to search
     * @param string $operator
     * @param string|Raw $rightColumn
     * @param string|Raw|string[]|null $rightNodes
     *
     * @return static
     */
    public function rightJoinJson(
        $table,
        $leftColumn,
        $leftNodes,
        string $operator,
        $rightColumn,
        $rightNodes
    ): self {
        return $this->joinJson(
            $table,
            $leftColumn,
            $leftNodes,
            $operator,
            $rightColumn,
            $rightNodes,
            'right'
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $leftColumn
     * @param string|Raw|string[]|null $leftNodes The json key/index to search
     * @param string $operator
     * @param string|Raw $rightColumn
     * @param string|Raw|string[]|null $rightNodes
     *
     * @return static
     */
    public function outerJoinJson(
        $table,
        $leftColumn,
        $leftNodes,
        string $operator,
        $rightColumn,
        $rightNodes
    ): self {
        return $this->joinJson(
            $table,
            $leftColumn,
            $leftNodes,
            $operator,
            $rightColumn,
            $rightNodes,
            'FULL OUTER'
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $leftColumn
     * @param string|Raw|string[]|null $leftNodes The json key/index to search
     * @param string $operator
     * @param string|Raw $rightColumn
     * @param string|Raw|string[]|null $rightNodes
     *
     * @return static
     */
    public function crossJoinJson(
        $table,
        $leftColumn,
        $leftNodes,
        string $operator,
        $rightColumn,
        $rightNodes
    ): self {
        return $this->joinJson(
            $table,
            $leftColumn,
            $leftNodes,
            $operator,
            $rightColumn,
            $rightNodes,
            'cross'
        );
    }



    // JSON

    /**
     * @param string|Raw $column The database column which holds the JSON value
     * @param string|Raw|string[] $nodes The json key/index to search
     * @param string|null $alias The alias used to define the value in results, if not defined will use json_{$nodes}
     * @return static
     */
    public function selectJson($column, $nodes, ?string $alias = null): self
    {
        // Handle potential raw values.
        if ($column instanceof Raw) {
            $column = $this->adapterInstance->parseRaw($column);
        }
        if ($nodes instanceof Raw) {
            $nodes = $this->adapterInstance->parseRaw($nodes);
        }

        // If deeply nested jsonKey.
        if (is_array($nodes)) {
            $nodes = \implode('.', $nodes);
        }

        // Add any possible prefixes to the key
        $column = $this->addTablePrefix($column, true);

        $alias = null === $alias ? "json_{$nodes}" : $alias;
        return  $this->select(new Raw("JSON_UNQUOTE(JSON_EXTRACT({$column}, \"$.{$nodes}\")) as {$alias}"));
    }
}
