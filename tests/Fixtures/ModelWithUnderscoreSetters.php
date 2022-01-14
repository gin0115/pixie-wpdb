<?php

/**
 * Example model that is populated with setters using underscores.
 */

namespace Pixie\Tests\Fixtures;

class ModelWithUnderscoreSetters
{

    public $foo;

    public $bar;

    public function set_foo($value)
    {
        $this->foo = $value;
    }

    public function set_bar($value)
    {
        $this->bar = $value;
    }
}
