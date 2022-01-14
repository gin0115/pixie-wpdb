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

    public function __construct($sql, array $bindings, $dbInstance)
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
     * Replaces any parameter placeholders in a query with the value of that
     * parameter. Useful for debugging. Assumes anonymous parameters from
     * $params are are in the same order as specified in $query
     *
     * Reference: http://stackoverflow.com/a/1376838/656489
     *
     * @param string $query  The sql query with parameter placeholders
     * @param array  $params The array of substitution parameters
     *
     * @return string The interpolated query
     */
    protected function interpolateQuery($query, $params)
    {
        // Only call this when we have valid params (avoids wpdb::prepare() incorrectly called error)
        return empty($params) ? $query : $this->dbInstance->prepare($query, $params);
    }
}
