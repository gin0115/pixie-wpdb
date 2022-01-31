<?php

namespace Pixie\QueryBuilder;

class jsonQueryBuilder extends QueryBuilderHandler
{
    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($key, $jsonKey, $operator, $value, 'AND');
    }

    /**
     * @param string|Raw $key The database column which holds the JSON value
     * @param string|Raw|string[] $jsonKey The json key/index to search
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return static
     */
    public function whereNotJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($key, $jsonKey, $operator, $value, 'AND NOT');
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function orWhereJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($key, $jsonKey, $operator, $value, 'OR');
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function orWhereNotJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereJsonHandler($key, $jsonKey, $operator, $value, 'OR NOT');
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function whereInJson($key, $jsonKey, $values): self
    {
        return $this->whereJsonHandler($key, $jsonKey, 'IN', $values, 'AND');
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function whereNotInJson($key, $jsonKey, $values): self
    {
        return $this->whereJsonHandler($key, $jsonKey, 'NOT IN', $values, 'AND');
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function orWhereInJson($key, $jsonKey, $values): self
    {
        return $this->whereJsonHandler($key, $jsonKey, 'IN', $values, 'OR');
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param mixed[] $values
    * @return static
    */
    public function orWhereNotInJson($key, $jsonKey, $values): self
    {
        return $this->whereJsonHandler($key, $jsonKey, 'NOT IN', $values, 'OR');
    }

    /**
     * @param string|Raw $key
    * @param string|Raw|string[] $jsonKey The json key/index to search
     * @param mixed $valueFrom
     * @param mixed $valueTo
     *
     * @return static
     */
    public function whereBetweenJson($key, $jsonKey, $valueFrom, $valueTo): self
    {
        return $this->whereJsonHandler($key, $jsonKey, 'BETWEEN', [$valueFrom, $valueTo], 'AND');
    }

    /**
     * @param string|Raw $key
    * @param string|Raw|string[] $jsonKey The json key/index to search
     * @param mixed $valueFrom
     * @param mixed $valueTo
     *
     * @return static
     */
    public function orWhereBetweenJson($key, $jsonKey, $valueFrom, $valueTo): self
    {
        return $this->whereJsonHandler($key, $jsonKey, 'BETWEEN', [$valueFrom, $valueTo], 'OR');
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereDayJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($key, $jsonKey, 'DAY', $operator, $value);
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereMonthJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($key, $jsonKey, 'MONTH', $operator, $value);
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereYearJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($key, $jsonKey, 'YEAR', $operator, $value);
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @return static
    */
    public function whereDateJson($key, $jsonKey, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (3 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallJsonHandler($key, $jsonKey, 'DATE', $operator, $value);
    }

    /**
     * Maps a function call for a JSON where condition
     *
     * @param string|Raw $key
     * @param string|Raw|string[] $jsonKey
     * @param string $function
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return static
     */
    protected function whereFunctionCallJsonHandler($key, $jsonKey, $function, $operator, $value): self
    {
        // Handle potential raw values.
        if ($key instanceof Raw) {
            $key = $this->adapterInstance->parseRaw($key);
        }
        if ($jsonKey instanceof Raw) {
            $jsonKey = $this->adapterInstance->parseRaw($jsonKey);
        }

        return $this->whereFunctionCallHandler(
            $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($key, $jsonKey),
            $function,
            $operator,
            $value
        );
    }

    /**
    * @param string|Raw $key The database column which holds the JSON value
    * @param string|Raw|string[] $jsonKey The json key/index to search
    * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
    * @param mixed|null $value
    * @param string $joiner
    * @return static
    */
    protected function whereJsonHandler($key, $jsonKey, $operator = null, $value = null, string $joiner = 'AND'): self
    {
        // Handle potential raw values.
        if ($key instanceof Raw) {
            $key = $this->adapterInstance->parseRaw($key);
        }
        if ($jsonKey instanceof Raw) {
            $jsonKey = $this->adapterInstance->parseRaw($jsonKey);
        }

        return $this->whereHandler(
            $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($key, $jsonKey),
            $operator,
            $value,
            $joiner
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $remoteColumn
     * @param string|Raw|string[]|null $remoteJsonKeys The json key/index to search
     * @param string $operator
     * @param string|Raw $localColumn
     * @param string|Raw|string[]|null $localJsonKeys
     * @param string $type
     *
     * @return static
     */
    public function joinJson(
        $table,
        $remoteColumn,
        $remoteJsonKeys,
        string $operator,
        $localColumn,
        $localJsonKeys,
        $type = 'inner'
    ): self {
        // Convert key if json
        if (null !== $localJsonKeys) {
            $localColumn = $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($localColumn, $localJsonKeys);
        }

        // Convert key if json
        if (null !== $remoteJsonKeys) {
            $remoteColumn = $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($remoteColumn, $remoteJsonKeys);
        }

        return $this->join($table, $remoteColumn, $operator, $localColumn, $type);
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $remoteColumn
     * @param string|Raw|string[]|null $remoteJsonKeys The json key/index to search
     * @param string $operator
     * @param string|Raw $localColumn
     * @param string|Raw|string[]|null $localJsonKeys
     *
     * @return static
     */
    public function leftJoinJson(
        $table,
        $remoteColumn,
        $remoteJsonKeys,
        string $operator,
        $localColumn,
        $localJsonKeys
    ): self {
        return $this->joinJson(
            $table,
            $remoteColumn,
            $remoteJsonKeys,
            $operator,
            $localColumn,
            $localJsonKeys,
            'left'
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $remoteColumn
     * @param string|Raw|string[]|null $remoteJsonKeys The json key/index to search
     * @param string $operator
     * @param string|Raw $localColumn
     * @param string|Raw|string[]|null $localJsonKeys
     *
     * @return static
     */
    public function rightJoinJson(
        $table,
        $remoteColumn,
        $remoteJsonKeys,
        string $operator,
        $localColumn,
        $localJsonKeys
    ): self {
        return $this->joinJson(
            $table,
            $remoteColumn,
            $remoteJsonKeys,
            $operator,
            $localColumn,
            $localJsonKeys,
            'right'
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $remoteColumn
     * @param string|Raw|string[]|null $remoteJsonKeys The json key/index to search
     * @param string $operator
     * @param string|Raw $localColumn
     * @param string|Raw|string[]|null $localJsonKeys
     *
     * @return static
     */
    public function outerJoinJson(
        $table,
        $remoteColumn,
        $remoteJsonKeys,
        string $operator,
        $localColumn,
        $localJsonKeys
    ): self {
        return $this->joinJson(
            $table,
            $remoteColumn,
            $remoteJsonKeys,
            $operator,
            $localColumn,
            $localJsonKeys,
            'outer'
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw $remoteColumn
     * @param string|Raw|string[]|null $remoteJsonKeys The json key/index to search
     * @param string $operator
     * @param string|Raw $localColumn
     * @param string|Raw|string[]|null $localJsonKeys
     *
     * @return static
     */
    public function crossJoinJson(
        $table,
        $remoteColumn,
        $remoteJsonKeys,
        string $operator,
        $localColumn,
        $localJsonKeys
    ): self {
        return $this->joinJson(
            $table,
            $remoteColumn,
            $remoteJsonKeys,
            $operator,
            $localColumn,
            $localJsonKeys,
            'cross'
        );
    }



    // JSON

    /**
     * @param string|Raw $key The database column which holds the JSON value
     * @param string|Raw|string[] $jsonKey The json key/index to search
     * @param string|null $alias The alias used to define the value in results, if not defined will use json_{$jsonKey}
     * @return static
     */
    public function selectJson($key, $jsonKey, ?string $alias = null): self
    {
        // Handle potential raw values.
        if ($key instanceof Raw) {
            $key = $this->adapterInstance->parseRaw($key);
        }
        if ($jsonKey instanceof Raw) {
            $jsonKey = $this->adapterInstance->parseRaw($jsonKey);
        }

        // If deeply nested jsonKey.
        if (is_array($jsonKey)) {
            $jsonKey = \implode('.', $jsonKey);
        }

        // Add any possible prefixes to the key
        $key = $this->addTablePrefix($key, true);

        $alias = null === $alias ? "json_{$jsonKey}" : $alias;
        return  $this->select(new Raw("JSON_UNQUOTE(JSON_EXTRACT({$key}, \"$.{$jsonKey}\")) as {$alias}"));
    }
}
