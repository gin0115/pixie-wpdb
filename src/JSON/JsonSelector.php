<?php

namespace Pixie\JSON;

class JsonSelector
{
    /**
     * The table column
     *
     * @var string
     */
    protected $column;

    /**
     * JSON Nodes
     *
     * @var string[]
     */
    protected $nodes;

    /**
     * @param string $column
     * @param string[] $nodes
     */
    public function __construct(string $column, array $nodes)
    {
        $this->column = $column;
        $this->nodes = $nodes;
    }

    /**
     * Get the table column
     *
     * @return string
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Get jSON Nodes
     *
     * @return string[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
