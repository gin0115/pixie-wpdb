<?php

namespace Pixie\QueryBuilder;

use Closure;
use Pixie\Exception;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;

trait TablePrefixer
{
    /**
         * Add table prefix (if given) on given string.
         *
         * @param array<string|int, string|int|float|bool|Raw|Closure>|string|int|float|bool|Raw|Closure     $values
         * @param bool $tableFieldMix If we have mixes of field and table names with a "."
         *
         * @return mixed|mixed[]
         */
    public function addTablePrefix($values, bool $tableFieldMix = true)
    {
        if (is_null($this->getTablePrefix())) {
            return $values;
        }

        // $value will be an array and we will add prefix to all table names

        // If supplied value is not an array then make it one
        $single = false;
        if (!is_array($values)) {
            $values = [$values];
            // We had single value, so should return a single value
            $single = true;
        }

        $return = [];

        foreach ($values as $key => $value) {
            // It's a raw query, just add it to our return array and continue next
            if ($value instanceof Raw || $value instanceof Closure) {
                $return[$key] = $value;
                continue;
            }

            // If key is not integer, it is likely a alias mapping,
            // so we need to change prefix target
            $target = &$value;
            if (!is_int($key)) {
                $target = &$key;
            }

            // Do prefix if the target is an expression or function.
            if (
                !$tableFieldMix
                || (
                    is_string($target) // Must be a string
                    && (bool) preg_match('/^[A-Za-z0-9_.]+$/', $target) // Can only contain letters, numbers, underscore and full stops
                    && 1 === \substr_count($target, '.') // Contains a single full stop ONLY.
                )
            ) {
                $target = $this->getTablePrefix() . $target;
            }

            $return[$key] = $value;
        }

        // If we had single value then we should return a single value (end value of the array)
        return true === $single ? end($return) : $return;
    }

    /**
     * Returns the table prefix if defined in connection
     *
     * @return string|null
     */
    protected function getTablePrefix(): ?string
    {
        $adapterConfig = $this->getConnection()->getAdapterConfig();
        return isset($adapterConfig[Connection::PREFIX])
            ? $adapterConfig[Connection::PREFIX]
            : null;
    }

    abstract public function getConnection(): Connection;
}
