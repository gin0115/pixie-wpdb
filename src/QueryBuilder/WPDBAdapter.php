<?php

namespace Pixie\QueryBuilder;

use Closure;
use Pixie\Binding;
use Pixie\Exception;

use Pixie\Connection;

use Pixie\QueryBuilder\Raw;

use Pixie\Parser\StatementParser;

use Pixie\Statement\SelectStatement;
use Pixie\QueryBuilder\NestedCriteria;
use Pixie\Statement\StatementCollection;
use function is_bool;
use function is_float;

class WPDBAdapter
{
    /**
     * @var string
     */
    protected $sanitizer = '';

    /**
     * @var \Pixie\Connection
     */
    protected $connection;


    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /** This is a mock for the new parser based select method. */
    public function selectCol(StatementCollection $col, $data, $statements) // @phpstan-ignore-line
    {
        if (!array_key_exists('tables', $statements)) {
            throw new Exception('No table specified.', 3);
        } elseif (!array_key_exists('selects', $statements)) {
            $statements['selects'][] = '*';
        }

        $parser = new StatementParser($this->connection);
        if (!$col->hasSelect()) {
            $col->addSelect(new SelectStatement('*'));
        }
        $_selects = $parser->parseSelect($col->getSelect());
        $_tables = $parser->parseTable($col->getTable());


        // From
        // $tables = $this->arrayStr($statements['tables'], ', ');
        // // Select
        // $selects = $this->arrayStr($statements['selects'], ', ');

        // Wheres
        list($whereCriteria, $whereBindings) = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        // Group bys
        $groupBys = '';
        if (isset($statements['groupBys']) && $groupBys = $this->arrayStr($statements['groupBys'], ', ')) {
            $groupBys = 'GROUP BY ' . $groupBys;
        }


        // Limit and offset
        $limit  = isset($statements['limit']) ? 'LIMIT ' . (int) $statements['limit'] : '';
        $offset = isset($statements['offset']) ? 'OFFSET ' . (int) $statements['offset'] : '';

        // Having
        list($havingCriteria, $havingBindings) = $this->buildCriteriaWithType($statements, 'havings', 'HAVING');

        // Joins
        $joinString = $this->buildJoin($statements);

        /** @var string[] */
        $sqlArray = [
            'SELECT' . (isset($statements['distinct']) ? ' DISTINCT' : ''),
            $parser->parseSelect($col->getSelect()),
            'FROM',
            $parser->parseTable($col->getTable()),
            $joinString,
            $whereCriteria,
            $groupBys,
            $havingCriteria,
            $parser->parseOrderBy($col->getOrderBy()),
            $limit,
            $offset,
        ];

        $sql = $this->concatenateQuery($sqlArray);

        $bindings = array_merge(
            $whereBindings,
            $havingBindings
        );

        return compact('sql', 'bindings');
    }

    /**
     * Build select query string and bindings
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     *
     * @throws Exception
     *
     * @return array{sql:string,bindings:mixed[]}
     */
    public function select(array $statements): array
    {
        if (!array_key_exists('tables', $statements)) {
            throw new Exception('No table specified.', 3);
        } elseif (!array_key_exists('selects', $statements)) {
            $statements['selects'][] = '*';
        }

        // From
        $tables = $this->arrayStr($statements['tables'], ', ');
        // Select
        $selects = $this->arrayStr($statements['selects'], ', ');

        // Wheres
        list($whereCriteria, $whereBindings) = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        // Group bys
        $groupBys = '';
        if (isset($statements['groupBys']) && $groupBys = $this->arrayStr($statements['groupBys'], ', ')) {
            $groupBys = 'GROUP BY ' . $groupBys;
        }

        // Order bys
        $orderBys = '';
        if (isset($statements['orderBys']) && is_array($statements['orderBys'])) {
            foreach ($statements['orderBys'] as $orderBy) {
                $field = $this->wrapSanitizer($orderBy['field']);
                if ($field instanceof Closure) {
                    continue;
                }
                $orderBys .= $field . ' ' . $orderBy['type'] . ', ';
            }

            if ($orderBys = trim($orderBys, ', ')) {
                $orderBys = 'ORDER BY ' . $orderBys;
            }
        }

        // Limit and offset
        $limit  = isset($statements['limit']) ? 'LIMIT ' . (int) $statements['limit'] : '';
        $offset = isset($statements['offset']) ? 'OFFSET ' . (int) $statements['offset'] : '';

        // Having
        list($havingCriteria, $havingBindings) = $this->buildCriteriaWithType($statements, 'havings', 'HAVING');

        // Joins
        $joinString = $this->buildJoin($statements);

        /** @var string[] */
        $sqlArray = [
            'SELECT' . (isset($statements['distinct']) ? ' DISTINCT' : ''),
            $selects,
            'FROM',
            $tables,
            $joinString,
            $whereCriteria,
            $groupBys,
            $havingCriteria,
            $orderBys,
            $limit,
            $offset,
        ];

        $sql = $this->concatenateQuery($sqlArray);

        $bindings = array_merge(
            $whereBindings,
            $havingBindings
        );

        return compact('sql', 'bindings');
    }

    /**
     * Build just criteria part of the query
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param bool $bindValues
     *
     * @return array{sql:string[]|string, bindings:array<mixed>}
     */
    public function criteriaOnly(array $statements, bool $bindValues = true): array
    {
        $sql = $bindings = [];
        if (!isset($statements['criteria'])) {
            return compact('sql', 'bindings');
        }

        list($sql, $bindings) = $this->buildCriteria($statements['criteria'], $bindValues);

        return compact('sql', 'bindings');
    }

    /**
     * Build a generic insert/ignore/replace query
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param array<string, mixed> $data
     * @param string $type
     *
     * @return array{sql:string, bindings:mixed[]}
     *
     * @throws Exception
     */
    private function doInsert(array $statements, array $data, string $type): array
    {
        if (!isset($statements['tables'])) {
            throw new Exception('No table specified', 3);
        }

        $table = end($statements['tables']);

        $bindings = $keys = $values = [];

        foreach ($data as $key => $value) {
            $keys[] = $key;

            // Handle value as bindings
            $isBindings = $value instanceof Binding;
            // If this is a raw binding, extract the Raw and replace value.
            if ($isBindings && $value->isRaw()) {
                $value = $value->getValue();
            }

            if ($value instanceof Raw) {
                $values[] = $this->parseRaw($value);
            } elseif ($isBindings) {
                $values[]   =  $value->getType();
                $bindings[] = $value->getValue();
            } else {
                $values[]   =  $this->inferType($value);
                $bindings[] = $value;
            }
        }

        $sqlArray = [
        $type . ' INTO',
        $this->wrapSanitizer($table),
        '(' . $this->arrayStr($keys, ',') . ')',
        'VALUES',
        '(' . $this->arrayStr($values, ',') . ')',
        ];

        if (isset($statements['onduplicate'])) {
            if (count($statements['onduplicate']) < 1) {
                throw new Exception('No data given.', 4);
            }
            list($updateStatement, $updateBindings) = $this->getUpdateStatement($statements['onduplicate']);
            $sqlArray[]                             = 'ON DUPLICATE KEY UPDATE ' . $updateStatement;
            $bindings                               = array_merge($bindings, $updateBindings);
        }

        $sql = $this->concatenateQuery($this->stringifyValues($sqlArray));

        return compact('sql', 'bindings');
    }

    /**
     * Attempts to stringify an array of values.
     *
     * @param array<string|int, string|Closure> $values
     *
     * @return string[]
     */
    protected function stringifyValues(array $values): array
    {
        return array_filter(array_map([$this, 'stringifyValue'], $values));
    }

    /**
     * Attempts to stringify a single of values.
     *
     * @param string|Closure|Raw $value
     *
     * @return string|null
     */
    protected function stringifyValue($value): ?string
    {
        if ($value instanceof Closure) {
            $value = $value();

            return is_string($value) ? $value : null;
        }

        if ($value instanceof Raw) {
            return $this->parseRaw($value);
        }

        return $value;
    }

    /**
     * Build Insert query
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param array<string, mixed> $data $data
     *
     * @return array{sql:string, bindings:mixed[]}
     *
     * @throws Exception
     */
    public function insert($statements, array $data)
    {
        return $this->doInsert($statements, $data, 'INSERT');
    }

    /**
     * Build Insert Ignore query
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param array<string, mixed> $data $data
     *
     * @return array{sql:string, bindings:mixed[]}
     *
     * @throws Exception
     */
    public function insertIgnore($statements, array $data)
    {
        return $this->doInsert($statements, $data, 'INSERT IGNORE');
    }

    /**
     * Build Insert Ignore query
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param array<string, mixed> $data $data
     *
     * @return array{sql:string, bindings:mixed[]}
     *
     * @throws Exception
     */
    public function replace($statements, array $data)
    {
        return $this->doInsert($statements, $data, 'REPLACE');
    }

    /**
     * Build fields assignment part of SET ... or ON DUBLICATE KEY UPDATE ... statements
     *
     * @param array<string, mixed> $data
     *
     * @return array{0:string,1:mixed[]}
     */
    private function getUpdateStatement(array $data): array
    {
        $bindings  = [];
        $statement = '';

        foreach ($data as $key => $value) {
            $isBindings = $value instanceof Binding;
            // If this is a raw binding, extract the Raw and replace value.
            if ($isBindings && $value->isRaw()) {
                $value = $value->getValue();
            }

            if ($value instanceof Raw) {
                $statement .= $this->stringifyValue($this->wrapSanitizer($key)) . '=' . $value . ',';
            } elseif ($isBindings) {
                $statement .= $this->stringifyValue($this->wrapSanitizer($key)) . sprintf('=%s,', $value->getType());
                $bindings[] = $value->getValue();
            } else {
                $statement .= $this->stringifyValue($this->wrapSanitizer($key)) . sprintf('=%s,', $this->inferType($value));
                $bindings[] = $value;
            }
        }

        $statement = trim($statement, ',');

        return [$statement, $bindings];
    }

    /**
     * Build update query
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param array<string, mixed> $data
     *
     * @return array{sql:string, bindings:mixed[]}
     *
     * @throws Exception
     */
    public function update($statements, array $data)
    {
        if (!isset($statements['tables'])) {
            throw new Exception('No table specified', 3);
        } elseif (count($data) < 1) {
            throw new Exception('No data given.', 4);
        }

        $table = end($statements['tables']);

        // Update statement
        list($updateStatement, $bindings) = $this->getUpdateStatement($data);

        // Wheres
        list($whereCriteria, $whereBindings) = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        // Limit
        $limit = isset($statements['limit']) ? 'LIMIT ' . $statements['limit'] : '';

        $sqlArray = [
            'UPDATE',
            $this->wrapSanitizer($table),
            'SET ' . $updateStatement,
            $whereCriteria,
            $limit,
        ];

        $sql = $this->concatenateQuery($this->stringifyValues($sqlArray));

        $bindings = array_merge($bindings, $whereBindings);

        return compact('sql', 'bindings');
    }

    /**
     * Build delete query
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     *
     * @return array{sql:string, bindings:mixed[]}
     *
     * @throws Exception
     */
    public function delete($statements)
    {
        if (!isset($statements['tables'])) {
            throw new Exception('No table specified', 3);
        }

        $table = end($statements['tables']);
        // Ensure table name is a string
        $table = $this->stringifyValue($this->wrapSanitizer($table));
        if (null === $table) {
            throw new Exception('Table must be a valid string.', 5);
        }

        // Wheres
        list($whereCriteria, $whereBindings) = $this->buildCriteriaWithType($statements, 'wheres', 'WHERE');

        // Limit
        $limit = isset($statements['limit']) ? 'LIMIT ' . $statements['limit'] : '';

        $sqlArray = ['DELETE FROM', $table, $whereCriteria];
        $sql      = $this->concatenateQuery($sqlArray);
        $bindings = $whereBindings;

        return compact('sql', 'bindings');
    }

    /**
     * Array concatenating method, like implode.
     * But it does wrap sanitizer and trims last glue
     *
     * @param array<string|int, string> $pieces
     * @param string $glue
     *
     * @return string
     */
    protected function arrayStr(array $pieces, string $glue): string
    {
        $str = '';
        foreach ($pieces as $key => $piece) {
            if (!is_int($key)) {
                $piece = $key . ' AS ' . $piece;
            }

            $str .= $piece . $glue;
        }

        return trim($str, $glue);
    }

    /**
     * Join different part of queries with a space.
     *
     * @param array<string|int, string> $pieces
     *
     * @return string
     */
    protected function concatenateQuery(array $pieces): string
    {
        $str = '';
        foreach ($pieces as $piece) {
            $str = trim($str) . ' ' . trim($piece);
        }

        return trim($str);
    }

    /**
     * Gets the type of a value, either from a binding or infered
     *
     * @param mixed $value
     * @return string
     */
    public function getType($value): string
    {
        return $value instanceof Binding && $value->getType() !== null
            ? $value->getType() : $this->inferType($value) ;
    }

    /**
     * Get the value from a possible Bindings object.
     *
     * @param mixed $value
     * @return mixed
     */
    public function getValue($value)
    {
        return $value instanceof Binding ? $value->getValue() : $value;
    }

    /**
     * Attempts to parse a raw query, if bindings are defined then they will be bound first.
     *
     * @param Raw $raw
     * @requires string
     */
    public function parseRaw(Raw $raw): string
    {
        $bindings = $raw->getBindings();
        return 0 === count($bindings)
            ? (string) $raw
            : $this->interpolateQuery($raw->getValue(), $bindings);
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
        if (0 === count($bindings)) {
            return $query;
        }


        $bindings = array_map([$this, 'getValue'], $bindings);
        $query = $this->connection->getDbInstance()->prepare($query, $bindings) ;
        return is_string($query) ? $query : '';
    }

    /**
     * Build generic criteria string and bindings from statements, like "a = b and c = ?"
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param bool $bindValues
     *
     * @return array{0:string,1:string[]}
     */
    protected function buildCriteria(array $statements, bool $bindValues = true): array
    {
        $criteria = '';
        $bindings = [];
        foreach ($statements as $statement) {
            $key   = $statement['key'];
            $value = $statement['value'];

            // If the value is a Raw Binding, cast to raw
            if ($value instanceof Binding && Binding::RAW === $value->getType()) {
                /** @var Raw */
                $value = $value->getValue();
            }

            if (is_null($value) && $key instanceof Closure) {
                // We have a closure, a nested criteria

                // Build a new NestedCriteria class, keep it by reference so any changes made
                // in the closure should reflect here
                $nestedCriteria = new NestedCriteria($this->connection);

                $nestedCriteria = &$nestedCriteria;
                // Call the closure with our new nestedCriteria object
                $key($nestedCriteria);
                // Get the criteria only query from the nestedCriteria object
                $queryObject = $nestedCriteria->getQuery('criteriaOnly', true);
                // Merge the bindings we get from nestedCriteria object
                $bindings = array_merge($bindings, $queryObject->getBindings());
                // Append the sql we get from the nestedCriteria object
                $criteria .= $statement['joiner'] . ' (' . $queryObject->getSql() . ') ';
            } elseif (is_array($value)) {
                // where_in or between like query
                $criteria .= $statement['joiner'] . ' ' . $key . ' ' . $statement['operator'];

                switch ($statement['operator']) {
                    case 'BETWEEN':
                        $bindings = array_merge($bindings, $statement['value']);
                        $criteria .= sprintf(
                            ' %s AND %s ',
                            $this->getType($value[0]),
                            $this->getType($value[1])
                        );

                        // Maybe cast the values bindings.
                        $value[0] = $this->getValue($value[0]);
                        $value[1] = $this->getValue($value[1]);

                        // Parse any raws.
                        $value = array_map(function ($value) {
                            return $value instanceof Raw
                                ? $this->parseRaw($value)
                                : $value;
                        }, $value);
                        break;
                    default:
                        $valuePlaceholder = '';
                        foreach ($statement['value'] as $subValue) {
                            // Get its value.
                            if ($this->getValue($subValue) instanceof Raw) {
                                /** @var Raw $subValue */
                                $subValue = $this->getValue($subValue);
                                $valuePlaceholder .= sprintf('%s, ', $this->parseRaw($subValue));
                                continue;
                            }


                            // Add in format placeholders.
                            $valuePlaceholder .= sprintf('%s, ', $this->getType($subValue)); // glynn
                            $bindings[] = $this->getValue($subValue);
                        }

                        $valuePlaceholder = trim($valuePlaceholder, ', ');
                        $criteria .= ' (' . $valuePlaceholder . ') ';
                        break;
                }
            } elseif ($value instanceof Raw) {
                $value = $this->parseRaw($value);
                $criteria .= "{$statement['joiner']} {$key} {$statement['operator']} $value ";
            } else {
                // Usual where like criteria
                if (!$bindValues) {
                    // Specially for joins
                    // We are not binding values, lets sanitize then
                    $value = $this->stringifyValue($this->wrapSanitizer($value)) ?? '';
                    $criteria .= $statement['joiner'] . ' ' . $key . ' ' . $statement['operator'] . ' ' . $value . ' ';
                } elseif ($statement['key'] instanceof Raw) {
                    $criteria .= $statement['joiner'] . ' ' . $key . ' ';
                    $bindings = array_merge($bindings, $statement['key']->getBindings());
                } else {
                    // For wheres
                    $bindings[] = $this->getValue($value);

                    $criteria .= $statement['joiner'] . ' ' . $key . ' ' . $statement['operator'] . ' '
                    . $this->getType($value) . ' ';
                }
            }
        }

        // Clear all white spaces, and, or from beginning and white spaces from ending
        $criteria = preg_replace('/^(\s?AND ?|\s?OR ?)|\s$/i', '', $criteria);

        return [$criteria ?? '', $bindings];
    }

    /**
     * Asserts the types place holder based on its value
     *
     * @param mixed $value
     *
     * @return string
     */
    public function inferType($value): string
    {
        switch (true) {
            case is_string($value):
                return '%s';
            case \is_int($value):
            case is_bool($value):
                return '%d';
            case is_float($value):
                return '%f';
            default:
                return '';
        }
    }

    /**
     * Wrap values with adapter's sanitizer like, '`'
     *
     * @param string|Raw|Closure $value
     *
     * @return string|Closure
     */
    public function wrapSanitizer($value)
    {
        // Its a raw query, just cast as string, object has __toString()
        if ($value instanceof Raw) {
            return $this->parseRaw($value);
        } elseif ($value instanceof Closure) {
            return $value;
        }

        // Separate our table and fields which are joined with a ".",
        // like my_table.id
        $valueArr = explode('.', $value, 2);

        foreach ($valueArr as $key => $subValue) {
            // Don't wrap if we have *, which is not a usual field
            $valueArr[$key] = '*' == trim($subValue) ? $subValue : $this->sanitizer . $subValue . $this->sanitizer;
        }

        // Join these back with "." and return
        return implode('.', $valueArr);
    }

    /**
     * Build criteria string and binding with various types added, like WHERE and Having
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     * @param string $key
     * @param string $type
     * @param bool $bindValues
     *
     * @return array{0:string, 1:string[]}
     */
    protected function buildCriteriaWithType(array $statements, string $key, string $type, bool $bindValues = true)
    {
        $criteria = '';
        $bindings = [];

        if (isset($statements[$key])) {
            // Get the generic/adapter agnostic criteria string from parent
            list($criteria, $bindings) = $this->buildCriteria($statements[$key], $bindValues);

            if ($criteria) {
                $criteria = $type . ' ' . $criteria;
            }
        }

        // Remove any multiple whitespace.
        $criteria = (string) preg_replace('!\s+!', ' ', $criteria);

        return [$criteria, $bindings];
    }

    /**
     * Build join string
     *
     * @param array<string|Closure, mixed|mixed[]> $statements
     *
     * @return string
     */
    protected function buildJoin(array $statements): string
    {
        $sql = '';

        if (!array_key_exists('joins', $statements) || !is_array($statements['joins'])) {
            return $sql;
        }

        foreach ($statements['joins'] as $joinArr) {
            if (is_array($joinArr['table'])) {
                $mainTable  = $this->stringifyValue($this->wrapSanitizer($joinArr['table'][0]));
                $aliasTable = $this->stringifyValue($this->wrapSanitizer($joinArr['table'][1]));
                $table      = $mainTable . ' AS ' . $aliasTable;
            } else {
                $table = $joinArr['table'] instanceof Raw
                    ? $this->parseRaw($joinArr['table'])
                    : $this->wrapSanitizer($joinArr['table']);
            }
            $joinBuilder = $joinArr['joinBuilder'];

            /** @var string[] */
            $sqlArr = [
                $sql,
                strtoupper($joinArr['type']),
                'JOIN',
                $table,
                'ON',
                $joinBuilder->getQuery('criteriaOnly', false)->getSql(),
            ];

            $sql = $this->concatenateQuery($sqlArr);
        }

        return $sql;
    }
}
