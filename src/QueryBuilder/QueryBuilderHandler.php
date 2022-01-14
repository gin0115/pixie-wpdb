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
     * @var array
     */
    protected $statements = array();

    /**
     * @var \wpdb
     */
    protected $dbInstance;

    /**
     * @var null|string[]
     */
    protected $sqlStatement = null;

    /**
     * @var null|string
     */
    protected $tablePrefix = null;

    /**
     * @var \Pixie\QueryBuilder\Adapters\BaseAdapter
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
    public function __construct(
        Connection $connection = null,
        string $fetchMode = \OBJECT,
        ?array $hydratorConstructorArgs = null
    ) {
        if (is_null($connection)) {
            // throws if connection not already established.
            $connection = Connection::getStoredConnection();
        }

        $this->connection = $connection;
        $this->container = $this->connection->getContainer();
        $this->dbInstance = $this->connection->getDbInstance();
        $this->adapter = 'wpdb';
        $this->adapterConfig = $this->connection->getAdapterConfig();

        if (isset($this->adapterConfig['prefix'])) {
            $this->tablePrefix = $this->adapterConfig['prefix'];
        }

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
     * Set the fetch mode
     *
     * @param string $mode
     * @param null|array<int, mixed> $constructorArgs
     * @return $this
     */
    public function setFetchMode(string $mode, ?array $constructorArgs = null): self
    {
        $this->fetchMode = $mode;
        $this->hydratorConstructorArgs = $constructorArgs;
        return $this;
    }

    /**
     * @param null|Connection $connection
     * @return QueryBuilderHandler
     * @throws Exception
     */
    public function newQuery(Connection $connection = null)
    {
        if (is_null($connection)) {
            $connection = $this->connection;
        }

        $new = new static($connection);
        $new->setFetchMode($this->getFetchMode(), $this->hydratorConstructorArgs);
        return $new;
    }

    /**
     * @param       $sql
     * @param array $bindings
     *
     * @return $this
     */
    public function query($sql, $bindings = array())
    {
        list($this->sqlStatement) = $this->statement($sql, $bindings);

        return $this;
    }

    /**
     * @param       $sql
     * @param array $bindings
     *
     * @return array sqlStatement and execution time as float
     */
    public function statement($sql, $bindings = array())
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
        return ! in_array($this->getFetchMode(), [\ARRAY_A, \ARRAY_N, \OBJECT, \OBJECT_K]);
    }

    /**
     * Get first row
     *
     * @return \stdClass|array|null
     */
    public function first()
    {
        $this->limit(1);
        $result = $this->get();
        return empty($result) ? null : $result[0];
    }

    /**
     * @param        $value
     * @param string $fieldName
     *
     * @return null|\stdClass
     */
    public function findAll($fieldName, $value)
    {
        $this->where($fieldName, '=', $value);
        return $this->get();
    }

    /**
     * @param        $value
     * @param string $fieldName
     *
     * @return null|\stdClass
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
     * @param null $alias
     *
     * @return Raw
     */
    public function subQuery(QueryBuilderHandler $queryBuilder, $alias = null)
    {
        $sql = '(' . $queryBuilder->getQuery()->getRawSql() . ')';
        if ($alias) {
            $sql = $sql . ' as ' . $alias;
        }

        return $queryBuilder->raw($sql);
    }

    /**
     * @param $data
     *
     * @return array|string
     */
    private function doInsert($data, $type)
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
     * @param $data
     *
     * @return array|string
     */
    public function insert($data)
    {
        return $this->doInsert($data, 'insert');
    }

    /**
     * @param $data
     *
     * @return array|string
     */
    public function insertIgnore($data)
    {
        return $this->doInsert($data, 'insertignore');
    }

    /**
     * @param $data
     *
     * @return array|string
     */
    public function replace($data)
    {
        return $this->doInsert($data, 'replace');
    }

    /**
     * @param $data
     *
     * @return bool
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
     * @param $data
     *
     * @return array|string
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
     * @param $data
     *
     * @return $this
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
     * @param string|array $tables Single table or array of tables
     *
     * @return QueryBuilderHandler
     * @throws Exception
     */
    public function table($tables)
    {
        if (!is_array($tables)) {
            // because a single table is converted to an array anyways,
            // this makes sense.
            $tables = func_get_args();
        }

        $instance = new static($this->connection);
        $this->setFetchMode($this->getFetchMode(), $this->hydratorConstructorArgs);
        $tables = $this->addTablePrefix($tables, false);
        $instance->addStatement('tables', $tables);
        return $instance;
    }

    /**
     * @param $tables
     *
     * @return $this
     */
    public function from($tables)
    {
        if (!is_array($tables)) {
            $tables = func_get_args();
        }

        $tables = $this->addTablePrefix($tables, false);
        $this->addStatement('tables', $tables);
        return $this;
    }

    /**
     * @param $fields
     *
     * @return $this
     */
    public function select($fields)
    {
        if (!is_array($fields)) {
            $fields = func_get_args();
        }

        $fields = $this->addTablePrefix($fields);
        $this->addStatement('selects', $fields);
        return $this;
    }

    /**
     * @param $fields
     *
     * @return $this
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
     * @return $this
     */
    public function groupBy($field)
    {
        $field = $this->addTablePrefix($field);
        $this->addStatement('groupBys', $field);
        return $this;
    }

    /**
     * @param string|string[] $fields
     * @param string          $defaultDirection
     *
     * @return $this
     */
    public function orderBy($fields, $defaultDirection = 'ASC')
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
     * @param $limit
     *
     * @return $this
     */
    public function limit($limit)
    {
        $this->statements['limit'] = $limit;
        return $this;
    }

    /**
     * @param $offset
     *
     * @return $this
     */
    public function offset($offset)
    {
        $this->statements['offset'] = $offset;
        return $this;
    }

    /**
     * @param        $key
     * @param        $operator
     * @param        $value
     * @param string $joiner
     *
     * @return $this
     */
    public function having($key, $operator, $value, $joiner = 'AND')
    {
        $key = $this->addTablePrefix($key);
        $this->statements['havings'][] = compact('key', 'operator', 'value', 'joiner');
        return $this;
    }

    /**
     * @param        $key
     * @param        $operator
     * @param        $value
     *
     * @return $this
     */
    public function orHaving($key, $operator, $value)
    {
        return $this->having($key, $operator, $value, 'OR');
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    public function where($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    public function orWhere($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereHandler($key, $operator, $value, 'OR');
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    public function whereNot($key, $operator = null, $value = null)
    {
        // If two params are given then assume operator is =
        if (func_num_args() == 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->whereHandler($key, $operator, $value, 'AND NOT');
    }

    /**
     * @param $key
     * @param $operator
     * @param $value
     *
     * @return $this
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
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function whereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values, 'AND');
    }

    /**
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function whereNotIn($key, $values)
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'AND');
    }

    /**
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function orWhereIn($key, $values)
    {
        return $this->whereHandler($key, 'IN', $values, 'OR');
    }

    /**
     * @param       $key
     * @param array $values
     *
     * @return $this
     */
    public function orWhereNotIn($key, $values)
    {
        return $this->whereHandler($key, 'NOT IN', $values, 'OR');
    }

    /**
     * @param $key
     * @param $valueFrom
     * @param $valueTo
     *
     * @return $this
     */
    public function whereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', array($valueFrom, $valueTo), 'AND');
    }

    /**
     * @param $key
     * @param $valueFrom
     * @param $valueTo
     *
     * @return $this
     */
    public function orWhereBetween($key, $valueFrom, $valueTo)
    {
        return $this->whereHandler($key, 'BETWEEN', array($valueFrom, $valueTo), 'OR');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function whereNull($key)
    {
        return $this->whereNullHandler($key);
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function whereNotNull($key)
    {
        return $this->whereNullHandler($key, 'NOT');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function orWhereNull($key)
    {
        return $this->whereNullHandler($key, '', 'or');
    }

    /**
     * @param $key
     * @return QueryBuilderHandler
     */
    public function orWhereNotNull($key)
    {
        return $this->whereNullHandler($key, 'NOT', 'or');
    }

    protected function whereNullHandler($key, string $prefix = '', $operator = '')
    {
        $prefix = \strlen($prefix) === 0 ? '' : " {$prefix}";

        $key = $this->adapterInstance->wrapSanitizer($this->addTablePrefix($key));
        return $this->{$operator . 'Where'}($this->raw("{$key} IS{$prefix} NULL"));
    }

    /**
     * @param        $table
     * @param        $key
     * @param        $operator
     * @param        $value
     * @param string $type
     *
     * @return $this
     */
    public function join($table, $key, $operator = null, $value = null, $type = 'inner')
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
     * @param $callback
     *
     * @return $this
     */
    public function transaction(\Closure $callback)
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
     * @param callable    $callback
     * @param Transaction $transaction
     * @return void
     */
    protected function handleTransactionCall(callable $callback, Transaction $transaction): void
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
     * @param      $table
     * @param      $key
     * @param null $operator
     * @param null $value
     *
     * @return $this
     */
    public function leftJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'left');
    }

    /**
     * @param      $table
     * @param      $key
     * @param null $operator
     * @param null $value
     *
     * @return $this
     */
    public function rightJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'right');
    }

    /**
     * @param      $table
     * @param      $key
     * @param null $operator
     * @param null $value
     *
     * @return $this
     */
    public function innerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'inner');
    }

    /**
     * @param string           $table
     * @param callable|string  $key
     * @param null|string      $operator
     * @param null|mixed       $value
     * @return this
     */
    public function crossJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'cross');
    }

    /**
     * @param string           $table
     * @param callable|string  $key
     * @param null|string      $operator
     * @param null|mixed       $value
     * @return this
     */
    public function outerJoin($table, $key, $operator = null, $value = null)
    {
        return $this->join($table, $key, $operator, $value, 'outer');
    }

    /**
     * Add a raw query
     *
     * @param $value
     * @param $bindings
     *
     * @return mixed
     */
    public function raw($value, $bindings = array())
    {
        return $this->container->build(Raw::class, array($value, $bindings));
    }

    /**
     * Return wpdb instance
     *
     * @return wpdb
     */
    public function dbInstance(): \wpdb
    {
        return $this->dbInstance;
    }

    /**
     * @param Connection $connection
     *
     * @return $this
     */
    public function setConnection(Connection $connection)
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
     * @param        $key
     * @param        $operator
     * @param        $value
     * @param string $joiner
     *
     * @return $this
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
     * @param string|string[]     $values
     * @param bool $tableFieldMix If we have mixes of field and table names with a "."
     *
     * @return array|mixed
     */
    public function addTablePrefix($values, $tableFieldMix = true)
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

            if (!$tableFieldMix || ($tableFieldMix && strpos($target, '.') !== false)) {
                $target = $this->tablePrefix . $target;
            }

            $return[$key] = $value;
        }

        // If we had single value then we should return a single value (end value of the array)
        return $single ? end($return) : $return;
    }

    /**
     * @param $key
     * @param $value
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
     * @param $event
     * @param $table
     *
     * @return callable|null
     */
    public function getEvent($event, $table = ':any')
    {
        return $this->connection->getEventHandler()->getEvent($event, $table);
    }

    /**
     * @param          $event
     * @param string $table
     * @param callable $action
     *
     * @return void
     */
    public function registerEvent($event, $table, \Closure $action)
    {
        $table = $table ?: ':any';

        if ($table != ':any') {
            $table = $this->addTablePrefix($table, false);
        }

        return $this->connection->getEventHandler()->registerEvent($event, $table, $action);
    }

    /**
     * @param          $event
     * @param string $table
     *
     * @return void
     */
    public function removeEvent($event, $table = ':any')
    {
        if ($table != ':any') {
            $table = $this->addTablePrefix($table, false);
        }

        return $this->connection->getEventHandler()->removeEvent($event, $table);
    }

    /**
     * @param      $event
     * @return mixed
     */
    public function fireEvents($event)
    {
        $params = func_get_args();
        array_unshift($params, $this);
        return call_user_func_array(array($this->connection->getEventHandler(), 'fireEvents'), $params);
    }

    /**
     * @return array
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
