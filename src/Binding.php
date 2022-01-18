<?php

namespace Pixie;

use Pixie\Exception;
use Pixie\QueryBuilder\Raw;

class Binding
{
    public const STRING = '%s';
    public const BOOL = '%d';
    public const INT = '%d';
    public const FLOAT = '%f';
    public const JSON = '%s';
    public const RAW = ':RAW';

    /**
     * Holds the value to bind with
     *
     * @var mixed
     */
    protected $value;

    /**
     * Denotes the type
     *
     * @var string|null
     */
    protected $type;

    /**
     * Denotes if the field is a RAW value
     *
     * @var bool
     */
    protected $isRaw = false;

    /**
     * @param mixed $value
     * @param string|null $type
     */
    public function __construct($value, ?string $type = null)
    {
        $this->verifyType($type);
        $this->value = $value;
        $this->type = $type;
        if (self::RAW === $type) {
            $this->isRaw = true;
        }
    }

    /**
     * Creates a binding for a String
     *
     * @param mixed $value
     * @return self
     */
    public static function asString($value): self
    {
        return new Binding($value, self::STRING);
    }

    /**
     * Creates a binding for a Float
     *
     * @param mixed $value
     * @return self
     */
    public static function asFloat($value): self
    {
        return new Binding($value, self::FLOAT);
    }

    /**
     * Creates a binding for a Int
     *
     * @param mixed $value
     * @return self
     */
    public static function asInt($value): self
    {
        return new Binding($value, self::INT);
    }

    /**
     * Creates a binding for a Bool
     *
     * @param mixed $value
     * @return self
     */
    public static function asBool($value): self
    {
        return new Binding($value, self::BOOL);
    }

    /**
     * Creates a binding for a JSON
     *
     * @param mixed $value
     * @return self
     */
    public static function asJSON($value): self
    {
        return new Binding($value, self::JSON);
    }

    /**
     * Creates a binding for a Raw
     *
     * @param mixed $value
     * @return self
     */
    public static function asRaw($value): self
    {
        return new Binding($value, self::RAW);
    }

    /**
     * Verifies that the passed type is allowed
     *
     * @param string|null $type
     * @return void
     * @throws Exception if not a valid type.
     */
    protected function verifyType(?string $type): void
    {
        $validTypes = [self::STRING, self::BOOL, self::FLOAT, self::INT, self::JSON, self::RAW];
        if (null !== $type && !in_array($type, $validTypes, true)) {
            throw new Exception("{$type} is not a valid type to use for Bindings.", 1);
        }
    }

    /**
     * Checks if we have a type that will bind.
     *
     * @return bool
     */
    public function hasTypeDefined(): bool
    {
        return !\in_array($this->type, [null, self::RAW], true);
    }

    /**
     * Returns the bindings values
     *
     * @return string|int|float|bool|Raw|null
     */
    public function getValue()
    {
        return ! $this->hasTypeDefined()
            ? new Raw($this->value)
            : $this->value;
    }

    /**
     * Gets the types format Conversion Specifier
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Get denotes if the field is a RAW value
     *
     * @return bool
     */
    public function isRaw(): bool
    {
        return $this->isRaw;
    }
}
