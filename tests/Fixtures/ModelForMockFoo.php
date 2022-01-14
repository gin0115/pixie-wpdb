<?php

/**
 * Example model for WPDB integration tests.
 *
 * Includes optional constructor props.
 */

namespace Pixie\Tests\Fixtures;

class ModelForMockFoo
{

    public $constructorProp;

    public $string;

    public $number;

    public $rowId = -1;

    public function __construct(string $constructorProp = 'DEFAULT')
    {
        $this->constructorProp = $constructorProp;
    }

    public function setNumber(string $number): void
    {
        $this->number = (int) $number;
    }

    public function set_string(string $string)
    {
        $this->string = "{$string}!!";
    }

    public function __set($key, $value)
    {
        if ($key === 'id') {
            $this->rowId = (int) $value;
        }
    }
}
