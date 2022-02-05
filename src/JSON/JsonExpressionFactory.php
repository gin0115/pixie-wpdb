<?php

namespace Pixie\JSON;

use Pixie\Exception;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\QueryBuilder\TablePrefixer;

class JsonExpressionFactory
{
    use TablePrefixer;

    /** @var Connection */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns the current connection instance.
     *
     * @return connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Normalises the values passed as nodes
     *
     * @param mixed $nodes
     * @return string
     */
    protected function normaliseNodes($nodes): string
    {
        // If its not an array, cast.
        if (!is_array($nodes)) {
            $nodes = [$nodes];
        }

        // Remove all none string.
        $nodes = array_filter($nodes, function ($node): bool {
            return is_string($node);
        });

        // If we have no nodes, throw.
        if (count($nodes) === 0) {
            throw new Exception("Only strings values may be passed as nodes.");
        }

        return \implode('.', $nodes);
    }

    /**
     * @param string          $column  The database column which holds the JSON value
     * @param string|string[] $nodes   The json key/index to search
     * @return \Pixie\QueryBuilder\Raw
     */
    public function extractAndUnquote(string $column, $nodes): Raw
    {
        // Normalise nodes.
        $nodes = $this->normaliseNodes($nodes);

        // Add any possible prefixes to the key
        $column = $this->addTablePrefix($column, true);

        return new Raw("JSON_UNQUOTE(JSON_EXTRACT({$column}, \"$.{$nodes}\"))");
    }
}
