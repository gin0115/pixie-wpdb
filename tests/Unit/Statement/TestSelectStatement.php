<?php

declare(strict_types=1);

/**
 * Unit tests for the SelectStatement
 *
 * @since 0.2.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit\Statement;

use stdClass;
use TypeError;
use WP_UnitTestCase;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\QueryBuilder\Statement\Statement;
use Pixie\QueryBuilder\Statement\SelectStatement;

/**
 * @group v0.2
 * @group unit
 * @group statement
 */
class TestSelectStatement extends WP_UnitTestCase
{
    /** Supplies all types which will throw as fields for statement */
    public function invalidTypeProvider(): array
    {
        return [
            [1],
            [2.5],
            [['array']],
            [new stdClass()],
            [null],
            [false]
        ];
    }

    /**
     * @testdox An exception should be thrown if a none String, Raw or JsonSelector passed as the field of a select statement.
     * @dataProvider invalidTypeProvider
     */
    public function testThrowsIfNoneStringRawJsonSelectorPassed($field)
    {
        $this->expectExceptionMessage('Only string, Raw and JsonSelectors may be used as select fields');
        $this->expectException(TypeError::class);

        new SelectStatement($field);
    }

    /** @testdox It should be possible to get the correct type from any Statement [SELECT] */
    public function testGetType(): void
    {
        $this->assertEquals('select', (new SelectStatement('*'))->getType());
        $this->assertEquals(Statement::SELECT, (new SelectStatement('*'))->getType());
    }

    /** @testdox Raw and JsonSelector fields will need to be interpolated before they can be parsed, it should be possible to check if this needs to happen. */
    public function testCanInterpolateField(): void
    {
        $statement = function ($field): SelectStatement {
            return new SelectStatement($field);
        };

        $this->assertFalse($statement('string')->fieldRequiresInterpolation());
        $this->assertTrue($statement(new Raw('string'))->fieldRequiresInterpolation());
        $this->assertTrue($statement(new JsonSelector('string', ['a','b']))->fieldRequiresInterpolation());
    }

    /** @testdox It should be possible to interpolate a field and be given a new instance of a Statement with the resolved field. */
    public function testCanInterpolateFieldWithClosure(): void
    {
        $statement = new SelectStatement(new Raw('foo-%s', ['boo']), 'alias');
        $newStatement = $statement->interpolateField(
            function (Raw $e) {
                return sprintf($e->getValue(), ...$e->getBindings());
            }
        );

        // Alias should remain unchanged
        $this->assertEquals('alias', $statement->getAlias());
        $this->assertEquals('alias', $newStatement->getAlias());

        // Field should be whatever is returned from closure
        $this->assertEquals('foo-boo', $newStatement->getField());

        // Should be a new object.
        $this->assertNotSame($statement, $newStatement);
    }

    /** @testdox It should be possible to check if the statement has an alias and gets it value if it does. */
    public function testGetAliasIfExists(): void
    {
        $with = new SelectStatement('field', 'with');
        $without = new SelectStatement('field');
        $empty = new SelectStatement('field', '');
        $null = new SelectStatement('field', null);

        $this->assertTrue($with->hasAlias());
        $this->assertFalse($without->hasAlias());
        $this->assertFalse($empty->hasAlias());
        $this->assertFalse($null->hasAlias());

        $this->assertEquals('with', $with->getAlias());
        $this->assertNull($without->getAlias());
        $this->assertNull($empty->getAlias());
        $this->assertNull($null->getAlias());
    }
}
