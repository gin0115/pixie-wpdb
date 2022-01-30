<?php

namespace Pixie\JSON;

use Pixie\Exception;
use Pixie\Connection;
use Pixie\HasConnection;
use Pixie\QueryBuilder\TablePrefixer;

class JsonSelectorHandler implements HasConnection
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
     * Checks if the passed expression is for JSON
     * this->denotes->json
     *
     * @param string $expression
     * @return bool
     */
    public function isJsonSelector($expression): bool
    {
        return is_string($expression)
        && 2 <= count(explode('->', $expression));
    }

    /**
    * Gets the column name form a potential array
    *
    * @param string $expression
    * @return string
    * @throws Exception If invalid JSON Selector string passed.
    */
    public function getColumn(string $expression): string
    {
        return $this->asJsonSelector($expression)->getColumn();
    }

    /**
     * Gets all JSON object keys while removing the column name.
     *
     * @param string $expression
     * @return string[]
     * @throws Exception If invalid JSON Selector string passed.
     */
    public function getNodes(string $expression): array
    {
        return $this->asJsonSelector($expression)->getNodes();
    }

    /**
     * Casts a valid JSON selector to a JsonSelector object.
     *
     * @param string $expression
     * @return JsonSelector
     * @throws Exception If invalid JSON Selector string passed.
     */
    public function asJsonSelector(string $expression): JsonSelector
    {
        if (! $this->isJsonSelector($expression)) {
            throw new Exception('JSON expression must contain at least 2 values, the table column and JSON key.', 1);
        }

        /** @var string[] Check done above. */
        $parts = explode('->', $expression);

        $column = array_shift($parts);
        $nodes = $parts;

        if (! is_string($column)) {
            throw new Exception('JSON expression must contain a valid column name', 1);
        }

        return new JsonSelector($column, $nodes);
    }
}
