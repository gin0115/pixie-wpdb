<?php

declare(strict_types=1);

/**
 * Tests for JSON Support Phase 2 modification expressions (#28).
 *
 * Verifies the verbose, portable JSON_* function syntax (never ->/->>) is
 * generated for set/insert/replace/array append+insert/remove/merge variants,
 * plus the orderBy cast-to-type helper.
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\JSON;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\Tests\Logable_WPDB;
use Pixie\JSON\JsonExpressionFactory;
use Pixie\QueryBuilder\JsonQueryBuilder;

class TestJsonPhase2Expressions extends WP_UnitTestCase
{
    private function exprFactory(): JsonExpressionFactory
    {
        return new JsonExpressionFactory(new Connection(new Logable_WPDB(), []));
    }

    private function jsonQb(): JsonQueryBuilder
    {
        return new JsonQueryBuilder(new Connection(new Logable_WPDB(), []));
    }

    /** @testdox [#28] set() generates JSON_SET. */
    public function testSet(): void
    {
        $this->assertEquals(
            'JSON_SET(data, "$.foo.bar", \'baz\')',
            (string) $this->exprFactory()->set('data', ['foo', 'bar'], 'baz')
        );
    }

    /** @testdox [#28] insert() generates JSON_INSERT. */
    public function testInsert(): void
    {
        $this->assertEquals(
            'JSON_INSERT(data, "$.foo", 5)',
            (string) $this->exprFactory()->insert('data', 'foo', 5)
        );
    }

    /** @testdox [#28] replace() generates JSON_REPLACE. */
    public function testReplace(): void
    {
        $this->assertEquals(
            'JSON_REPLACE(data, "$.foo", true)',
            (string) $this->exprFactory()->replace('data', 'foo', true)
        );
    }

    /** @testdox [#28] appendArray() generates JSON_ARRAY_APPEND. */
    public function testAppendArray(): void
    {
        $this->assertEquals(
            'JSON_ARRAY_APPEND(data, "$.list", 9)',
            (string) $this->exprFactory()->appendArray('data', 'list', 9)
        );
    }

    /** @testdox [#28] insertArray() generates JSON_ARRAY_INSERT with an indexed path. */
    public function testInsertArray(): void
    {
        $this->assertEquals(
            'JSON_ARRAY_INSERT(data, "$.list[1]", \'x\')',
            (string) $this->exprFactory()->insertArray('data', ['list[1]'], 'x')
        );
    }

    /** @testdox [#28] remove() generates JSON_REMOVE. */
    public function testRemove(): void
    {
        $this->assertEquals(
            'JSON_REMOVE(data, "$.foo.bar")',
            (string) $this->exprFactory()->remove('data', ['foo', 'bar'])
        );
    }

    /** @testdox [#28] mergePreserve() and merge() generate JSON_MERGE_PRESERVE with a portable JSON document. */
    public function testMergePreserve(): void
    {
        $expected = 'JSON_MERGE_PRESERVE(data, JSON_EXTRACT(\'{"a":1}\', \'$\'))';
        $this->assertEquals($expected, (string) $this->exprFactory()->mergePreserve('data', ['a' => 1]));
        $this->assertEquals($expected, (string) $this->exprFactory()->merge('data', ['a' => 1]));
    }

    /** @testdox [#28] mergePatch() generates JSON_MERGE_PATCH with a portable JSON document. */
    public function testMergePatch(): void
    {
        $this->assertEquals(
            'JSON_MERGE_PATCH(data, JSON_EXTRACT(\'{"a":1}\', \'$\'))',
            (string) $this->exprFactory()->mergePatch('data', ['a' => 1])
        );
    }

    /** @testdox [#28] An array value is inlined as a portable JSON_EXTRACT document (works on MySQL & MariaDB). */
    public function testArrayValueIsPortableJsonDocument(): void
    {
        $this->assertEquals(
            'JSON_SET(data, "$.list", JSON_EXTRACT(\'[1,2,3]\', \'$\'))',
            (string) $this->exprFactory()->set('data', 'list', [1, 2, 3])
        );
    }

    /** @testdox [#28] A null value is inlined as NULL. */
    public function testNullValue(): void
    {
        $this->assertEquals(
            'JSON_SET(data, "$.foo", NULL)',
            (string) $this->exprFactory()->set('data', 'foo', null)
        );
    }

    /** @testdox [#28] Single quotes in string values are escaped. */
    public function testStringValueEscaped(): void
    {
        $this->assertEquals(
            'JSON_SET(data, "$.name", \'O\'\'Brien\')',
            (string) $this->exprFactory()->set('data', 'name', "O'Brien")
        );
    }

    /** @testdox [#28] orderByCast() generates a CAST over JSON_UNQUOTE(JSON_EXTRACT()). */
    public function testOrderByCast(): void
    {
        $this->assertEquals(
            'CAST(JSON_UNQUOTE(JSON_EXTRACT(data, "$.age")) AS UNSIGNED)',
            (string) $this->exprFactory()->orderByCast('data', 'age', 'UNSIGNED')
        );
    }

    /** @testdox [#28] orderByJson() with a cast type adds an ORDER BY using the cast expression. */
    public function testFluentOrderByJsonWithCast(): void
    {
        $sql = $this->jsonQb()->table('foo')
            ->orderByJson('data', 'age', 'DESC', 'UNSIGNED')
            ->getQuery()->getSql();

        $this->assertEquals(
            'SELECT * FROM foo ORDER BY CAST(JSON_UNQUOTE(JSON_EXTRACT(data, "$.age")) AS UNSIGNED) DESC',
            $sql
        );
    }

    /** @testdox [#28][BC] orderByJson() without a cast keeps the existing extract-and-unquote behaviour. */
    public function testFluentOrderByJsonNoCastUnchanged(): void
    {
        $sql = $this->jsonQb()->table('foo')
            ->orderByJson('data', 'age', 'DESC')
            ->getQuery()->getSql();

        $this->assertEquals(
            'SELECT * FROM foo ORDER BY JSON_UNQUOTE(JSON_EXTRACT(data, "$.age")) DESC',
            $sql
        );
    }

    /** @testdox [#28] A JSON modification expression composes into update(). */
    public function testComposesIntoUpdate(): void
    {
        $qb  = $this->jsonQb();
        $sql = $qb->table('foo')
            ->where('id', '=', 1)
            ->update(['data' => $qb->jsonExpression()->set('data', ['profile', 'name'], 'Sam')]);

        // The Logable_WPDB records the prepared query.
        $log = $qb->dbInstance()->usage_log['get_results'][0]['query'] ?? '';
        $this->assertStringContainsString('JSON_SET(data, "$.profile.name", \'Sam\')', $log);
    }
}
