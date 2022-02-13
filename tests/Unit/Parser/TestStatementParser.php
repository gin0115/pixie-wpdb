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
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonSelector;
use Pixie\Tests\Logable_WPDB;
use Pixie\Statement\Statement;
use Pixie\Parser\StatementParser;
use Pixie\Tests\SQLAssertionsTrait;
use Pixie\Statement\SelectStatement;
use Pixie\Statement\StatementCollection;

/**
 * @group v0.2
 * @group unit
 * @group parser
 */
class TestStatementParser extends WP_UnitTestCase
{
    use SQLAssertionsTrait;

    /** Mocked WPDB instance.
     * @var Logable_WPDB
     */
    private $wpdb;

    public function setUp(): void
    {
        $this->wpdb = new Logable_WPDB();
        parent::setUp();
    }

    /**
     * Create an instance of the StatementParser with a defined connection config.
     *
     * @param array $connectionConfig
     * @return \Pixie\Parser\StatementParser
     */
    public function getParser(array $connectionConfig = []): StatementParser
    {
        return new StatementParser(new Connection($this->wpdb, $connectionConfig));
    }

    /** @testdox Is should be possible to parse all expected select values from string, json arrow and selector objects and raw expressions and have them returned as a valid SQL fragment. */
    public function testSelectParserWithAcceptTypes(): void
    {
        $collection = new StatementCollection();
        // Expected user inputs
        $collection->addSelect(new SelectStatement('simpleCol'));
        $collection->addSelect(new SelectStatement('table.simpleCol'));
        $collection->addSelect(new SelectStatement('json->arrow->selector'));
        $collection->addSelect(new SelectStatement('table.json->arrow->selector'));
        // Expected internal inputs.
        $collection->addSelect(new SelectStatement(new JsonSelector('jsSimpleCol', ['nodeA', 'nodeB'])));
        $collection->addSelect(new SelectStatement(new JsonSelector('table.jsSimpleCol', ['nodeA', 'nodeB'])));
        $collection->addSelect(new SelectStatement(Raw::val('rawSimpleCol')));
        $collection->addSelect(new SelectStatement(Raw::val('table.rawSimpleCol')));

        $parsed = $this->getParser([Connection::PREFIX => 'pfx_'])
            ->parseSelect($collection->getSelect());

        $this->assertStringContainsString('simpleCol', $parsed);
        $this->assertStringContainsString('pfx_table.simpleCol', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(json, "$.arrow.selector"))', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(pfx_table.json, "$.arrow.selector"))', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(jsSimpleCol, "$.nodeA.nodeB"))', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(pfx_table.jsSimpleCol, "$.nodeA.nodeB"))', $parsed);
        $this->assertStringContainsString('rawSimpleCol', $parsed);
        $this->assertStringContainsString('pfx_table.rawSimpleCol', $parsed);

        // Verify as valid SQL.
        $this->assertValidSQL(sprintf("SELECT %s FROM fooTable;", $parsed));
    }

    /** @testdox Is should be possible to parse all expected select with aliases values from string, json arrow and selector objects and raw expressions and have them returned as a valid SQL fragment. */
    public function testSelectParserWithAcceptTypesWithAliases(): void
    {
        $collection = new StatementCollection();
        // Expected user inputs
        $collection->addSelect(new SelectStatement('simpleCol', 'alias'));
        $collection->addSelect(new SelectStatement('table.simpleCol', 'alias'));
        $collection->addSelect(new SelectStatement('json->arrow->selector', 'alias'));
        $collection->addSelect(new SelectStatement('table.json->arrow->selector', 'alias'));
        // Expected internal inputs.
        $collection->addSelect(new SelectStatement(new JsonSelector('jsSimpleCol', ['nodeA', 'nodeB']), 'alias'));
        $collection->addSelect(new SelectStatement(new JsonSelector('table.jsSimpleCol', ['nodeA', 'nodeB']), 'alias'));
        $collection->addSelect(new SelectStatement(Raw::val('rawSimpleCol'), 'alias'));
        $collection->addSelect(new SelectStatement(Raw::val('table.rawSimpleCol'), 'alias'));

        $parsed = $this->getParser([Connection::PREFIX => 'egh_'])
            ->parseSelect($collection->getSelect());

        $this->assertStringContainsString('simpleCol AS alias', $parsed);
        $this->assertStringContainsString('egh_table.simpleCol AS alias', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(json, "$.arrow.selector")) AS alias', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(egh_table.json, "$.arrow.selector")) AS alias', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(jsSimpleCol, "$.nodeA.nodeB")) AS alias', $parsed);
        $this->assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(egh_table.jsSimpleCol, "$.nodeA.nodeB")) AS alias', $parsed);
        $this->assertStringContainsString('rawSimpleCol AS alias', $parsed);
        $this->assertStringContainsString('egh_table.rawSimpleCol', $parsed);

        // Verify as valid SQL.
        $this->assertValidSQL(sprintf("SELECT %s FROM fooTable;", $parsed));
    }
}
