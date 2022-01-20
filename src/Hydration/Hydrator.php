<?php

declare(strict_types=1);

/**
 * Handles the hydration of objects/models for results.
 */

namespace Pixie\Hydration;

use Exception;
use stdClass;
use Throwable;
use function is_object;
use function method_exists;
use function trim;
use function ucfirst;

/**
 * @template T
 */
class Hydrator
{
    /**
     * The model to hydrate

     *
     * @var class-string<T>
     */
    protected $model;

    /**
     * The arguments used to create the new instance.
     *
     * @var array<string|int, mixed>
     */
    protected $constructorArgs;

    /**

     * @param class-string<T> $model
     * @param array<string|int, mixed> $constructorArgs
     */
    public function __construct(string $model = stdClass::class, array $constructorArgs = [])
    {
        $this->model           = $model;
        $this->constructorArgs = $constructorArgs;
    }

    /**
     * Map many models
     *
     * @param array<int, object|mixed[]> $sources
     *
     * @return array<T>
     */
    public function fromMany(array $sources): array
    {
        return array_map([$this, 'from'], $sources);
    }

    /**
     * Map a single model
     *
     * @param object|mixed[] $source
     *
     * @return T
     */
    public function from($source)
    {
        switch (true) {
            case is_array($source):
                return $this->fromArray($source);

            case is_object($source):
                return $this->fromObject($source);

            default:
                throw new Exception('Models can only be mapped from arrays or objects.', 1);
        }
    }

    /**
     * Maps the model from an array of data.
     *
     * @param array<string, mixed> $source
     *
     * @return T
     */
    protected function fromArray(array $source)
    {
        $model = $this->newInstance();
        foreach ($source as $key => $value) {
            $this->setProperty($model, $key, $value);
        }

        return $model;
    }

    /**
     * Maps a model from an Object of data
     *
     * @param object $source
     *
     * @return T
     */
    protected function fromObject($source)
    {
        $vars = get_object_vars($source);

        return $this->fromArray($vars);
    }

    /**
     * Construct an instance of the model

     *
     * @return T
     */
    protected function newInstance()
    {
        $class = $this->model;
        try {
            /** @var T */
            $instance = empty($this->constructorArgs)
                ? new $class()
                : new $class(...$this->constructorArgs);
        } catch (Throwable $th) {
            throw new Exception("Failed to construct model, {$th->getMessage()}", 1);
        }

        return $instance;
    }

    /**
     * Sets a property to the current model
     *
     * @param T $model
     * @param string $property
     * @param mixed $value
     *
     * @return T
     */
    protected function setProperty($model, string $property, $value)
    {
        $property = $this->normaliseProperty($property);

        // Attempt to set.
        try {
            switch (true) {
                case method_exists($model, $this->generateSetterMethod($property)):
                    $method = $this->generateSetterMethod($property);
                    $model->$method($value);
                    break;

                case method_exists($model, $this->generateSetterMethod($property, true)):
                    $method = $this->generateSetterMethod($property, true);
                    $model->$method($value);
                    break;

                default:
                    $model->$property = $value;
                    break;
            }
        } catch (Throwable $th) {
            throw new Exception(sprintf('Failed to set %s of %s model, %s', $property, get_class($model), $th->getMessage()), 1);
        }

        return $model;
    }

    /**
     * Normalises a property
     *
     * @param string $property
     *
     * @return string
     */
    protected function normaliseProperty(string $property): string
    {
        return trim(
            preg_replace('/[^a-z0-9]+/', '_', strtolower($property)) ?: ''
        );
    }

    /**
     * Generates a generic setter method using either underscore [set_property()] or PSR2 style [setProperty()]
     *
     * @param string $property
     * @param bool $underscore
     *
     * @return string
     */
    protected function generateSetterMethod(string $property, bool $underscore = false): string
    {
        return $underscore
            ? "set_{$property}"
            : 'set' . ucfirst($property);
    }
}
