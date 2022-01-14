<?php

/**
 * Example model that is populated using magic __set()
 */

namespace Pixie\Tests\Fixtures;

class ModelWithMagicSetter
{
    public $fromMagicSetter = [];

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
