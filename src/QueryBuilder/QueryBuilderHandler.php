<?php

namespace Pixie\QueryBuilder;

use wpdb;
use Closure;
use Throwable;
use Pixie\Binding;
use Pixie\Exception;
use Pixie\Connection;
use function mb_strlen;
use Pixie\HasConnection;
use Pixie\JSON\JsonHandler;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Hydration\Hydrator;
use Pixie\Statement\JoinStatement;
use Pixie\QueryBuilder\JoinBuilder;
use Pixie\QueryBuilder\QueryObject;
use Pixie\QueryBuilder\Transaction;
use Pixie\QueryBuilder\WPDBAdapter;
use Pixie\Statement\TableStatement;
use Pixie\Statement\WhereStatement;
use Pixie\Statement\HavingStatement;
use Pixie\Statement\InsertStatement;
use Pixie\Statement\SelectStatement;
use Pixie\QueryBuilder\TablePrefixer;
use Pixie\Statement\GroupByStatement;
use Pixie\Statement\OrderByStatement;
use Pixie\Statement\StatementBuilder;
use Pixie\Exception\StatementBuilderException;

class QueryBuilderHandler implements HasConnection
{
    /**
     * @method add
     */
    use TablePrefixer;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array<string, mixed[]|mixed>
     */
    protected $statements = array();

    /** @var StatementBuilder */
    protected $statementBuilder;

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
     * Handler for Json Selectors
     *
     * @var JsonHandler
     */
    protected $jsonHandler;

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
        $this->dbInstance = $this->connection->getDbInstance();
        $this->setAdapterConfig($this->connection->getAdapterConfig());

        // Set up optional hydration details.
        $this->setFetchMode($fetchMode);
        $this->hydratorConstructorArgs = $hydratorConstructorArgs;

        // Query builder adapter instance
        $this->adapterInstance = new WPDBAdapter($this->connection);

        // Setup JSON Selector handler.
        $this->jsonHandler = new JsonHandler($connection);

        // Setup statement collection.
        $this->statementBuilder = new StatementBuilder();
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
        if (isset($adapterConfig[ Connection::PREFIX ])) {
            $this->tablePrefix = $adapterConfig[ Connection::PREFIX ];
        }
    }

    /**
     * Fetch query results as object of specified type
     *
     * @param string $className
     * @param array<int, mixed> $constructorArgs
     * @return static
     */
    public function asObject($className, $constructorArgs = array()): self
    {
        return $this->setFetchMode($className, $constructorArgs);
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
    public function interpolateQuery(string $query, array $bindings = array()): string
    {
        return $this->adapterInstance->interpolateQuery($query, $bindings);
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
        $start        = microtime(true);
        $sqlStatement = empty($bindings) ? $sql : $this->interpolateQuery($sql, $bindings);

        if (! is_string($sqlStatement)) {
            throw new Exception('Could not interpolate query', 1);
        }

        return array( $sqlStatement, microtime(true) - $start );
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
        if (! is_null($eventResult)) {
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
        $executionTime     += microtime(true) - $start;
        $this->sqlStatement = null;

        // Ensure we have an array of results.
        if (! is_array($result) && null !== $result) {
            $result = array( $result );
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
        $hydrator = new Hydrator($this->getFetchMode(), $this->hydratorConstructorArgs ?? array()); /* @phpstan-ignore-line */

        return $hydrator;
    }

    /**
     * Checks if the results should be mapped via the hydrator
     *
     * @return bool
     */
    protected function useHydrator(): bool
    {
        return ! in_array($this->getFetchMode(), array( \ARRAY_A, \ARRAY_N, \OBJECT, \OBJECT_K ));
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
     * Allows the handling of a basic if/else with conditional access.
     *
     * @param bool $condition
     * @param \Closure $if
     * @param \Closure|null $else
     * @return self
     */
    public function when(bool $condition, \Closure $if, ?\Closure $else = null): self
    {
        // If the condition evaluates to true
        if (true === $condition) {
            $if($this);
            return $this;
        }

        // If false and we have a else closure
        if (null !== $else) {
            $else($this);
        }
        return $this;
    }

    /**
     * Used to handle all aggregation method.
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string $type
     * @param string|Raw $field
     *
     * @return float
     */
    protected function aggregate(string $type, $field = '*'): float
    {
        // Parse a raw expression.
        if ($field instanceof Raw) {
            $field = $this->adapterInstance->parseRaw($field);
        }

        // Potentialy cast field from JSON
        if ($this->jsonHandler->isJsonSelector($field)) {
            $field = $this->jsonHandler->extractAndUnquoteFromJsonSelector($field);
        }

        // Verify that field exists
        if ('*' !== $field && true === isset($this->statements['selects']) && false === \in_array($field, $this->statements['selects'], true)) {
            throw new \Exception(sprintf('Failed %s query - the column %s hasn\'t been selected in the query.', $type, $field));
        }

        if (false === isset($this->statements['tables'])) {
            throw new Exception('No table selected');
        }

        $count = $this
            ->table($this->subQuery($this, 'count'))
            ->select(array( $this->raw(sprintf('%s(%s) AS field', strtoupper($type), $field)) ))
            ->first();

        return true === isset($count->field) ? (float) $count->field : 0;
    }

    /**
     * Get count of all the rows for the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string|Raw $field
     *
     * @return int
     *
     * @throws Exception
     */
    public function count($field = '*'): int
    {
        return (int) $this->aggregate('count', $field);
    }

    /**
     * Get the sum for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string|Raw $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function sum($field): float
    {
        return $this->aggregate('sum', $field);
    }

    /**
     * Get the average for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string|Raw $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function average($field): float
    {
        return $this->aggregate('avg', $field);
    }

    /**
     * Get the minimum for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string|Raw $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function min($field): float
    {
        return $this->aggregate('min', $field);
    }

    /**
     * Get the maximum for a field in the current query
     *
     * @see Taken from the pecee-pixie library - https://github.com/skipperbent/pecee-pixie/
     *
     * @param string|Raw $field
     *
     * @return float
     *
     * @throws Exception
     */
    public function max($field): float
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
    public function getQuery(string $type = 'select', $dataToBePassed = array())
    {
        $allowedTypes = array( 'select', 'insert', 'insertignore', 'replace', 'delete', 'update', 'criteriaonly' );
        if (! in_array(strtolower($type), $allowedTypes)) {
            throw new Exception($type . ' is not a known type.', 2);
        }

        if ('select' === $type) {
            $queryArr = $this->adapterInstance->selectCol($this->statementBuilder, array(), $this->statements);
        } else {
            $queryArr = $this->adapterInstance->$type($this->statements, $dataToBePassed);
        }

        return new QueryObject($queryArr['sql'], $queryArr['bindings'], $this->dbInstance);
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
        if (! is_null($eventResult)) {
            return $eventResult;
        }

        $statement = new InsertStatement($data, $type);
        $this->statementBuilder->addStatement($statement);
        $q = $this->adapterInstance->doInsertB($this->statementBuilder);
        dump($q);

        // If first value is not an array () not a batch insert)
        if (! is_array(current($data))) {
            $queryObject = $this->getQuery($type, $data);

            list($preparedQuery, $executionTime) = $this->statement($queryObject->getSql(), $queryObject->getBindings());
            $this->dbInstance->get_results($preparedQuery);

            // Check we have a result.
            $return = 1 === $this->dbInstance->rows_affected ? $this->dbInstance->insert_id : null;
        } else {
            // Its a batch insert
            $return        = array();
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
     * @return int|null Number of row effected, null for none.
     */
    public function update(array $data): ?int
    {
        $eventResult = $this->fireEvents('before-update');
        if (! is_null($eventResult)) {
            return $eventResult;
        }
        $queryObject                         = $this->getQuery('update', $data);
        $r                                   = $this->statement($queryObject->getSql(), $queryObject->getBindings());
        list($preparedQuery, $executionTime) = $r;
        $this->dbInstance()->get_results($preparedQuery);
        $this->fireEvents('after-update', $queryObject, $executionTime);

        return 0 !== (int) $this->dbInstance()->rows_affected
            ? (int) $this->dbInstance()->rows_affected
            : null;
    }

    /**
     * Update or Insert based on the attributes.
     *
     * @param array<string, mixed> $attributes Conditions to check
     * @param array<string, mixed> $values     Values to add/update
     *
     * @return int|int[]|null will return row id(s) for insert and null for success/fail on update
     */
    public function updateOrInsert(array $attributes, array $values = array())
    {
        // Check if existing post exists.
        $query = clone $this;
        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        // If we have a result, update it.
        if (null !== $query->first()) {
            foreach ($attributes as $column => $value) {
                $this->where($column, $value);
            }

            return $this->update($values);
        }

        // Else insert
        return $this->insert($values);
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
     * @return mixed number of rows effected or shortcircuited response
     */
    public function delete()
    {
        $eventResult = $this->fireEvents('before-delete');
        if (! is_null($eventResult)) {
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
    public function table(...$tables)
    {
        $instance = $this->constructCurrentBuilderClass($this->connection);
        $instance->setFetchMode($this->getFetchMode(), $this->hydratorConstructorArgs);

        foreach ($tables as $table) {
            $instance->getStatementBuilder()->addTable(new TableStatement($table));
        }

        /** REMOVE BELOW HERE IN V0.2 */
        $tables = $this->addTablePrefix($tables, false);
        $instance->addStatement('tables', $tables);
        /** REMOVE ABOVE HERE IN V0.2 */

        return $instance;
    }

    /**
     * @param string|Raw ...$tables Single table or array of tables
     *
     * @return static
     */
    public function from(...$tables): self
    {
        foreach ($tables as $table) {
            $this->statementBuilder->addTable(new TableStatement($table));
        }
        $tables = $this->addTablePrefix($tables, false);
        $this->addStatement('tables', $tables);

        return $this;
    }

    /**
     * Select which fields should be returned in the results.
     *
     * @param string|string[]|Raw[]|array<string, string> $fields
     * @return static
     */
    public function select($fields): self
    {

        if (!is_array($fields)) {
            $fields = func_get_args();
        }
        
        $fields2 = $this->maybeFlipArrayValues($fields);
        foreach ($fields2 as ['key' => $field, 'value' => $alias]) {
            // If no alias passed, but field is for JSON. thrown an exception.
            if (is_numeric($field) && is_string($alias) && $this->jsonHandler->isJsonSelector($alias)) {
                throw new Exception('An alias must be used if you wish to select from JSON Object', 1);
            }

            if (is_int($field)) {
                continue;
            }

            /** V0.2 */
            $statement =  ! is_string($alias)
                ? new SelectStatement($field)
                : new SelectStatement($field, $alias);
            $this->statementBuilder->addSelect($statement);
        }

        
return $this;

// OLD CODE
        foreach ($fields as $field => $alias) {
            // If no alias passed, but field is for JSON. thrown an exception.
            if (is_numeric($field) && is_string($alias) && $this->jsonHandler->isJsonSelector($alias)) {
                throw new Exception('An alias must be used if you wish to select from JSON Object', 1);
            }

            // /** V0.2 */
            // $statement = is_numeric($field)
            //     ? new SelectStatement($alias)
            //     : new SelectStatement($field, $alias);
            // $this->statementBuilder->addSelect(
            //     $statement/* ->setIsDistinct($isDistinct) */
            // );

            /**    REMOVE BELOW IN V0.2 */
            // If we have a JSON expression
            if ($this->jsonHandler->isJsonSelector($field)) {

                /** @var string $field */
                $field = $this->jsonHandler->extractAndUnquoteFromJsonSelector($field);
            }
            $field = $this->addTablePrefix($field);

            // Treat each array as a single table, to retain order added
            $field       = is_numeric($field)
                ? $field = $alias // If single colum
                : $field = array( $field => $alias ); // Has alias

            $this->addStatement('selects', $field);
            /**    REMOVE ABOVE IN V0.2 */
        }
        return $this;
    }

    /**
     * @param string|string[]|Raw[]|array<string, string> $fields
     *
     * @return static
     */
    public function selectDistinct($fields)
    {
        // $this->select($fields, true);
        // $this->addStatement('distinct', true);
        $this->statementBuilder->setDistinctSelect(true);
        $this->select(!is_array($fields) ? func_get_args() : $fields);
        return $this;
    }

    /**
     * @param string|string[] $field either the single field or an array of fields
     *
     * @return static
     */
    public function groupBy($field): self
    {
        $groupBys = is_array($field) ? $field : array( $field );
        foreach (array_filter($groupBys, 'is_string') as $groupBy) {
            $this->statementBuilder->addGroupBy(new GroupByStatement($groupBy));
        }

        /** REMOVE BELOW IN V0.2 */
        $field = $this->addTablePrefix($field);
        $this->addStatement('groupBys', $field);
        /** REMOVE ABOVE IN V0.2 */

        return $this;
    }

    /**
     * Will flip and array where the key should be an object.
     *
     * $columns = ['columnA' => 'aliasA', 'aliasB' => Raw::val('count(foo)'), 'noAlias'];
     * $flipped = array_map([$this, 'maybeFlipArrayValues'], $columns);
     * [
     *  ['key' => 'columnA', value => 'aliasA'],
     *  ['key' => Raw::val('count(foo)'), value => 'aliasB'],
     *  ['key' => 'noAlias', 'value'=> 2 ]
     * ]
     *
     * @param string|int $key
     * @param string|object|int $value
     * @return array{key:string|object|int,value:string|object|int}
     */
    public function _maybeFlipArrayValues($key, $value): array
    {
        return is_object($value) || is_int($key)
            ? array(
                'key'   => $value,
                'value' => $key,
            )
            : array(
                'key'   => $key,
                'value' => $value,
            );
    }

    /**
     * Will flip and array where the key should be an object.
     *
     * $columns = ['columnA' => 'aliasA', 'aliasB' => Raw::val('count(foo)'), 'noAlias'];
     * $flipped = array_map([$this, 'maybeFlipArrayValues'], $columns);
     * [
     *  ['key' => 'columnA', value => 'aliasA'],
     *  ['key' => Raw::val('count(foo)'), value => 'aliasB'],
     *  ['key' => 'noAlias', 'value'=> 0 ]
     * ]
     * @template K The key
     * @template V The value
     * @param array<K, V> $data
     * @return array<int, array{key:V,value:K}|array{key:K,value:V}>
     */
    public function maybeFlipArrayValues(array $data): array
    {
        return array_map(
            function ($key, $value): array {
                return is_object($value) || is_int($key)
                    ? array(
                        'key'   => $value,
                        'value' => $key,
                    )
                    : array(
                        'key'   => $key,
                        'value' => $value,
                    );
            },
            array_keys($data),
            array_values($data)
        );
    }

    /**
     * @param string|Raw|JsonSelector|array<string|int, string|Raw|JsonSelector> $fields
     * @param string          $defaultDirection
     *
     * @return static
     */
    public function orderBy($fields, string $defaultDirection = 'ASC'): self
    {
        if (! is_array($fields)) {
            $fields = array( $fields );
        }

        foreach (
            // Key = Column && Value = Direction
            $this->maybeFlipArrayValues($fields)
            as
                ['key'   => $column,
                'value' => $direction]

        ) {
            // To please static analysis due to limited generics
            if (is_int($column)) {
                continue;
            }
            $this->statementBuilder->addOrderBy(
                new OrderByStatement(
                    $column,
                    ! is_string($direction) ? $defaultDirection : (string) $direction
                )
            );
        }

        /** REMOVE BELOW HERE IN v0.2 */
        foreach ($fields as $key => $value) {
            $field = $key;
            $type  = $value;
            if (is_int($key)) {
                $field = $value;
                $type  = $defaultDirection;
            }

            if ($this->jsonHandler->isJsonSelector($field)) {
                $field = $this->jsonHandler->extractAndUnquoteFromJsonSelector($field); // @phpstan-ignore-line
            }

            if (! $field instanceof Raw) {
                $field = $this->addTablePrefix($field); // @phpstan-ignore-line
            }
            $this->statements['orderBys'][] = compact('field', 'type');
            /** REMOVE ABOVE HERE IN v0.2 */
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
        $key = $this->jsonHandler->jsonExpressionFactory()->extractAndUnquote($key, $jsonKey);
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
        $this->statementBuilder->setLimit($limit);
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
        $this->statementBuilder->setOffset($offset);
        return $this;
    }

    /**
     * @param string|Raw|\Closure(QueryBuilderHandler):void       $key
     * @param string $operator
     * @param mixed $value
     * @param string $joiner
     *
     * @return static
     */
    public function having($key, string $operator, $value, string $joiner = 'AND')
    {
        // If two params are given then assume operator is =
        if (2 === func_num_args()) {
            $value    = $operator;
            $operator = '=';
        }

        $this->statementBuilder->addHaving(
            new HavingStatement($key, $operator, $value, $joiner)
        );

        $key                           = $this->addTablePrefix($key);
        $this->statements['havings'][] = compact('key', 'operator', 'value', 'joiner');

        return $this;
    }

    /**
     * @param string|Raw|\Closure(QueryBuilderHandler):void       $key
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
     * @param string|Raw|\Closure(QueryBuilderHandler):void $key
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

        $this->statementBuilder->addWhere(
            new WhereStatement(
                is_string($key) && $this->jsonHandler->isJsonSelector($key) ? $this->jsonHandler->extractAndUnquoteFromJsonSelector($key) : $key,
                $operator,
                $value,
                'AND'
            )
        );

        return $this->whereHandler($key, $operator, $value);
    }

    /**
     * @param string|Raw|\Closure(QueryBuilderHandler):void $key
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

        $this->statementBuilder->addWhere(new WhereStatement($key, $operator, $value, 'OR'));
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

        $this->statementBuilder->addWhere(new WhereStatement($key, $operator, $value, 'AND NOT'));
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

        $this->statementBuilder->addWhere(new WhereStatement($key, $operator, $value, 'OR NOT'));
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
        $this->statementBuilder->addWhere(new WhereStatement($key, 'IN', $values, 'AND'));
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
        $this->statementBuilder->addWhere(new WhereStatement($key, 'NOT IN', $values, 'AND'));
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
        $this->statementBuilder->addWhere(new WhereStatement($key, 'IN', $values, 'OR'));
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
        $this->statementBuilder->addWhere(new WhereStatement($key, 'NOT IN', $values, 'OR'));
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
        $this->statementBuilder->addWhere(new WhereStatement($key, 'BETWEEN', array( $valueFrom, $valueTo ), 'AND'));
        return $this->whereHandler($key, 'BETWEEN', array( $valueFrom, $valueTo ), 'AND');
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
        $this->statementBuilder->addWhere(new WhereStatement($key, 'BETWEEN', array( $valueFrom, $valueTo ), 'OR'));
        return $this->whereHandler($key, 'BETWEEN', array( $valueFrom, $valueTo ), 'OR');
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

        $key = $this->addTablePrefix($key);
        if ($key instanceof Closure) {
            throw new Exception('Key used for whereNull condition must be a string or raw exrpession.', 1);
        }

        return $this->{$operator . 'Where'}($this->raw("{$key} IS{$prefix} NULL"));
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
            $transaction = new Transaction($this->connection);

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
     * Catches any WPDB Errors (printed)
     *
     * @param Closure    $callback
     * @param Transaction $transaction
     *
     * @return void
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

    /*************************************************************************/
    /*************************************************************************/
    /*************************************************************************/
    /**                              JOIN JOIN                              **/
    /**                                 JOIN                                **/
    /**                              JOIN JOIN                              **/
    /*************************************************************************/
    /*************************************************************************/
    /*************************************************************************/

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
        $table1 = $this->maybeFlipArrayValues(is_array($table) ? $table : [$table]);
        $this->statementBuilder->addStatement(
            new JoinStatement(end($table1) , $key, $operator, $value, $type)
        );
        
        // Potentially cast key from JSON
        if ($this->jsonHandler->isJsonSelector($key)) {
            /** @var string $key */
            $key = $this->jsonHandler->extractAndUnquoteFromJsonSelector($key); /** @phpstan-ignore-line */
        }

        // Potentially cast value from json
        if ($this->jsonHandler->isJsonSelector($value)) {
            /** @var string $value */
            $value = $this->jsonHandler->extractAndUnquoteFromJsonSelector($value);
        }

        if (! $key instanceof Closure) {
            $key = function (JoinBuilder $joinBuilder) use ($key, $operator, $value) {
                $joinBuilder->on($key, $operator, $value);
            };
        }

        // Build a new JoinBuilder class, keep it by reference so any changes made
        // in the closure should reflect here
        $joinBuilder = new JoinBuilder($this->connection);

        // Call the closure with our new joinBuilder object
        $key($joinBuilder);

        $table = $this->addTablePrefix($table, false);

        // Get the criteria only query from the joinBuilder object
        $this->statements['joins'][] = compact('type', 'table', 'joinBuilder');
        return $this;
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
        return $this->join($table, $key, $operator, $value, 'full outer');
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
        if (! array_key_exists('tables', $this->statements) || count($this->statements['tables']) !== 1) {
            throw new Exception('JoinUsing can only be used with a single table set as the base of the query', 1);
        }
        $baseTable = end($this->statements['tables']);

        // Potentialy cast key from JSON
        if ($this->jsonHandler->isJsonSelector($key)) {
            $key = $this->jsonHandler->extractAndUnquoteFromJsonSelector($key);
        }

        $remoteKey = $table = $this->addTablePrefix("{$table}.{$key}", true);
        $localKey  = $table = $this->addTablePrefix("{$baseTable}.{$key}", true);
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
    public function raw($value, $bindings = array()): Raw
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
    public function getConnection(): Connection
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

        if ($this->jsonHandler->isJsonSelector($key)) {
            $key = $this->jsonHandler->extractAndUnquoteFromJsonSelector($key);
        }

        $this->statements['wheres'][] = compact('key', 'operator', 'value', 'joiner');
        return $this;
    }



    /**
     * @param string $key
     * @param mixed|mixed[]|bool $value
     *
     * @return void
     */
    protected function addStatement($key, $value)
    {
        if (! is_array($value)) {
            $value = array( $value );
        }

        if (! array_key_exists($key, $this->statements)) {
            $this->statements[ $key ] = $value;
        } else {
            $this->statements[ $key ] = array_merge($this->statements[ $key ], $value);
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

        return call_user_func_array(array( $this->connection->getEventHandler(), 'fireEvents' ), $params);
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

    /**
     * Returns an NEW instance of the JSON builder populated with the same connection and hydrator details.
     *
     * @return JsonQueryBuilder
     */
    public function jsonBuilder(): JsonQueryBuilder
    {
        return new JsonQueryBuilder($this->getConnection(), $this->getFetchMode(), $this->hydratorConstructorArgs);
    }

    /**
     * Get the value of StatementBuilder
     * @return StatementBuilder
     */
    public function getStatementBuilder(): StatementBuilder
    {
        return $this->statementBuilder;
    }
}
