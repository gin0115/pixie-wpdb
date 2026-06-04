<?php

declare(strict_types=1);

/**
 * Live-DB integration tests for JSON Support Phase 2 (#28).
 *
 * Proves the verbose JSON_* modification expressions actually execute against
 * the WP PHPUnit MySQL/MariaDB instance and produce the expected stored JSON.
 *
 * @since 0.2.0
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\JSON;

use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\JsonQueryBuilder;

class TestJsonPhase2Integration extends WP_UnitTestCase
{
    /** @var \wpdb */
    private $wpdb;

    /** @var bool */
    protected static $created = false;

    public function setUp(): void
    {
        global $wpdb;
        $this->wpdb = clone $wpdb;
        parent::setUp();

        if (! static::$created) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            \dbDelta(
                "CREATE TABLE mock_json_p2 (
                 id mediumint(8) unsigned NOT NULL auto_increment,
                 jsonCol json NULL,
                 PRIMARY KEY  (id)
                ) COLLATE {$this->wpdb->collate}"
            );
            static::$created = true;
        }

        $this->wpdb->query('TRUNCATE TABLE mock_json_p2');
    }

    private function qb(): JsonQueryBuilder
    {
        return new JsonQueryBuilder(new Connection($this->wpdb, []));
    }

    private function seed(string $json): int
    {
        $this->wpdb->insert('mock_json_p2', ['jsonCol' => $json], ['%s']);
        return (int) $this->wpdb->insert_id;
    }

    private function readJson(int $id): array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT jsonCol FROM mock_json_p2 WHERE id = %d', $id)
        );
        return (array) \json_decode($row->jsonCol, true);
    }

    /** @testdox [#28] JSON_SET via set() updates a nested value in the live DB. */
    public function testSetUpdatesValue(): void
    {
        $id = $this->seed('{"profile": {"name": "Old"}}');

        $qb = $this->qb();
        $qb->table('mock_json_p2')->where('id', '=', $id)
            ->update(['jsonCol' => $qb->jsonExpression()->set('jsonCol', ['profile', 'name'], 'New')]);

        $data = $this->readJson($id);
        $this->assertEquals('New', $data['profile']['name']);
    }

    /** @testdox [#28] JSON_ARRAY_APPEND via appendArray() appends to a live array. */
    public function testAppendArray(): void
    {
        $id = $this->seed('{"list": [1, 2]}');

        $qb = $this->qb();
        $qb->table('mock_json_p2')->where('id', '=', $id)
            ->update(['jsonCol' => $qb->jsonExpression()->appendArray('jsonCol', ['list'], 3)]);

        $data = $this->readJson($id);
        $this->assertEquals([1, 2, 3], $data['list']);
    }

    /** @testdox [#28] JSON_REMOVE via remove() drops a key in the live DB. */
    public function testRemoveKey(): void
    {
        $id = $this->seed('{"keep": 1, "drop": 2}');

        $qb = $this->qb();
        $qb->table('mock_json_p2')->where('id', '=', $id)
            ->update(['jsonCol' => $qb->jsonExpression()->remove('jsonCol', ['drop'])]);

        $data = $this->readJson($id);
        $this->assertArrayHasKey('keep', $data);
        $this->assertArrayNotHasKey('drop', $data);
    }

    /** @testdox [#28] JSON_MERGE_PATCH via mergePatch() merges a document in the live DB. */
    public function testMergePatch(): void
    {
        $id = $this->seed('{"a": 1, "b": 2}');

        $qb = $this->qb();
        $qb->table('mock_json_p2')->where('id', '=', $id)
            ->update(['jsonCol' => $qb->jsonExpression()->mergePatch('jsonCol', ['b' => 20, 'c' => 30])]);

        $data = $this->readJson($id);
        $this->assertEquals(['a' => 1, 'b' => 20, 'c' => 30], $data);
    }

    /** @testdox [#28] orderByJson() with a cast sorts JSON values numerically in the live DB. */
    public function testOrderByJsonCastNumeric(): void
    {
        $this->seed('{"age": "9"}');
        $this->seed('{"age": "10"}');
        $this->seed('{"age": "2"}');

        // Cast to UNSIGNED so 10 sorts after 9 (numeric, not lexicographic).
        $rows = $this->qb()->table('mock_json_p2')
            ->select('jsonCol')
            ->orderByJson('jsonCol', 'age', 'ASC', 'UNSIGNED')
            ->get();

        $ages = array_map(static function ($row): int {
            return (int) \json_decode($row->jsonCol, true)['age'];
        }, $rows);

        $this->assertEquals([2, 9, 10], $ages);
    }
}
