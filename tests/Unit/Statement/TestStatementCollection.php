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
        $collection = new StatementBuilder();
        $array = $collection->getStatements();

        // Check all keys exist
        $this->assertArrayHasKey(Statement::SELECT, $array);
        $this->assertArrayHasKey('select', $array);
        $this->assertArrayHasKey(Statement::TABLE, $array);
        $this->assertArrayHasKey('table', $array);

        // Add values,
        $collection->addSelect($this->createMock(SelectStatement::class));
        $collection->addTable($this->createMock(TableStatement::class));
        $array = $collection->getStatements();
        $this->assertCount(1, $array['select']);
        $this->assertCount(1, $array['table']);
    }

    /** @testdox It should be possible to add, fetch select statements and check if any set. */
    public function testSelectStatement(): void
    {
        $collection = new StatementBuilder();

        // Should be empty
        $this->assertFalse($collection->hasSelect());
        $this->assertEmpty($collection->getSelect());

        $statement = $this->createMock(SelectStatement::class);
        $collection->addSelect($statement);

        $this->assertTrue($collection->hasSelect());
        $this->assertCount(1, $collection->getSelect());
        $this->assertContains($statement, $collection->getSelect());
    }

    /** @testdox It should be possible to add, fetch table statements and check if any set. */
    public function testTableStatement(): void
    {
        $collection = new StatementBuilder();

        // Should be empty
        $this->assertFalse($collection->hasTable());
        $this->assertEmpty($collection->getTable());

        $statement = $this->createMock(TableStatement::class);
        $collection->addTable($statement);

        $this->assertTrue($collection->hasTable());
        $this->assertCount(1, $collection->getTable());
        $this->assertContains($statement, $collection->getTable());
    }
}
