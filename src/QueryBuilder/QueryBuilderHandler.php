<?php

namespace Pixie\QueryBuilder;

use PDO;
use Pixie\Exception;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Hydration\Hydrator;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\QueryBuilder\QueryObject;
use Pixie\QueryBuilder\Transaction;
// use Pixie\QueryBuilder\Adapters\wpdb
use Pixie\QueryBuilder\WPDBAdapter;

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
     * @var array<string, mixed[]>
     */
    protected $statements = array();

    /**
     * @var \wpdb
     */
    protected $dbInstance;

    /**
     * @var null|string|string[]
     */
    protected $sqlStatement = null;

    /**
     * @var null|string
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
     * @param null|\Pixie\Connection $connection
     * @param string $fetchMode
     * @throws Exception If no connection passed and not previously established.
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
        $this->container = $this->connection->getContainer();
        $this->dbInstance = $this->connection->getDbInstance();
        $this->setAdapterConfig($this->connection->getAdapterConfig());

        // Set up optional hydration details.
        $this->setFetchMode($fetchMode);
        $this->hydratorConstructorArgs = $hydratorConstructorArgs;


        // Query builder adapter instance
        $this->adapterInstance = $this->container->build(
            WPDBAdapter::class,
            array($this->connection)
        );
    }

    /**
     * Sets the config for WPDB
     *
     * @param array<string, mixed> $adapterConfig
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
     * @param null|array<int, mixed> $constructorArgs
     * @return static
     */
    public function setFetchMode(string $mode, ?array $constructorArgs = null): self
    {
        $this->fetchMode = $mode;
        $this->hydratorConstructorArgs = $constructorArgs;
        return $this;
    }

    /**
     * @param null|Connection $connection
     * @return static
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
     * @return static
     */
    protected function constructCurrentBuilderClass(Connection $connection): self
    {
        return new static($connection);
    }

    /**
     * @param string           $sql
     * @param array<int,mixed> $bindings
     *
     * @return static
     */
    public function query($sql, $bindings = array()): self
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
    public function statement(string $sql, $bindings = array()): array
    {
        $start = microtime(true);
        $sqlStatement = empty($bindings) ? $sql : $this->dbInstance->prepare($sql, $bindings);

        return array($sqlStatement, microtime(true) - $start);
    }

    /**
     * Get all rows
     *
     * @return object|object[]
     * @throws Exception
     */
    public function get()
    {
        $eventResult = $this->fireEvents('before-select');
        if (!is_null($eventResult)) {
            return $eventResult;
        };
        $executionTime = 0;
        if (is_null($this->sqlStatement)) {
            $queryObject = $this->getQuery('select');

            list($this->sqlStatement, $executionTime) = $this->statement(
                $queryObject->getSql(),
                $queryObject->getBindings()
            );
        }

        $start = microtime(true);
        $result = $this->dbInstance()->get_results(
            $this->sqlStatement,
            $this->useHydrator() ? OBJECT : $this->getFetchMode()
        );
        $executionTime += microtime(true) - $start;
        $this->sqlStatement = null;

        // Maybe hydrate the results.
        if (is_array($result) && $this->useHydrator()) {
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
    protected function getHydrator(): Hydrator
    {
        return new Hydrator($this->getFetchMode(), $this->hydratorConstructorArgs ?? []);
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
     * @return null|\stdClass\array<mixed,mixed>|object Can return any object using hydrator
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
     * @return null|\stdClass\array<mixed,mixed>|object Can return any object using hydrator
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
     * @return null|\stdClass\array<mixed,mixed>|object Can return any object using hydrator
     */
    public function find($value, $fieldName = 'id')
    {
        $this->where($fieldName, '=', $value);
        return $this->first();
    }

    /**
     * Used to handle all aggregation method.
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $type
     * @param string $field
     * @return float
     */
    protected function aggregate(string $type, string $field = '*'): float
    {
        // Verify that field exists
        if ($field !== '*' && isset($this->statements['selects']) === true && \in_array($field, $this->statements['selects'], true) === false) {
            throw new \Exception(sprintf('Failed %s query - the column %s hasn\'t been selected in the query.', $type, $field));
        }

        if (isset($this->statements['tables']) === false) {
            throw new Exception('No table selected');
        }

        $count = $this
            ->table($this->subQuery($this, 'count'))
            ->select([$this->raw(sprintf('%s(%s) AS field', strtoupper($type), $field))])
            ->first();

        return isset($count->field) === true ? (float)$count->field : 0;
    }

    /**
     * Get count of all the rows for the current query
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     *
     * @return integer
     * @throws Exception
     */
    public function count(string $field = '*'): int
    {
        return (int)$this->aggregate('count', $field);
    }

    /**
     * Get the sum for a field in the current query
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     * @return float
     * @throws Exception
     */
    public function sum(string $field): float
    {
        return $this->aggregate('sum', $field);
    }

    /**
     * Get the average for a field in the current query
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     * @return float
     * @throws Exception
     */
    public function average(string $field): float
    {
        return $this->aggregate('avg', $field);
    }

    /**
     * Get the minimum for a field in the current query
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     * @return float
     * @throws Exception
     */
    public function min(string $field): float
    {
        return $this->aggregate('min', $field);
    }

    /**
     * Get the maximum for a field in the current query
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $field
     * @return float
     * @throws Exception
     */
    public function max(string $field): float
    {
        return $this->aggregate('max', $field);
    }

    /**
     * @param string $type
     * @param array $dataToBePassed
     *
     * @return mixed
     * @throws Exception
     */
    public function getQuery($type = 'select', $dataToBePassed = array())
    {
        $allowedTypes = array('select', 'insert', 'insertignore', 'replace', 'delete', 'update', 'criteriaonly');
        if (!in_array(strtolower($type), $allowedTypes)) {
            throw new Exception($type . ' is not a known type.', 2);
        }

        $queryArr = $this->adapterInstance->$type($this->statements, $dataToBePassed);

        return $this->container->build(
            QueryObject::class,
            array($queryArr['sql'], $queryArr['bindings'], $this->dbInstance)
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
        if (is_string($alias) && \mb_strlen($alias) !== 0) {
            $sql = $sql . ' as ' . $alias;
        }

        return $queryBuilder->raw($sql);
    }

    /**
     * Handles the various insert operations based on the type.
     *
     * @param array<int|string, mixed|mixed[]> $data
     * @param string $type
     * @return int|int[]|null|mixed Can return a single row id, array of row ids, null (for failed) or any other value short circuited from event.
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
            $return = $this->dbInstance->rows_affected === 1 ? $this->dbInstance->insert_id : null;
        } else {
            // Its a batch insert
            $return = array();
            $executionTime = 0;
            foreach ($data as $subData) {
                $queryObject = $this->getQuery($type, $subData);

                list($preparedQuery, $time) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
                $this->dbInstance->get_results($preparedQuery);
                $executionTime += $time;

                if ($this->dbInstance->rows_affected === 1) {
                    $return[] = $this->dbInstance->insert_id;
                }
            }
        }

        $this->fireEvents('after-insert', $return, $executionTime);

        return $return;
    }

    /**
     * @param array<int|string, mixed|mixed[]> $data Either key=>value array for single or array of arrays for bulk.
     *
     * @return int|int[]|null|mixed Can return a single row id, array of row ids, null (for failed) or any other value short circuited from event.
     */
    public function insert($data)
    {
        return $this->doInsert($data, 'insert');
    }

    /**
     *
     * @param array<int|string, mixed|mixed[]> $data Either key=>value array for single or array of arrays for bulk.
     * @return int|int[]|null|mixed Can return a single row id, array of row ids, null (for failed) or any other value short circuited from event.
     */
    public function insertIgnore($data)
    {
        return $this->doInsert($data, 'insertignore');
    }

    /**
     *
     * @param array<int|string, mixed|mixed[]> $data Either key=>value array for single or array of arrays for bulk.
     * @return int|int[]|null|mixed Can return a single row id, array of row ids, null (for failed) or any other value short circuited from event.
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

        $queryObject = $this->getQuery('update', $data);
        list($preparedQuery, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());

        $this->dbInstance()->get_results($preparedQuery);
        $this->fireEvents('after-update', $queryObject, $executionTime);

        return $this->dbInstance()->rows_affected !== 0
            ? $this->dbInstance()->rows_affected
            : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return int|null Will return row id for insert and bool for success/fail on update.
     */
    public function updateOrInsert($data)
    {
        if ($this->first()) {
            return $this->update($data);
        } else {
            return $this->insert($data);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return static
     */
    public function onDuplicateKeyUpdate($data)
    {
        $this->addStatement('onduplicate', $data);
        return $this;
    }

    /**
     *
     */
    public function delete()
    {
        $eventResult = $this->fireEvents('before-delete');
        if (!is_null($eventResult)) {
            return $eventResult;
        }

        $queryObject = $this->getQuery('delete');

        list($preparedQuery, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        $response = $this->dbInstance()->get_results($preparedQuery);
        $this->fireEvents('after-delete', $queryObject, $executionTime);

        return $response;
    }

    /**
     * @param string|Raw ...$tables Single table or array of tables
     *
     * @return static
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

        $fields = $this->addTablePrefix($fields);
        $this->addStatement('selects', $fields);
        return $this;
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
     * @param string|string[] $field Either the single field or an array of fields.
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
     * @param string|array<string|Raw, mixed> $fields
     * @param string          $defaultDirection
     *
     * @return static
     */
    public function orderBy($fields, string $defaultDirection = 'ASC'): self
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        foreach ($fields as $key => $value) {
            $field = $key;
            $type = $value;
            if (is_int($key)) {
                $field = $value;
                $type = $defaultDirection;
            }
            if (!$field instanceof Raw) {
                $field = $this->addTablePrefix($field);
            }
            $this->statements['orderBys'][] = compact('field', 'type');
        }

        return $this;
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
        $key = $this->addTablePrefix($key);
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
     * @param string|null|mixed $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function where($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * @param string|Raw $key
     * @param string|null|mixed $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function orWhere($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR');
    }

    /**
     * @param string|Raw $key
     * @param string|null|mixed $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function whereNot($key, $operator = null, $value = null): self
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->whereHandler($key, $operator, $value, 'AND NOT');
    }

    /**
     * @param string|Raw $key
     * @param string|null|mixed $operator Can be used as value, if 3rd arg not passed
     * @param mixed|null $value
     *
     * @return static
     */
    public function orWhereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
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
        return $this->whereHandler($key, 'BETWEEN', array($valueFrom, $valueTo), 'AND');
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
        return $this->whereHandler($key, 'BETWEEN', array($valueFrom, $valueTo), 'OR');
    }

    /**
     * @param string|Raw $key
     * @return static
     */
    public function whereNull($key): self
    {
        return $this->whereNullHandler($key);
    }

    /**
     * @param string|Raw $key
     * @return static
     */
    public function whereNotNull($key): self
    {
        return $this->whereNullHandler($key, 'NOT');
    }

    /**
     * @param string|Raw $key
     * @return static
     */
    public function orWhereNull($key): self
    {
        return $this->whereNullHandler($key, '', 'or');
    }

    /**
     * @param string|Raw $key
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
     * @return static
     */
    protected function whereNullHandler($key, string $prefix = '', $operator = ''): self
    {
        $prefix = \strlen($prefix) === 0 ? '' : " {$prefix}";

        $key = $this->adapterInstance->wrapSanitizer($this->addTablePrefix($key));
        return $this->{$operator . 'Where'}($this->raw("{$key} IS{$prefix} NULL"));
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param mixed $value
     * @param string $type
     *
     * @return static
     */
    public function join($table, $key, ?string $operator = null, $value = null, $type = 'inner')
    {
        if (!$key instanceof \Closure) {
            $key = function ($joinBuilder) use ($key, $operator, $value) {
                $joinBuilder->on($key, $operator, $value);
            };
        }

        // Build a new JoinBuilder class, keep it by reference so any changes made
        // in the closure should reflect here
        $joinBuilder = $this->container->build(JoinBuilder::class, array($this->connection));
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
     * @return static
     */
    public function transaction(\Closure $callback): self
    {
        try {
            // Begin the transaction
            $this->dbInstance->query('START TRANSACTION');

            // Get the Transaction class
            $transaction = $this->container->build(Transaction::class, array($this->connection));

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
     * @param \Closure    $callback
     * @param Transaction $transaction
     * @return void
     * @throws Exception
     */
    protected function handleTransactionCall(\Closure $callback, Transaction $transaction): void
    {
        try {
            ob_start();
            $callback($transaction);
            $output = ob_get_clean();
        } catch (\Throwable $th) {
            ob_end_clean();
            throw $th;
        }

        // If we caught an error, throw an exception.
        if (\mb_strlen($output) !== 0) {
            throw new Exception($output);
        }
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|\Closure $key
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
     * @param string|Raw|\Closure $key
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
     * @param string|Raw|\Closure $key
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
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param mixed $value
     * @return static
     */
    public function crossJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'cross');
    }

    /**
     * @param string|Raw $table
     * @param string|Raw|\Closure $key
     * @param string|null $operator
     * @param mixed $value
     * @return static
     */
    public function outerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'outer');
    }

    /**
     * Add a raw query
     *
     * @param string|Raw $value
     * @param mixed|mixed[] $bindings
     *
     * @return Raw
     */
    public function raw($value, $bindings = array()): Raw
    {
        return new Raw($value, $bindings);
    }

    /**
     * Return wpdb instance
     *
     * @return \wpdb
     */
    public function dbInstance(): \wpdb
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
     * @param string|Raw|\Closure $key
     * @param null|string      $operator
     * @param null|mixed       $value
     * @param string $joiner
     * 
     * @return static
     */
    protected function whereHandler($key, $operator = null, $value = null, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $this->statements['wheres'][] = compact('key', 'operator', 'value', 'joiner');
        return $this;
    }

    /**
     * Add table prefix (if given) on given string.
     *
     * @param array<string|int, string|int|float|bool|Raw|\Closure>|string|int|float|bool|Raw|\Closure     $values
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
            $values = array($values);
            // We had single value, so should return a single value
            $single = true;
        }

        $return = array();

        foreach ($values as $key => $value) {
            // It's a raw query, just add it to our return array and continue next
            if ($value instanceof Raw || $value instanceof \Closure) {
                $return[$key] = $value;
                continue;
            }

            // If key is not integer, it is likely a alias mapping,
            // so we need to change prefix target
            $target = &$value;
            if (!is_int($key)) {
                $target = &$key;
            }

            if (!$tableFieldMix || strpos($target, '.') !== false) {
                $target = $this->tablePrefix . $target;
            }

            $return[$key] = $value;
        }

        // If we had single value then we should return a single value (end value of the array)
        return true === $single ? end($return) : $return;
    }

    /**
     * @param string|Raw|\Closure $key
     * @param mixed|mixed[] $value
     */
    protected function addStatement($key, $value)
    {
        if (!is_array($value)) {
            $value = array($value);
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
     * @param \Closure $action
     *
     * @return void
     */
    public function registerEvent($event, $table, \Closure $action): void
    {
        $table = $table ?: ':any';

        if ($table != ':any') {
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
        if ($table != ':any') {
            $table = $this->addTablePrefix($table, false);
        }

        $this->connection->getEventHandler()->removeEvent($event, $table);
    }

    /**
     * @param string $event
     * @return mixed
     */
    public function fireEvents(string $event)
    {
        $params = func_get_args(); // @todo Replace this with an easier to read alteratnive
        array_unshift($params, $this);
        return call_user_func_array(array($this->connection->getEventHandler(), 'fireEvents'), $params);
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
}
