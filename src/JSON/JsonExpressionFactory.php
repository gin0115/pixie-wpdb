<?php

namespace Pixie\JSON;

use Pixie\Connection;
use Pixie\Exception;
use Pixie\QueryBuilder\Raw;
use Pixie\QueryBuilder\TablePrefixer;

use function implode;
use function json_encode;

class JsonExpressionFactory
{
    use TablePrefixer;

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns the current connection instance.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Normalises the values passed as nodes
     *
     * @param  mixed $nodes
     *
     * @return string
     */
    protected function normaliseNodes($nodes): string
    {
        // If its not an array, cast.
        if (!is_array($nodes)) {
            $nodes = [$nodes];
        }

        // Remove all none string.
        $nodes = array_filter(
            $nodes,
            function ($node): bool {
                return is_string($node);
            }
        );

        // If we have no nodes, throw.
        if (0 === count($nodes)) {
            throw new Exception('Only strings values may be passed as nodes.');
        }

        return implode('.', $nodes);
    }

    /**
     * @param  string          $column The database column which holds the JSON value
     * @param  string|string[] $nodes  The json key/index to search
     *
     * @return Raw
     */
    public function extractAndUnquote(string $column, $nodes): Raw
    {
        // Normalise nodes.
        $nodes = $this->normaliseNodes($nodes);

        // Add any possible prefixes to the key
        $column = $this->addTablePrefix($column, true);

        return new Raw("JSON_UNQUOTE(JSON_EXTRACT({$column}, \"$.{$nodes}\"))");
    }

    /* -----------------------------------------------------------------------
     * JSON Support Phase 2 (#28) — modification expressions.
     *
     * Each method returns a Raw expression using the portable verbose JSON_*
     * function syntax (never ->/->>), suitable for the lowest DB WordPress
     * supports (MySQL 5.7+ / MariaDB 10.2+). The returned Raw composes into
     * update()/insert()/select()/orderBy() like any other expression.
     * --------------------------------------------------------------------- */

    /**
     * JSON_SET — insert or update the value at the given path.
     *
     * @param string          $column
     * @param string|string[] $nodes
     * @param mixed           $value
     */
    public function set(string $column, $nodes, $value): Raw
    {
        return $this->modify('JSON_SET', $column, $nodes, $value);
    }

    /**
     * JSON_INSERT — insert the value only if the path does not already exist.
     *
     * @param string          $column
     * @param string|string[] $nodes
     * @param mixed           $value
     */
    public function insert(string $column, $nodes, $value): Raw
    {
        return $this->modify('JSON_INSERT', $column, $nodes, $value);
    }

    /**
     * JSON_REPLACE — update the value only if the path already exists.
     *
     * @param string          $column
     * @param string|string[] $nodes
     * @param mixed           $value
     */
    public function replace(string $column, $nodes, $value): Raw
    {
        return $this->modify('JSON_REPLACE', $column, $nodes, $value);
    }

    /**
     * JSON_ARRAY_APPEND — append a value to the array at the given path.
     *
     * @param string          $column
     * @param string|string[] $nodes
     * @param mixed           $value
     */
    public function appendArray(string $column, $nodes, $value): Raw
    {
        return $this->modify('JSON_ARRAY_APPEND', $column, $nodes, $value);
    }

    /**
     * JSON_ARRAY_INSERT — insert a value at a specific array index.
     *
     * The nodes must include the target index, e.g. ['data', 'list[1]'].
     *
     * @param string          $column
     * @param string|string[] $nodes
     * @param mixed           $value
     */
    public function insertArray(string $column, $nodes, $value): Raw
    {
        return $this->modify('JSON_ARRAY_INSERT', $column, $nodes, $value);
    }

    /**
     * JSON_REMOVE — remove the value at the given path.
     *
     * @param string          $column
     * @param string|string[] $nodes
     */
    public function remove(string $column, $nodes): Raw
    {
        $path   = $this->normaliseNodes($nodes);
        $column = $this->addTablePrefix($column, true);

        return new Raw(sprintf('JSON_REMOVE(%s, "$.%s")', $column, $path));
    }

    /**
     * JSON_MERGE_PRESERVE — merge a document, preserving duplicate keys as arrays.
     *
     * @param string $column
     * @param mixed  $document array/object/JSON-string document to merge in
     */
    public function mergePreserve(string $column, $document): Raw
    {
        return $this->merge($column, $document);
    }

    /**
     * Alias of mergePreserve(); JSON_MERGE is deprecated in favour of it.
     *
     * @param string $column
     * @param mixed  $document
     */
    public function merge(string $column, $document): Raw
    {
        $column = $this->addTablePrefix($column, true);

        return new Raw(
            sprintf(
                'JSON_MERGE_PRESERVE(%s, %s)',
                $column,
                $this->formatJsonDocument($document)
            )
        );
    }

    /**
     * JSON_MERGE_PATCH — RFC 7396 merge (last value wins, nulls remove keys).
     *
     * @param string $column
     * @param mixed  $document
     */
    public function mergePatch(string $column, $document): Raw
    {
        $column = $this->addTablePrefix($column, true);

        return new Raw(
            sprintf(
                'JSON_MERGE_PATCH(%s, %s)',
                $column,
                $this->formatJsonDocument($document)
            )
        );
    }

    /**
     * Extract a JSON value cast to a SQL type, for use in orderBy and the like.
     *
     * @param string          $column
     * @param string|string[] $nodes
     * @param string          $type   A SQL type, e.g. 'UNSIGNED', 'DECIMAL(10,2)', 'CHAR'.
     */
    public function orderByCast(string $column, $nodes, string $type): Raw
    {
        $path   = $this->normaliseNodes($nodes);
        $column = $this->addTablePrefix($column, true);

        return new Raw(
            sprintf(
                'CAST(JSON_UNQUOTE(JSON_EXTRACT(%s, "$.%s")) AS %s)',
                $column,
                $path,
                $type
            )
        );
    }

    /**
     * Shared builder for the path-and-value modification functions.
     *
     * @param string          $function
     * @param string          $column
     * @param string|string[] $nodes
     * @param mixed           $value
     */
    protected function modify(string $function, string $column, $nodes, $value): Raw
    {
        $path   = $this->normaliseNodes($nodes);
        $column = $this->addTablePrefix($column, true);

        return new Raw(
            sprintf(
                '%s(%s, "$.%s", %s)',
                $function,
                $column,
                $path,
                $this->formatValue($value)
            )
        );
    }

    /**
     * Formats a scalar/array value for inlining into a JSON modification call.
     *
     * @param mixed $value
     */
    protected function formatValue($value): string
    {
        if ($value instanceof Raw) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_null($value)) {
            return 'NULL';
        }

        if (is_array($value) || is_object($value)) {
            return $this->formatJsonDocument($value);
        }

        return sprintf("'%s'", $this->escapeString((string) $value));
    }

    /**
     * Formats an array/object/JSON-string into a JSON document expression.
     *
     * Uses JSON_EXTRACT('<json>', '$') to parse the string literal into a JSON
     * value. This is portable across both MySQL 5.7+ and MariaDB 10.2+, whereas
     * CAST(... AS JSON) is rejected by MariaDB.
     *
     * @param mixed $document
     */
    protected function formatJsonDocument($document): string
    {
        if ($document instanceof Raw) {
            return (string) $document;
        }

        $json = is_string($document) ? $document : (string) json_encode($document);

        return sprintf("JSON_EXTRACT('%s', '$')", $this->escapeString($json));
    }

    /**
     * Escapes single quotes for safe inlining as a SQL string literal.
     */
    protected function escapeString(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
