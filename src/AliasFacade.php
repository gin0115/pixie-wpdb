<?php

namespace Pixie;

use Pixie\QueryBuilder\QueryBuilderHandler;

/**
 * This class gives the ability to access non-static methods statically
 *
 * Class AliasFacade
 *
 */
class AliasFacade
{
    /**
     * @var QueryBuilderHandler|null
     */
    protected static $queryBuilderInstance;

    /**
     * @param string $method
     * @param mixed[] $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        if (!static::$queryBuilderInstance) {
            static::$queryBuilderInstance = new QueryBuilderHandler();
        }

        // Call the non-static method from the class instance
        $callable = [static::$queryBuilderInstance, $method];

        return is_callable($callable)
            ? call_user_func_array($callable, $args)
            : null;
    }

    /**
     * @param QueryBuilderHandler $queryBuilderInstance
     */
    public static function setQueryBuilderInstance($queryBuilderInstance): void
    {
        static::$queryBuilderInstance = $queryBuilderInstance;
    }
}
