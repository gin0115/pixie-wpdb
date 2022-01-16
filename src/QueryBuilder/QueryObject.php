<?php

namespace Pixie\QueryBuilder;

class QueryObject
{

    /**
     * @var string
     */
    protected $sql;

    /**
     * @var mixed[]
     */
    protected $bindings = array();

    /**
     * @var \wpdb
     */
    protected $dbInstance;

    /**
     * @param string $sql
     * @param mixed[] $bindings
     * @param \wpdb $dbInstance
     */
    public function __construct(string $sql, array $bindings, \wpdb $dbInstance)
    {
        $this->sql = (string)$sql;
        $this->bindings = $bindings;
        $this->dbInstance = $dbInstance;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * @return mixed[]
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Get the raw/bound sql
     *
     * @return string
     */
    public function getRawSql()
    {
        return $this->interpolateQuery($this->sql, $this->bindings);
    }

    /**
     * Uses WPDB::prepare() to interpolate the query passed.

     *
     * @param string $query  The sql query with parameter placeholders
     * @param mixed[]  $params The array of substitution parameters
     *
     * @return string The interpolated query
     */
    protected function interpolateQuery($query, $params): string
    {
        // Only call this when we have valid params (avoids wpdb::prepare() incorrectly called error)
        $value = empty($params) ? $query : $this->dbInstance->prepare($query, $params);
        return is_string($value) ? $value : '';
    }
}
