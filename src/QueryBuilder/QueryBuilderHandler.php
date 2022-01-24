<?php

namespace Pixie\QueryBuilder;

use wpdb;
use Closure;
use Throwable;
use Pixie\Binding;
use Pixie\Exception;
use Pixie\Connection;

use Pixie\QueryBuilder\Raw;

use Pixie\Hydration\Hydrator;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\QueryBuilder\QueryObject;
use Pixie\QueryBuilder\Transaction;
use Pixie\QueryBuilder\WPDBAdapter;
use function mb_strlen;

class QueryBuilderHandler
{
    /**
     * @var \Viocon\Container
     */
    protected $container;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array<string, mixed[]|mixed>
     */
    protected $statements = [];

    /**
     * @var wpdb
     */
    protected $dbInstance;

    /**
     * @var string|string[]|null
     */
    protected $sqlStatement = null;

    /**
     * @var string|null
     */
    protected $tablePrefix = null;

    /**
     * @var WPDBAdapter
     */
    protected $adapterInstance;

    /**
     * The mode to return results as.
     * Accepts WPDB constants or class names.
     *
     * @var string
     */
    protected $fetchMode;

    /**
     * Custom args used to construct models for hydrator
     *
     * @var array<int, mixed>|null
     */
    protected $hydratorConstructorArgs;

    /**
     * @param \Pixie\Connection|null $connection
     * @param string $fetchMode
     * @param mixed[] $hydratorConstructorArgs
     *
     * @throws Exception if no connection passed and not previously established
     */
    final public function __construct(
        Connection $connection = null,
        string $fetchMode = \OBJECT,
        ?array $hydratorConstructorArgs = null
    ) {
        if (is_null($connection)) {
            // throws if connection not already established.
            $connection = Connection::getStoredConnection();
        }

        // Set all dependencies from connection.
        $this->connection = $connection;
        $this->container  = $this->connection->getContainer();
        $this->dbInstance = $this->connection->getDbInstance();
        $this->setAdapterConfig($this->connection->getAdapterConfig());

        // Set up optional hydration details.
        $this->setFetchMode($fetchMode);
        $this->hydratorConstructorArgs = $hydratorConstructorArgs;

        // Query builder adapter instance
        $this->adapterInstance = $this->container->build(
            WPDBAdapter::class,
            [$this->connection]
        );
    }

    /**
     * Sets the config for WPDB
     *
     * @param array<string, mixed> $adapterConfig
     *
     * @return void
     */
    protected function setAdapterConfig(array $adapterConfig): void
    {
        if (isset($adapterConfig['prefix'])) {
            $this->tablePrefix = $adapterConfig['prefix'];
        }
    }

    /**
     * Set the fetch mode
     *
     * @param string $mode
     * @param array<int, mixed>|null $constructorArgs
     *
     * @return static
     */
    public function setFetchMode(string $mode, ?array $constructorArgs = null): self
    {
        $this->fetchMode               = $mode;
        $this->hydratorConstructorArgs = $constructorArgs;

        return $this;
    }

    /**
     * @param Connection|null $connection
     *
     * @return static
     *
     * @throws Exception
     */
    public function newQuery(Connection $connection = null): self
    {
        if (is_null($connection)) {
            $connection = $this->connection;
        }

        $newQuery = $this->constructCurrentBuilderClass($connection);
        $newQuery->setFetchMode($this->getFetchMode(), $this->hydratorConstructorArgs);

        return $newQuery;
    }

    /**
     * Returns a new instance of the current, with the passed connection.
     *
     * @param \Pixie\Connection $connection
     *
     * @return static
     */
    protected function constructCurrentBuilderClass(Connection $connection): self
    {
        return new static($connection);
    }

    /**
     * Interpolates a query
     *
     * @param string $query
     * @param array<mixed> $bindings
     * @return string
     */
    public function interpolateQuery(string $query, array $bindings = []): string
    {
        return $this->adapterInstance->interpolateQuery($query, $bindings);
    }

    /**
     * @param string           $sql
     * @param array<int,mixed> $bindings
     *
     * @return static
     */
    public function query($sql, $bindings = []): self
    {
        list($this->sqlStatement) = $this->statement($sql, $bindings);

        return $this;
    }

    /**
     * @param string           $sql
     * @param array<int,mixed> $bindings
     *
     * @return array{0:string, 1:float}
     */
    public function statement(string $sql, $bindings = []): array
    {
        $start        = microtime(true);
        $sqlStatement = empty($bindings) ? $sql : $this->interpolateQuery($sql, $bindings);

        if (!is_string($sqlStatement)) {
            throw new Exception('Could not interpolate query', 1);
        }

        return [$sqlStatement, microtime(true) - $start];
    }

    /**
     * Get all rows
     *
     * @return array<mixed,mixed>|null
     *
     * @throws Exception
     */
    public function get()
    {
        $eventResult = $this->fireEvents('before-select');
        if (!is_null($eventResult)) {
            return $eventResult;
        }
        $executionTime = 0;
        if (is_null($this->sqlStatement)) {
            $queryObject = $this->getQuery('select');
            $statement   = $this->statement(
                $queryObject->getSql(),
                $queryObject->getBindings()
            );

            $this->sqlStatement = $statement[0];
            $executionTime      = $statement[1];
        }

        $start  = microtime(true);
        $result = $this->dbInstance()->get_results(
            is_array($this->sqlStatement) ? (end($this->sqlStatement) ?: '') : $this->sqlStatement,
            // If we are using the hydrator, return as OBJECT and let the hydrator map the correct model.
            $this->useHydrator() ? OBJECT : $this->getFetchMode()
        );
        $executionTime += microtime(true) - $start;
        $this->sqlStatement = null;

        // Ensure we have an array of results.
        if (!is_array($result) && null !== $result) {
            $result = [$result];
        }

        // Maybe hydrate the results.
        if (null !== $result && $this->useHydrator()) {
            $result = $this->getHydrator()->fromMany($result);
        }

        $this->fireEvents('after-select', $result, $executionTime);

        return $result;
    }

    /**
     * Returns a populated instance of the Hydrator.
     *
     * @return Hydrator
     */
    protected function getHydrator(): Hydrator /* @phpstan-ignore-line */
    {
        $hydrator = new Hydrator($this->getFetchMode(), $this->hydratorConstructorArgs ?? []); /* @phpstan-ignore-line */

        return $hydrator;
    }

    /**
     * Checks if the results should be mapped via the hydrator
     *
     * @return bool
     */
    protected function useHydrator(): bool
    {
        return !in_array($this->getFetchMode(), [\ARRAY_A, \ARRAY_N, \OBJECT, \OBJECT_K]);
    }

    /**
     * Find all matching a simple where condition.
     *
     * Shortcut of ->where('key','=','value')->limit(1)->get();
     *
     * @return \stdClass\array<mixed,mixed>|object|null Can return any object using hydrator
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->get();

        return empty($result) ? null : $result[0];
    }

    /**
     * Find all matching a simple where condition.
     *
     * Shortcut of ->where('key','=','value')->get();
     *
     * @param string $fieldName
     * @param mixed $value
     *
     * @return array<mixed,mixed>|null Can return any object using hydrator
     */
    public function findAll($fieldName, $value)
    {
        $this->where($fieldName, '=', $value);

        return $this->get();
    }

    /**
     * @param string $fieldName
     * @param mixed $value
     *
     * @return \stdClass\array<mixed,mixed>|object|null Can return any object using hydrator
     */
    public function find($value, $fieldName = 'id')
    {
        $this->where($fieldName, '=', $value);

        return $this->first();
    }

    /**
     * @param string $fieldName
     * @param mixed $value
     *
     * @return \stdClass\array<mixed,mixed>|object Can return any object using hydrator
     * @throws Exception If fails to find
     */
    public function findOrFail($value, $fieldName = 'id')
    {
        $result = $this->find($value, $fieldName);
        if (null === $result) {
            throw new Exception("Failed to find {$fieldName}={$value}", 1);
        }
        return $result;
    }

    /**
     * Used to handle all aggregation method.
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $type
     * @param string $field
     *
     * @return float
     */
    protected function aggregate(string $type, string $field = '*'): float
    {
        // Verify that field exists
        if ('*' !== $field && true === isset($this->statements['selects']) && false === \in_array($field, $this->statements['selects'], true)) {
            throw new \Exception(sprintf('Failed %s query - the column %s hasn\'t been selected in the query.', $type, $field));
        }

        if (false === isset($this->statements['tables'])) {
            throw new Exception('No table selected');
        }

        $count = $this
            ->table($this->subQuery($this, 'count'))
            ->select([$this->raw(sprintf('%s(%s) AS field', strtoupper($type), $field))])
            ->first();

        return true === isset($count->field) ? (float)$count->field : 0;
    }

    /**
     * Get count of all the rows for the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     *
     * @return int
     *
     * @throws Exception
     */
    public function count(string $field = '*'): int
    {
        return (int)$this->aggregate('count', $field);
    }

    /**
     * Get the sum for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function sum(string $field): float
    {
        return $this->aggregate('sum', $field);
    }

    /**
     * Get the average for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function average(string $field): float
    {
        return $this->aggregate('avg', $field);
    }

    /**
     * Get the minimum for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function min(string $field): float
    {
        return $this->aggregate('min', $field);
    }

    /**
     * Get the maximum for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function max(string $field): float
    {
        return $this->aggregate('max', $field);
    }

    /**
     * @param string $type
     * @param bool|array<mixed, mixed> $dataToBePassed
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getQuery(string $type = 'select', $dataToBePassed = [])
    {
        $allowedTypes = ['select', 'insert', 'insertignore', 'replace', 'delete', 'update', 'criteriaonly'];
        if (!in_array(strtolower($type), $allowedTypes)) {
            throw new Exception($type . ' is not a known type.', 2);
        }

        $queryArr = $this->adapterInstance->$type($this->statements, $dataToBePassed);

        return $this->container->build(
            QueryObject::class,
            [$queryArr['sql'], $queryArr['bindings'], $this->dbInstance]
        );
    }

    /**
     * @param QueryBuilderHandler $queryBuilder
     * @param string|null $alias
     *
     * @return Raw
     */
    public function subQuery(QueryBuilderHandler $queryBuilder, ?string $alias = null)
    {
        $sql = '(' . $queryBuilder->getQuery()->getRawSql() . ')';
        if (is_string($alias) && 0 !== mb_strlen($alias)) {
            $sql = $sql . ' as ' . $alias;
        }

        return $queryBuilder->raw($sql);
    }

    /**
     * Handles the various insert operations based on the type.
     *
     * @param array<int|string, mixed|mixed[]> $data
     * @param string $type
     *
     * @return int|int[]|mixed|null can return a single row id, array of row ids, null (for failed) or any other value short circuited from event
     */
    private function doInsert(array $data, string $type)
    {
        $eventResult = $this->fireEvents('before-insert');
        if (!is_null($eventResult)) {
            return $eventResult;
        }

        // If first value is not an array () not a batch insert)
        if (!is_array(current($data))) {
            $queryObject = $this->getQuery($type, $data);

            list($preparedQuery, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
            $this->dbInstance->get_results($preparedQuery);

            // Check we have a result.
            $return = 1 === $this->dbInstance->rows_affected ? $this->dbInstance->insert_id : null;
        } else {
            // Its a batch insert
            $return        = [];
            $executionTime = 0;
            foreach ($data as $subData) {
                $queryObject = $this->getQuery($type, $subData);

                list($preparedQuery, $time) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
                $this->dbInstance->get_results($preparedQuery);
                $executionTime += $time;

                if (1 === $this->dbInstance->rows_affected) {
                    $return[] = $this->dbInstance->insert_id;
                }
            }
        }

        $this->fireEvents('after-insert', $return, $executionTime);

        return $return;
    }

    /**
     * @param array<int|string, mixed|mixed[]> $data either key=>value array for single or array of arrays for bulk
     *
     * @return int|int[]|mixed|null can return a single row id, array of row ids, null (for failed) or any other value short circuited from event
     */
    public function insert($data)
    {
        return $this->doInsert($data, 'insert');
    }

    /**
     *
     * @param array<int|string, mixed|mixed[]> $data either key=>value array for single or array of arrays for bulk
     *
     * @return int|int[]|mixed|null can return a single row id, array of row ids, null (for failed) or any other value short circuited from event
     */
    public function insertIgnore($data)
    {
        return $this->doInsert($data, 'insertignore');
    }

    /**
     *
     * @param array<int|string, mixed|mixed[]> $data either key=>value array for single or array of arrays for bulk
     *
     * @return int|int[]|mixed|null can return a single row id, array of row ids, null (for failed) or any other value short circuited from event
     */
    public function replace($data)
    {
        return $this->doInsert($data, 'replace');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int|null
     */
    public function update($data)
    {
        $eventResult = $this->fireEvents('before-update');
        if (!is_null($eventResult)) {
            return $eventResult;
        }
        $queryObject                         = $this->getQuery('update', $data);
        list($preparedQuery, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());

        $this->dbInstance()->get_results($preparedQuery);
        $this->fireEvents('after-update', $queryObject, $executionTime);

        return 0 !== $this->dbInstance()->rows_affected
            ? $this->dbInstance()->rows_affected
            : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int|null will return row id for insert and bool for success/fail on update
     */
    public function updateOrInsert($data)
    {
        if ($this->first()) {
            return $this->update($data);
        }

        return $this->insert($data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return static
     */
    public function onDuplicateKeyUpdate($data)
    {
        $this->addStatement('onduplicate', $data);

        return $this;
    }

    /**
     * @return int number of rows effected
     */
    public function delete(): int
    {
        $eventResult = $this->fireEvents('before-delete');
        if (!is_null($eventResult)) {
            return $eventResult;
        }

        $queryObject = $this->getQuery('delete');

        list($preparedQuery, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        $this->dbInstance()->get_results($preparedQuery);
        $this->fireEvents('after-delete', $queryObject, $executionTime);

        return $this->dbInstance()->rows_affected;
    }

    /**
     * @param string|Raw ...$tables Single table or array of tables
     *
     * @return static
     *
     * @throws Exception
     */
    public function table(...$tables): QueryBuilderHandler
    {
        $instance =  $this->constructCurrentBuilderClass($this->connection);
        $this->setFetchMode($this->getFetchMode(), $this->hydratorConstructorArgs);
        $tables = $this->addTablePrefix($tables, false);
        $instance->addStatement('tables', $tables);

        return $instance;
    }

    /**
     * @param string|Raw ...$tables Single table or array of tables
     *
     * @return static
     */
    public function from(...$tables): self
    {
        $tables = $this->addTablePrefix($tables, false);
        $this->addStatement('tables', $tables);

        return $this;
    }

    /**
     * @param string|string[]|Raw[]|array<string, string> $fields
     *
     * @return static
     */
    public function select($fields): self
    {
        if (!is_array($fields)) {
            $fields = func_get_args();
        }

        foreach ($fields as $field => $alias) {
            // If we have a JSON expression
            if ($this->isJsonExpression($field)) {
                // Add using JSON select.
                $this->castToJsonSelect($field, $alias);
                unset($fields[$field]);
                continue;
            }

            // If no alias passed, but field is for JSON. thrown an exception.
            if (is_numeric($field) && $this->isJsonExpression($alias)) {
                throw new Exception("An alias must be used if you wish to select from JSON Object", 1);
            }

            // Treat each array as a single table, to retain order added
            $field = is_numeric($field)
                ? $field = $alias // If single colum
                : $field = [$field => $alias]; // Has alias

            $field = $this->addTablePrefix($field);
            $this->addStatement('selects', $field);
        }



        return $this;
    }

    /**
     * Checks if the passed expression is for JSON
     * this->denotes->json
     *
     * @param string $expression
     * @return bool
     */
    protected function isJsonExpression(string $expression): bool
    {
        return 2 <= count(explode('->', $expression));
    }

    /**
     * Casts a select to JSON based on -> in column name.
     *
     * @param string $keys
     * @param string|null $alias
     * @return self
     */
    public function castToJsonSelect(string $keys, ?string $alias): self
    {
        $parts = explode('->', $keys);
        $field = $parts[0];
        unset($parts[0]);
        return $this->selectJson($field, $parts, $alias);
    }

    /**
     * Gets the column name form a potential array
     *
     * @param string $expression
     * @return string
     */
    protected function getColumnFromJsonExpression(string $expression): string
    {
        if (! $this->isJsonExpression($expression)) {
            throw new Exception('JSON expression must contain at least 2 values, the table column and JSON key.', 1);
        }

        /** @var string[] Check done above. */
        $parts = explode('->', $expression);
        return $parts[0];
    }

    /**
     * Gets all JSON object keys while removing the column name.
     *
     * @param string $expression
     * @return string[]
     */
    protected function getJsonKeysFromExpression($expression): array
    {
        if (! $this->isJsonExpression($expression)) {
            throw new Exception('JSON expression must contain at least 2 values, the table column and JSON key.', 1);
        }

        /** @var string[] Check done above. */
        $parts = explode('->', $expression);
        unset($parts[0]);
        return $parts;
    }

    /**
     * @param string|string[]|Raw[]|array<string, string> $fields
     *
     * @return static
     */
    public function selectDistinct($fields)
    {
        $this->select($fields);
        $this->addStatement('distinct', true);

        return $this;
    }

    /**
     * @param string|string[] $field either the single field or an array of fields
     *
     * @return static
     */
    public function groupBy($field): self
    {
        $field = $this->addTablePrefix($field);
        $this->addStatement('groupBys', $field);

        return $this;
    }

    /**
     * @param string|array<string|int, mixed> $fields
     * @param string          $defaultDirection
     *
     * @return static
     */
    public function orderBy($fields, string $defaultDirection = 'ASC'): self
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $key => $value) {
            $field = $key;
            $type  = $value;
            if (is_int($key)) {
                $field = $value;
                $type  = $defaultDirection;
            }

            if ($this->isJsonExpression($field)) {
                $field = $this->jsonParseExtractThenUnquote(
                    $this->getColumnFromJsonExpression($field),
                    $this->getJsonKeysFromExpression($field)
                );
            }

            if (!$field instanceof Raw) {
                $field = $this->addTablePrefix($field);
            }
            $this->statements['orderBys'][] = compact('field', 'type');
        }

        return $this;
    }

    /**
     * @param string|Raw $key The database column which holds the JSON value
     * @param string|Raw|string[] $jsonKey The json key/index to search
     * @param string $defaultDirection
     * @return static
     */
    public function orderByJson($key, $jsonKey, string $defaultDirection = 'ASC'): self
    {
        $key = $this->jsonParseExtractThenUnquote($key, $jsonKey);
        return $this->orderBy($key, $defaultDirection);
    }

    /**
     * @param int $limit
     *
     * @return static
     */
    public function limit(int $limit): self
    {
        $this->statements['limit'] = $limit;

        return $this;
    }

    /**
     * @param int $offset
     *
     * @return static
     */
    public function offset(int $offset): self
    {
        $this->statements['offset'] = $offset;

        return $this;
    }

    /**
     * @param string|string[]|Raw|Raw[]       $key
     * @param string $operator
     * @param mixed $value
     * @param string $joiner
     *
     * @return static
     */
    public function having($key, string $operator, $value, string $joiner = 'AND')
    {
        $key                           = $this->addTablePrefix($key);
        $this->statements['havings'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * @param string|string[]|Raw|Raw[]       $key
     * @param string $operator
     * @param mixed $value
     *
     * @return static
     */
    public function orHaving($key, $operator, $value)
    {
        return $this->having($key, $operator, $value, 'OR');
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function where($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function orWhere($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR');
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function whereNot($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'AND NOT');
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function orWhereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR NOT');
    }

    /**
     * @param string|Raw $key
     * @param mixed[]|string|Raw $values
     *
     * @return static
     */
    public function whereIn($key, $values): self
    {
        return $this->whereHandler($key, 'IN', $values, 'AND');
    }

    /**
     * @param string|Raw $key
     * @param mixed[]|string|Raw $values
     *
     * @return static
     */
    public function whereNotIn($key, $values): self
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'AND');
    }

    /**
     * @param string|Raw $key
     * @param mixed[]|string|Raw $values
     *
     * @return static
     */
    public function orWhereIn($key, $values): self
    {
        return $this->whereHandler($key, 'IN', $values, 'OR');
    }

    /**
     * @param string|Raw $key
     * @param mixed[]|string|Raw $values
     *
     * @return static
     */
    public function orWhereNotIn($key, $values): self
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'OR');
    }

    /**
     * @param string|Raw $key
     * @param mixed $valueFrom
     * @param mixed $valueTo
     *
     * @return static
     */
    public function whereBetween($key, $valueFrom, $valueTo): self
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo], 'AND');
    }

    /**
     * @param string|Raw $key
     * @param mixed $valueFrom
     * @param mixed $valueTo
     *
     * @return static
     */
    public function orWhereBetween($key, $valueFrom, $valueTo): self
    {
        return $this->whereHandler($key, 'BETWEEN', [$valueFrom, $valueTo], 'OR');
    }

    /**
     * Handles all function call based where conditions
     *
     * @param string|Raw $key
     * @param string $function
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return static
     */
    protected function whereFunctionCallHandler($key, $function, $operator, $value): self
    {
        $key = \sprintf('%s(%s)', $function, $this->addTablePrefix($key));
        return $this->where($key, $operator, $value);
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return self
     */
    public function whereMonth($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallHandler($key, 'MONTH', $operator, $value);
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return self
     */
    public function whereDay($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallHandler($key, 'DAY', $operator, $value);
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return self
     */
    public function whereYear($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallHandler($key, 'YEAR', $operator, $value);
    }

    /**
     * @param string|Raw $key
     * @param string|mixed|null $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     * @return self
     */
    public function whereDate($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }
        return $this->whereFunctionCallHandler($key, 'DATE', $operator, $value);
    }

    /**
     * @param string|Raw $key
     *
     * @return static
     */
    public function whereNull($key): self
    {
        return $this->whereNullHandler($key);
    }

    /**
     * @param string|Raw $key
     *
     * @return static
     */
    public function whereNotNull($key): self
    {
        return $this->whereNullHandler($key, 'NOT');
    }

    /**
     * @param string|Raw $key
     *
     * @return static
     */
    public function orWhereNull($key): self
    {
        return $this->whereNullHandler($key, '', 'or');
    }

    /**
     * @param string|Raw $key
     *
     * @return static
     */
    public function orWhereNotNull($key): self
    {
        return $this->whereNullHandler($key, 'NOT', 'or');
    }

    /**
     * @param string|Raw $key
     * @param string $prefix
     * @param string $operator
     *
     * @return static
     */
    protected function whereNullHandler($key, string $prefix = '', $operator = ''): self
    {
        $prefix = 0 === mb_strlen($prefix) ? '' : " {$prefix}";

        if ($key instanceof Raw) {
            $key = $this->adapterInstance->parseRaw($key);
        }

        $key = $this->adapterInstance->wrapSanitizer($this->addTablePrefix($key));
        if ($key instanceof Closure) {
            throw new Exception('Key used for whereNull condition must be a string or raw exrpession.', 1);
        }

        return $this->{$operator . 'Where'}($this->raw("{$key} IS{$prefix} NULL"));
    }

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
        return $this->whereFunctionCallHandler(
            $this->jsonParseExtractThenUnquote($key, $jsonKey),
            $function,
            $operator,
            $value
        );
    }

    /**
     * @param string|Raw $key The database column which holds the JSON value
     * @param string|Raw|string[] $jsonKey The json key/index to search
     * @return \Pixie\QueryBuilder\Raw
     */
    protected function jsonParseExtractThenUnquote($key, $jsonKey): Raw
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

        return new Raw("JSON_UNQUOTE(JSON_EXTRACT({$key}, \"$.{$jsonKey}\"))");
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
        return  $this->whereHandler(
            $this->jsonParseExtractThenUnquote($key, $jsonKey),
            $operator,
            $value,
            $joiner
        );
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|Closure $key
     * @param string|null $operator
     * @param mixed $value
     * @param string $type
     *
     * @return static
     */
    public function join($table, $key, ?string $operator = null, $value = null, $type = 'inner')
    {
        if (!$key instanceof Closure) {
            $key = function ($joinBuilder) use ($key, $operator, $value) {
                $joinBuilder->on($key, $operator, $value);
            };
        }

        // Build a new JoinBuilder class, keep it by reference so any changes made
        // in the closure should reflect here
        $joinBuilder = $this->container->build(JoinBuilder::class, [$this->connection]);
        $joinBuilder = &$joinBuilder;
        // Call the closure with our new joinBuilder object
        $key($joinBuilder);
        $table = $this->addTablePrefix($table, false);
        // Get the criteria only query from the joinBuilder object
        $this->statements['joins'][] = compact('type', 'table', 'joinBuilder');
        return $this;
    }

    /**
     * Runs a transaction
     *
     * @param \Closure(Transaction):void $callback
     *
     * @return static
     */
    public function transaction(Closure $callback): self
    {
        try {
            // Begin the transaction
            $this->dbInstance->query('START TRANSACTION');

            // Get the Transaction class
            $transaction = $this->container->build(Transaction::class, [$this->connection]);

            $this->handleTransactionCall($callback, $transaction);

            // If no errors have been thrown or the transaction wasn't completed within
            $this->dbInstance->query('COMMIT');

            return $this;
        } catch (TransactionHaltException $e) {
            // Commit or rollback behavior has been handled in the closure, so exit
            return $this;
        } catch (\Exception $e) {
            // something happened, rollback changes
            $this->dbInstance->query('ROLLBACK');

            return $this;
        }
    }

    /**
     * Handles the transaction call.
     *
     * Catches any WP Errors (printed)
     *
     * @param Closure    $callback
     * @param Transaction $transaction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function handleTransactionCall(Closure $callback, Transaction $transaction): void
    {
        try {
            ob_start();
            $callback($transaction);
            $output = ob_get_clean() ?: '';
        } catch (Throwable $th) {
            ob_end_clean();
            throw $th;
        }

        // If we caught an error, throw an exception.
        if (0 !== mb_strlen($output)) {
            throw new Exception($output);
        }
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|Closure $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    public function leftJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'left');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|Closure $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    public function rightJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'right');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|Closure $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    public function innerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'inner');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|Closure $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    public function crossJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'cross');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|Closure $key
     * @param string|null $operator
     * @param mixed $value
     *
     * @return static
     */
    public function outerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'outer');
    }

    /**
     * Shortcut to join 2 tables on the same key name with equals
     *
     * @param string $table
     * @param string $key
     * @param string $type
     * @return self
     * @throws Exception If base table is set as more than 1 or 0
     */
    public function joinUsing(string $table, string $key, string $type = 'INNER'): self
    {
        if (!array_key_exists('tables', $this->statements) || count($this->statements['tables']) !== 1) {
            throw new Exception("JoinUsing can only be used with a single table set as the base of the query", 1);
        }
        $baseTable = end($this->statements['tables']);

        $remoteKey = $table = $this->addTablePrefix("{$table}.{$key}", true);
        $localKey = $table = $this->addTablePrefix("{$baseTable}.{$key}", true);
        return $this->join($table, $remoteKey, '=', $localKey, $type);
    }

    /**
     * Add a raw query
     *
     * @param string|Raw $value
     * @param mixed|mixed[] $bindings
     *
     * @return Raw
     */
    public function raw($value, $bindings = []): Raw
    {
        return new Raw($value, $bindings);
    }

    /**
     * Return wpdb instance
     *
     * @return wpdb
     */
    public function dbInstance(): wpdb
    {
        return $this->dbInstance;
    }

    /**
     * @param Connection $connection
     *
     * @return static
     */
    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string|Raw|Closure $key
     * @param string|null      $operator
     * @param mixed|null       $value
     * @param string $joiner
     *
     * @return static
     */
    protected function whereHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        if ($key instanceof Raw) {
            $key = $this->adapterInstance->parseRaw($key);
        }

        // If JSON send to JSON handler
        if (is_string($key) && $this->isJsonExpression($key)) {
            $column = $this->getColumnFromJsonExpression($key);
            $jsonKeys = $this->getJsonKeysFromExpression($key);
            $this->whereJsonHandler($column, $jsonKeys, $operator, $value, $joiner);
            return $this;
        }

        $this->statements['wheres'][] = compact('key', 'operator', 'value', 'joiner');
        return $this;
    }

    /**
     * Add table prefix (if given) on given string.
     *
     * @param array<string|int, string|int|float|bool|Raw|Closure>|string|int|float|bool|Raw|Closure     $values
     * @param bool $tableFieldMix If we have mixes of field and table names with a "."
     *
     * @return mixed|mixed[]
     */
    public function addTablePrefix($values, bool $tableFieldMix = true)
    {
        if (is_null($this->tablePrefix)) {
            return $values;
        }

        // $value will be an array and we will add prefix to all table names

        // If supplied value is not an array then make it one
        $single = false;
        if (!is_array($values)) {
            $values = [$values];
            // We had single value, so should return a single value
            $single = true;
        }

        $return = [];

        foreach ($values as $key => $value) {
            // It's a raw query, just add it to our return array and continue next
            if ($value instanceof Raw || $value instanceof Closure) {
                $return[$key] = $value;
                continue;
            }

            // If key is not integer, it is likely a alias mapping,
            // so we need to change prefix target
            $target = &$value;
            if (!is_int($key)) {
                $target = &$key;
            }

            // Do prefix if the target is an expression or function.
            if (
                !$tableFieldMix
                || (
                    is_string($target) // Must be a string
                    && (bool) preg_match('/^[A-Za-z0-9_.]+$/', $target) // Can only contain letters, numbers, underscore and full stops
                    && 1 === \substr_count($target, '.') // Contains a single full stop ONLY.
                )
            ) {
                $target = $this->tablePrefix . $target;
            }

            $return[$key] = $value;
        }

        // If we had single value then we should return a single value (end value of the array)
        return true === $single ? end($return) : $return;
    }

    /**
     * @param string $key
     * @param mixed|mixed[]|bool $value
     *
     * @return void
     */
    protected function addStatement($key, $value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        if (!array_key_exists($key, $this->statements)) {
            $this->statements[$key] = $value;
        } else {
            $this->statements[$key] = array_merge($this->statements[$key], $value);
        }
    }

    /**
     * @param string $event
     * @param string|Raw $table
     *
     * @return callable|null
     */
    public function getEvent(string $event, $table = ':any'): ?callable
    {
        return $this->connection->getEventHandler()->getEvent($event, $table);
    }

    /**
     * @param string $event
     * @param string|Raw $table
     * @param Closure $action
     *
     * @return void
     */
    public function registerEvent($event, $table, Closure $action): void
    {
        $table = $table ?: ':any';

        if (':any' != $table) {
            $table = $this->addTablePrefix($table, false);
        }

        $this->connection->getEventHandler()->registerEvent($event, $table, $action);
    }

    /**
     * @param string $event
     * @param string|Raw $table
     *
     * @return void
     */
    public function removeEvent(string $event, $table = ':any')
    {
        if (':any' != $table) {
            $table = $this->addTablePrefix($table, false);
        }

        $this->connection->getEventHandler()->removeEvent($event, $table);
    }

    /**
     * @param string $event
     *
     * @return mixed
     */
    public function fireEvents(string $event)
    {
        $params = func_get_args(); // @todo Replace this with an easier to read alteratnive
        array_unshift($params, $this);

        return call_user_func_array([$this->connection->getEventHandler(), 'fireEvents'], $params);
    }

    /**
     * @return array<string, mixed[]>
     */
    public function getStatements()
    {
        return $this->statements;
    }

    /**
     * @return string will return WPDB Fetch mode
     */
    public function getFetchMode()
    {
        return null !== $this->fetchMode
            ? $this->fetchMode
            : \OBJECT;
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
