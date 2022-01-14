<?php

declare(strict_types=1);

/**
 * Unit tests object hydrator
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests;

use Exception;
use WP_UnitTestCase;
use Pixie\Hydration\Hydrator;
use Pixie\Tests\Fixtures\ModelWithSetters;
use Pixie\Tests\Fixtures\ModelWithNoSetters;
use Pixie\Tests\Fixtures\ModelWithMagicSetter;
use Pixie\Tests\Fixtures\ModelWithConstructorArgs;
use Pixie\Tests\Fixtures\ModelWithUnderscoreSetters;
use Pixie\Tests\Fixtures\ModelWithNoSettersAndPrivateProperties;

class TestHydrator extends WP_UnitTestCase
{
    /** @testdox It should be possible to hydrate an object (from array values) which uses setters and the underscores naming convention. Property foo would be set with set_foo($value) */
    public function testCanHydrateUsingSettersWithUnderscoresFromArray(): void
    {
        $hydrator = new Hydrator(ModelWithUnderscoreSetters::class);
        $model = $hydrator->from(['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithUnderscoreSetters::class, $model);
        $this->assertObjectHasAttribute('foo', $model);
        $this->assertEquals('a', $model->foo);
        $this->assertObjectHasAttribute('bar', $model);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to hydrate an object (from object values) which uses setters and the underscores naming convention. Property foo would be set with set_foo($value) */
    public function testCanHydrateUsingSettersWithUnderscoresFromObject(): void
    {
        $hydrator = new Hydrator(ModelWithUnderscoreSetters::class);
        $model = $hydrator->from((object)['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithUnderscoreSetters::class, $model);
        $this->assertObjectHasAttribute('foo', $model);
        $this->assertEquals('a', $model->foo);
        $this->assertObjectHasAttribute('bar', $model);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to hydrate an object (from array values) which uses setters. Property foo would be set with setFoo($value) */
    public function testCanHydrateUsingSettersFromArray(): void
    {
        $hydrator = new Hydrator(ModelWithSetters::class);
        $model = $hydrator->from(['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithSetters::class, $model);
        $this->assertObjectHasAttribute('foo', $model);
        $this->assertEquals('a', $model->foo);
        $this->assertObjectHasAttribute('bar', $model);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to hydrate an object (from object values) which uses setters. Property foo would be set with setFoo($value) */
    public function testCanHydrateUsingSettersFromObject(): void
    {
        $hydrator = new Hydrator(ModelWithSetters::class);
        $model = $hydrator->from((object)['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithSetters::class, $model);
        $this->assertObjectHasAttribute('foo', $model);
        $this->assertEquals('a', $model->foo);
        $this->assertObjectHasAttribute('bar', $model);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to hydrate an object (from array values) without setters. Property foo would be set with setFoo($value) */
    public function testCanHydrateWithoutSettersFromArray(): void
    {
        $hydrator = new Hydrator(ModelWithNoSetters::class);
        $model = $hydrator->from(['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithNoSetters::class, $model);
        $this->assertObjectHasAttribute('foo', $model);
        $this->assertEquals('a', $model->foo);
        $this->assertObjectHasAttribute('bar', $model);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to hydrate an object (from object values) without setters. Property foo would be set with setFoo($value) */
    public function testCanHydrateWithoutSettersFromObject(): void
    {
        $hydrator = new Hydrator(ModelWithNoSetters::class);
        $model = $hydrator->from((object)['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithNoSetters::class, $model);
        $this->assertObjectHasAttribute('foo', $model);
        $this->assertEquals('a', $model->foo);
        $this->assertObjectHasAttribute('bar', $model);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to hydrate an object (from array values) using the magic __set() method */
    public function testCanHydrateWithMagicSettersFromArray(): void
    {
        $hydrator = new Hydrator(ModelWithMagicSetter::class);
        $model = $hydrator->from(['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithMagicSetter::class, $model);
        $this->assertArrayHasKey('foo', $model->fromMagicSetter);
        $this->assertEquals('a', $model->foo);
        $this->assertArrayHasKey('bar', $model->fromMagicSetter);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to hydrate an object (from object values) using the magic __set() method */
    public function testCanHydrateWithMagicSettersFromObject(): void
    {
        $hydrator = new Hydrator(ModelWithMagicSetter::class);
        $model = $hydrator->from((object)['foo' => 'a', 'bar' => 'b']);

        $this->assertInstanceOf(ModelWithMagicSetter::class, $model);
        $this->assertArrayHasKey('foo', $model->fromMagicSetter);
        $this->assertEquals('a', $model->foo);
        $this->assertArrayHasKey('bar', $model->fromMagicSetter);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox It should be possible to use a model which needs constructor arguments passed to it. */
    public function testCanHydrateModelWithConstructorArgs(): void
    {
        $hydrator = new Hydrator(ModelWithConstructorArgs::class, ['alpha', 'bravo']);
        $model = $hydrator->from(['foo' => 'a', 'bar' => 'b']);

        // Check constructor args populated.
        $this->assertEquals('alpha', $model->con1);
        $this->assertEquals('bravo', $model->con2);

        $this->assertArrayHasKey('foo', $model->fromMagicSetter);
        $this->assertEquals('a', $model->foo);
        $this->assertArrayHasKey('bar', $model->fromMagicSetter);
        $this->assertEquals('b', $model->bar);
    }

    /** @testdox Constructing a model using constructor args, should throw an exception if any errors thrown. */
    public function testThrowsExceptionIfErrorConstructingModel(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to construct model, Error constructing ModelWithConstructorArgs');
        $hydrator = new Hydrator(ModelWithConstructorArgs::class, ['alpha', 'throw']);
        $hydrator->from(['foo' => 'a', 'bar' => 'b']);
    }

    /** @testdox An exception should be thrown when trying to set a property if no setter and the property exists as none public. */
    public function testThrowsIfTryingToSetNonePublicMethodWithNoSetter(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to set foo of Pixie\Tests\Fixtures\ModelWithNoSettersAndPrivateProperties model, Cannot access private property Pixie\Tests\Fixtures\ModelWithNoSettersAndPrivateProperties::$foo');
        $hydrator = new Hydrator(ModelWithNoSettersAndPrivateProperties::class);
        $hydrator->from(['foo' => 'a', 'bar' => 'b']);
    }

    /** @testdox It should be possible to use strings containing none alphanumeric or underscores and have them normalised */
    public function testCanNormaliseProperties()
    {
        $hydrator = new Hydrator(ModelWithMagicSetter::class);
        $model = $hydrator->from((object)['- this is    not . a % valid key' => 'a', 'table.bar' => 'b']);

        $this->assertArrayHasKey('_this_is_not_a_valid_key', $model->fromMagicSetter);
        $this->assertEquals('a', $model->fromMagicSetter['_this_is_not_a_valid_key']);
        $this->assertArrayHasKey('table_bar', $model->fromMagicSetter);
        $this->assertEquals('b', $model->fromMagicSetter['table_bar']);
    }

    /** @testdox An exception should be thrown trying to hydrate a model from anything other than an array or object. */
    public function testThrowsExceptionIfNotCreatedFromArrayOrObject(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Models can only be mapped from arrays or objects.');
        $hydrator = new Hydrator(ModelWithMagicSetter::class);
        $hydrator->from('This is not an object or array');
    }

    /** @testdox It should be possible to hydrate an array of data in object/array form and have an array of populated models returned.*/
    public function testCanHydrateMultiple(): void
    {
        $data = [
            ['foo' => 'a1', 'bar' => 'b1'],
            ['foo' => 'a2', 'bar' => 'b2'],
            ['foo' => 'a2', 'bar' => 'b2'],
        ];

        $hydrator = new Hydrator(ModelWithMagicSetter::class);
        $models = $hydrator->fromMany($data);

        // Check the values.
        foreach ($models as $key => $model) {
            $this->assertInstanceOf(ModelWithMagicSetter::class, $model);
            $this->assertEquals($data[$key]['foo'], $model->foo);
            $this->assertEquals($data[$key]['bar'], $model->bar);
        }
    }
}
