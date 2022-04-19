<?php

declare(strict_types=1);

/**
 * Unit tests for the StatementBuilder
 *
 * @since 0.2.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit\Statement;

use WP_UnitTestCase;
use Pixie\Statement\Statement;
use Pixie\Statement\TableStatement;
use Pixie\Statement\SelectStatement;
use Pixie\Statement\StatementBuilder;

/**
 * @group v0.2
 * @group unit
 * @group statement
 */
class TestStatementBuilder extends WP_UnitTestCase
{

    /** @testdox It should be possible to get the contents of the collection */
    public function testGetCollectionItems(): void
    {
        $builder = new StatementBuilder();
        $array = $builder->getStatements();

        // Check all keys exist
        $this->assertArrayHasKey(Statement::SELECT, $array);
        $this->assertArrayHasKey('select', $array);
        $this->assertArrayHasKey(Statement::TABLE, $array);
        $this->assertArrayHasKey('table', $array);

        // Add values,
        $builder->addSelect($this->createMock(SelectStatement::class));
        $builder->addTable($this->createMock(TableStatement::class));
        $array = $builder->getStatements();
        $this->assertCount(1, $array['select']);
        $this->assertCount(1, $array['table']);
    }

    /** @testdox It should be possible to add, fetch select statements and check if any set. */
    public function testSelectStatement(): void
    {
        $builder = new StatementBuilder();

        // Should be empty
        $this->assertFalse($builder->hasSelect());
        $this->assertEmpty($builder->getSelect());

        $statement = $this->createMock(SelectStatement::class);
        $builder->addSelect($statement);

        $this->assertTrue($builder->hasSelect());
        $this->assertCount(1, $builder->getSelect());
        $this->assertContains($statement, $builder->getSelect());
    }

    /** @testdox It should be possible to add, fetch table statements and check if any set. */
    public function testTableStatement(): void
    {
        $builder = new StatementBuilder();

        // Should be empty
        $this->assertFalse($builder->hasTable());
        $this->assertEmpty($builder->getTable());

        $statement = $this->createMock(TableStatement::class);
        $builder->addTable($statement);

        $this->assertTrue($builder->hasTable());
        $this->assertCount(1, $builder->getTable());
        $this->assertContains($statement, $builder->getTable());
    }
}
