<?php

/**
 * Example model that is populated with setters using PSR12 setters.
 */

namespace Pixie\Tests\Fixtures;

class ModelWithSetters
{

    public $foo;

    public $bar;

    public function setFoo($value)
    {
        $this->foo = $value;
    }

    public function setBar($value)
    {
        $this->bar = $value;
    }
}
