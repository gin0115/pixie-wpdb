<?php

declare(strict_types=1);

/**
 * Unit tests for the Criteria Builder
 *
 * @since 0.2.0
 * @author GLynn Quelch <glynn.quelch@gmail.com>
 */

namespace Pixie\Tests\Unit\Criteria;

use Pixie\Binding;
use WP_UnitTestCase;
use Pixie\Connection;
use Pixie\QueryBuilder\Raw;
use Pixie\Tests\Logable_WPDB;
use Pixie\Parser\CriteriaBuilder;
use Pixie\Statement\WhereStatement;

/**
 * @group v0.2
 * @group unit
 * @group criteria
 */
class TestCritieraBuilder extends WP_UnitTestCase
{
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
     * Create an instance of the CriteriaBuilder with a defined connection config.
     *
     * @param array $connectionConfig
     * @return \CriteriaBuilder
     */
    public function getBuilder(array $connectionConfig = []): CriteriaBuilder
    {
        return new CriteriaBuilder(new Connection($this->wpdb, $connectionConfig));
    }

    public function testBuildWhereBetween(): void
    {
        $statement = new WhereStatement('table.field', 'NOT BETWEEN', [Binding::asInt('2'), new Raw(12)]);
        $statement1 = new WhereStatement('table.field2', 'BETWEEN', [Binding::asInt('123'), 787879], 'OR');
        $builder = $this->getBuilder(['prefix' => 'ff_']);
        $builder->fromStatements([$statement,$statement1]);
    }
}
