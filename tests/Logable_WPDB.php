<?php

declare(strict_types=1);

/**
 * An instance of WPDB where all queries are logged internally.
 *
 * @package PinkCrab\Test_Helpers
 * @author Glynn Quelch glynn@pinkcrab.co.uk
 * @since 0.0.1
 */

namespace Pixie\Tests;

class Logable_WPDB extends \wpdb
{

    /** @var array<string,mixed[]> */
    public $usage_log = array();

    /**
     * Sets the value to return from the next call.
     *
     * @var mixed
     */
    public $then_return = null;

    /**
     * Ignore the constructor!
     *
     * @param null $a
     * @param null $b
     * @param null $c
     * @param null $d
     */
    public function __construct($a = null, $b = null, $c = null, $d = null)
    {
    }

    /**
     * Logs any calls made to insert
     *
     * NATIVE RETURN >> The number of rows inserted, or false on error.
     *
     * @param string $table
     * @param array $data
     * @param array|string|null $format
     * @return mixed
     */
    public function insert($table, $data, $format = null)
    {
        $this->usage_log['insert'][ $table ][] = array(
            'data'   => $data,
            'format' => $format,
        );

        return $this->then_return;
    }

    /**
     * Logs any get_results call.
     *
     * NATIVE RETURN >> array|object|null Database query results.
     *
     * @param string $query  SQL query.
     * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K
     * @return mixed
     */
    public function get_results($query = null, $output = OBJECT)
    {
        $this->usage_log['get_results'][] = array(
            'query'  => $query,
            'output' => $output,
        );

        return $this->then_return;
    }

    /**
     * Logs any prepare call.
     *
     * NATIVE RETURN >> string The query with populated placeholders.
     *
     * @param string $query
     * @param mixed ...$args
     * @return void
     */
    public function prepare($query, ...$args)
    {
        $this->usage_log['prepare'][] = array(
            'query'  => $query,
            'args' => $args[0],
        );

        return sprintf(\str_replace('%s', "'%s'", $query), ...$args[0]);
    }

    /**
     * Logs every Query call
     *
     * NATIVE RETURN >> int|bool
     *
     * @param string $query
     * @return int|bool
     */
    public function query($query)
    {
        $this->usage_log['query'][] = $query;

        return $this->then_return;
    }
}
