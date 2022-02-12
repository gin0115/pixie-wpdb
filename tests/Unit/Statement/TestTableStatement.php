<?php

declare(strict_types=1);

/**
 * Unit tests for the TableStatement
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
use Pixie\Statement\Statement;
use Pixie\Statement\TableStatement;

/**
 * @group v0.2
 * @group unit
 * @group statement
 */
class TestTableStatement extends WP_UnitTestCase
{
    /** Supplies all types which will throw as tables for statement */
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
     * @testdox An exception should be thrown if a none String, Raw passed as the table in a statement.
     * @dataProvider invalidTypeProvider
     */
    public function testThrowsIfNoneStringRawJsonSelectorPassed($table)
    {
        $this->expectExceptionMessage('Only string and Raw may be used as tables');
        $this->expectException(TypeError::class);

        new TableStatement($table);
    }

    /** @testdox It should be possible to get the correct type from any Statement [TABLE] */
    public function testGetType(): void
    {
        $this->assertEquals('table', (new TableStatement('*'))->getType());
        $this->assertEquals(Statement::TABLE, (new TableStatement('*'))->getType());
    }

    /** @testdox Raw tables will need to be interpolated before they can be parsed, it should be possible to check if this needs to happen. */
    public function testCanInterpolateField(): void
    {
        $statement = function ($table): TableStatement {
            return new TableStatement($table);
        };

        $this->assertFalse($statement('tableName')->tableRequiresInterpolation());
        $this->assertTrue($statement(new Raw('tableName'))->tableRequiresInterpolation());
    }

    /** @testdox It should be possible to interpolate a table and be given a new instance of a Statement with the resolved table. */
    public function testCanInterpolateFieldWithClosure(): void
    {
        $statement = new TableStatement(new Raw('foo-%s', ['boo']), 'alias');
        $newStatement = $statement->interpolateField(
            function (Raw $e) {
                return sprintf($e->getValue(), ...$e->getBindings());
            }
        );

        // Alias should remain unchanged
        $this->assertEquals('alias', $statement->getAlias());
        $this->assertEquals('alias', $newStatement->getAlias());

        // Field should be whatever is returned from closure
        $this->assertEquals('foo-boo', $newStatement->getTable());

        // Should be a new object.
        $this->assertNotSame($statement, $newStatement);
    }

    /** @testdox It should be possible to check if the statement has an alias and gets it value if it does. */
    public function testGetAliasIfExists(): void
    {
        $with = new TableStatement('table', 'with');
        $without = new TableStatement('table');
        $empty = new TableStatement('table', '');
        $null = new TableStatement('table', null);

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
