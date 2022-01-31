<?php

namespace Pixie\JSON;

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
     * @param string          $column  The database column which holds the JSON value
     * @param string|string[] $nodes   The json key/index to search
     * @return \Pixie\QueryBuilder\Raw
     */
    public function extractAndUnquote(string $column, $nodes): Raw
    {

        // Unpack any nodes.
        if (is_array($nodes)) {
            $nodes = \implode('.', $nodes);
        }

        // Add any possible prefixes to the key
        $column = $this->addTablePrefix($column, true);

        return new Raw("JSON_UNQUOTE(JSON_EXTRACT({$column}, \"$.{$nodes}\"))");
    }

    /**
     * @param string          $column  The database column which holds the JSON value
     * @param string|string[] $nodes   The json key/index to search
     * @param string|null     $alias   The alias to apply to this expression. Will be set as "json_{$column}" if not defined.
     * @return \Pixie\QueryBuilder\Raw
     */
    public function extractAndUnquoteWithAlias(string $column, $nodes, ?string $alias = null): Raw
    {
        return new Raw(sprintf(
            "%s AS %s",
            $this->extractAndUnquote($column, $nodes)->getValue(),
            null === $alias ? "json_{$column}" : $alias
        ));
    }
}
