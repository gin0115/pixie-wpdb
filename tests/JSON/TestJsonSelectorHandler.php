<?php

declare(strict_types=1);

/**
 * Unit tests for the JsonSelectorHandler
 *
 * @since 0.1.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\JSON;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\JSON\JsonHandler;
use Pixie\JSON\JsonSelectorHandler;

class TestJsonSelectorHandler extends WP_UnitTestCase
{
    /** @testdox It should be possible to check if a value is a valid JSON selector */
    public function testIsJsonSelector(): void
    {
        $cases = [
            ['single', false],
            ['dot.notation', false],
            ['missing->', false],
            [123, false],
            [false, false],
            [[1,2,3], false],
            ['two->deep', true],
            ['two->deepArray[1]', true],
            ['three->deep->eep', true],
        ];

        $handler = new JsonSelectorHandler();

        foreach ($cases as list($val,$result)) {
            $this->assertTrue(
                $result === $handler->isJsonSelector($val),
                sprintf("Failed on %s", is_array($val) ? 'ARRAY' : $val)
            );
        }
    }

    /** @testdox It should be possible to create an instance of a JsonSelector model from an expression. */
    public function testAsSelector(): void
    {
        $handler = new JsonSelectorHandler();
        $selector = $handler->toJsonSelector('column->node1->node2');
        $this->assertEquals('column', $selector->getColumn());
        $this->assertEquals(['node1','node2'], $selector->getNodes());
    }

    /** @testdox It should be possible to get the column from a JSON selector */
    public function testGetColumn(): void
    {
        $handler = new JsonSelectorHandler();
        $this->assertSame('column', $handler->getColumn('column->node'));
    }

    /** @testdox Attemping to get the column of an invalid expression should result in an exception */
    public function testGetColumnException(): void
    {
        $this->expectExceptionMessage('JSON expression must contain at least 2 values, the table column and at least 1 node.');
        (new JsonSelectorHandler())->getColumn('missing');
    }

    /** @testdox It should be possible to get the nodes from a JSON selector */
    public function testGetNodes(): void
    {
        $handler = new JsonSelectorHandler();
        $this->assertSame(['node'], $handler->getNodes('column->node'));
        $this->assertSame(['node', 'second'], $handler->getNodes('column->node->second'));
    }

    /** @testdox Attempting to get the nodes of an invalid expression should result in an exception */
    public function testGetNodesException(): void
    {
        $this->expectExceptionMessage('JSON expression must contain at least 2 values, the table column and at least 1 node.');
        (new JsonSelectorHandler())->getNodes('missing');
    }
}
