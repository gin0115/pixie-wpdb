<?php

declare(strict_types=1);

/**
 * Unit Tests for JsonExpressionFactory
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit;

use Pixie\Exception;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\JSON\JsonExpressionFactory;

class TestJsonExpressionFactory extends WP_UnitTestCase
{

    private $connection;

    public function setUp(): void
    {
        $this->connection  = $this->createMock(Connection::class);
    }

    /**
     * Returns an instance of the JsonExpressionFactory
     *
     * @return JsonExpressionFactory
     */
    public function getFactory(): JsonExpressionFactory
    {
        return new JsonExpressionFactory($this->connection);
    }

    /** @testdox It should be possible to access the connection from the JsonExpressionFactory */
    public function testGetConnection()
    {
        $this->assertSame(
            $this->getFactory()->getConnection(),
            $this->connection
        );
    }

    /** @testdox It should be possible to create a JSON query that extracts and unquotes based on column and nodes passed. */
    public function testExtractAndUnquote()
    {
        // Single node as string.
        $exp = $this->getFactory()->extractAndUnquote('col', 'node');
        $this->assertInstanceOf(Raw::class, $exp);
        $this->assertEquals('JSON_UNQUOTE(JSON_EXTRACT(col, "$.node"))', $exp->getValue());

        // Multiple nodes
        $exp = $this->getFactory()->extractAndUnquote('col', ['nodeA', 'nodeB']);
        $this->assertInstanceOf(Raw::class, $exp);
        $this->assertEquals('JSON_UNQUOTE(JSON_EXTRACT(col, "$.nodeA.nodeB"))', $exp->getValue());
    }

    /** @testdox When passing a none string or array of strings an exception should be thrown -- SINGLE FLOAT */
    public function testNormaliseNodesFailsWithSingleNoneString(): void
    {
        $this->expectException(Exception::class);
        $this->getFactory()->extractAndUnquote('col', 3.1456);
    }

    /** @testdox When passing a none string or array of strings an exception should be thrown -- ARRAY MIXED */
    public function testNormaliseNodesFailsWithArrayOfNoneStrings(): void
    {
        $this->expectException(Exception::class);
        $this->getFactory()->extractAndUnquote('col', [3.1456, false, null, 24, ['array']]);
    }
}
