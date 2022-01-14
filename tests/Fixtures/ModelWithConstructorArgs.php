<?php

/**
 * Example model that is populated using magic __set() with constructor args.
 */

namespace Pixie\Tests\Fixtures;

class ModelWithConstructorArgs
{
    public $fromMagicSetter = [];

    public $con1;
    public $con2;

    public function __construct($con1, $con2)
    {
        $this->con1 = $con1;
        $this->con2 = $con2;

        // Throw if $con2 is set to throw
        if ('throw' ===  $con2) {
            throw new \Exception("Error constructing ModelWithConstructorArgs", 1);
        }
    }

    public function __set($key, $value)
    {
        $this->$key = $value;
        $this->fromMagicSetter[$key] = $value;
    }

    public function __get($key)
    {
        return \array_key_exists($key, $this->fromMagicSetter)
            ? $this->fromMagicSetter[$key]
            : null;
    }
}
