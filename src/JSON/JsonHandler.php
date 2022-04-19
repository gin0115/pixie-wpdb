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
        $this->jsonSelectorHandler = new JsonSelectorHandler();
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
        $selector = $this->jsonSelectorHandler()->toJsonSelector($selector);
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

    /**
     * Returns a JSON Selector from the passed expression
     *
     * @param string $selector
     * @return JsonSelector
     */
    public function asJsonSelector(string $selector): JsonSelector
    {
        return $this->jsonSelectorHandler()->toJsonSelector($selector);
    }

    /**
     * Extract and unquote a JSON selector from the passed expression
     *
     * @param JsonSelector $jsonSelector
     * @return string
     */
    public function extractAndUnquoteSelector(JsonSelector $jsonSelector): string
    {
        return $this->jsonExpressionFactory()->extractAndUnquote(
            $jsonSelector->getColumn(),
            $jsonSelector->getNodes()
        );
    }
}
