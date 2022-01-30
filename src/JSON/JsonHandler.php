<?php

namespace Pixie\JSON;

use Pixie\Connection;

class JsonHandler
{
    /** @var Connection */
    protected $connection;

    /** @var JsonSelectorHandler */
    protected $jsonSelectorHandler;

    /** @var JsonExpressionFactory */
    protected $jsonExpressionFactory;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->jsonSelectorHandler = new JsonSelectorHandler($connection);
        $this->jsonExpressionFactory = new JsonExpressionFactory($connection);
    }

    /**
     * Returns the JSON Selector Handler
     *
     * @return JsonSelectorHandler
     */
    public function jsonSelectorHandler(): JsonSelectorHandler
    {
        return $this->jsonSelectorHandler;
    }

    /**
     * Returns the JSON Expression library
     *
     * @return JsonExpressionFactory
     */
    public function jsonExpressionFactory(): JsonExpressionFactory
    {
        return $this->jsonExpressionFactory;
    }

    /**
     * Parses a JSON selector and returns as an Extract and Unquote expression.
     *
     * @param string $selector
     * @return string
     */
    public function extractAndUnquoteFromJsonSelector(string $selector): string
    {
        $selector = $this->jsonSelectorHandler()->asJsonSelector($selector);
        return $this->jsonExpressionFactory()->extractAndUnquote(
            $selector->getColumn(),
            $selector->getNodes()
        );
    }

    /**
     * Checks if the passed values is a valid JSON Selector
     *
     * @param mixed $expression
     * @return bool
     */
    public function isJsonSelector($expression): bool
    {
        return $this->jsonSelectorHandler()->isJsonSelector($expression);
    }
}
